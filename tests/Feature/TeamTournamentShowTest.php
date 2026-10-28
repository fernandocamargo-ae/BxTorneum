<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentShowTest extends TestCase
{
    use RefreshDatabase;

    private function completeTeam(string $name): Team
    {
        $team = Team::create(['name' => $name]);
        foreach (['captain', 'subcaptain', 'official'] as $role) {
            $member = $team->members()->create(['role' => $role, 'name' => $role]);
            for ($position = 1; $position <= 3; $position++) {
                $member->beyblades()->create(['line' => 'bx', 'position' => $position]);
            }
        }

        return $team;
    }

    private function teamTournament(array $overrides = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Copa',
            'type' => 'official',
            'format' => 'teams',
            'status' => 'registration',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_show_lists_enrolled_teams_by_name_and_marks_format_teams(): void
    {
        $tournament = $this->teamTournament();
        $team = $this->completeTeam('Equipo Fénix');
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/torneo-equipos')
            ->assertInertia(fn ($page) => $page
                ->component('Tournament/Show')
                ->where('format', 'teams')
                ->where('entries.0.nickname', 'Equipo Fénix'));
    }

    public function test_teams_pool_only_has_complete_unenrolled_teams(): void
    {
        $tournament = $this->teamTournament();
        $enrolled = $this->completeTeam('Inscrito');
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $enrolled->id]);
        $this->completeTeam('Disponible');
        Team::create(['name' => 'Incompleto']); // 0 beys → not eligible

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/torneo-equipos')
            ->assertInertia(fn ($page) => $page
                ->where('teams_pool', fn ($pool) => collect($pool)->pluck('name')->all() === ['Disponible']));
    }
}
