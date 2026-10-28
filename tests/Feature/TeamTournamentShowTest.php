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
        $captain = $this->playerWithTournamentDeck();
        $team = Team::create(['name' => $name, 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);
        $team->members()->create(['user_id' => $this->playerWithTournamentDeck()->id, 'role' => 'subcaptain']);
        $team->members()->create(['user_id' => $this->playerWithTournamentDeck()->id, 'role' => 'official']);

        return $team;
    }

    private function playerWithTournamentDeck(): User
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $user;
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
