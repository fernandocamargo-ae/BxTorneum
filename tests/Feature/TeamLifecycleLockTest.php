<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLifecycleLockTest extends TestCase
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

    private function enroll(Team $team, string $status): Tournament
    {
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams',
            'status' => $status, 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => $status === 'registration' ? 0 : 1,
            'created_by_user_id' => User::factory()->create()->id,
        ]);
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);

        return $tournament;
    }

    private function namePayload(): array
    {
        return [
            'name' => 'Renombrado',
            'members' => [
                ['role' => 'captain', 'name' => 'A'],
                ['role' => 'subcaptain', 'name' => 'B'],
                ['role' => 'official', 'name' => 'C'],
            ],
        ];
    }

    public function test_a_team_in_an_active_tournament_cannot_be_deleted(): void
    {
        $team = $this->completeTeam('Fénix');
        $this->enroll($team, 'registration');

        $this->actingAs(User::factory()->create())->delete("/teams/{$team->id}")->assertStatus(422);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_a_team_in_an_active_tournament_cannot_be_edited(): void
    {
        $team = $this->completeTeam('Fénix');
        $this->enroll($team, 'swiss');

        $this->actingAs(User::factory()->create())->put("/teams/{$team->id}", $this->namePayload())->assertStatus(422);
    }

    public function test_a_teams_combos_cannot_be_edited_during_an_active_tournament(): void
    {
        $team = $this->completeTeam('Fénix');
        $this->enroll($team, 'registration');

        $payload = ['members' => $team->members->map(fn ($m) => ['id' => $m->id, 'beyblades' => []])->values()->all()];

        $this->actingAs(User::factory()->create())->put("/teams/{$team->id}/combos", $payload)->assertStatus(422);
    }

    public function test_a_team_can_be_edited_once_its_tournament_is_completed(): void
    {
        $team = $this->completeTeam('Fénix');
        $this->enroll($team, 'completed');

        $this->actingAs(User::factory()->create())->put("/teams/{$team->id}", $this->namePayload())->assertRedirect();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Renombrado']);
    }

    public function test_a_team_that_played_cannot_be_deleted_even_after_completion(): void
    {
        $team = $this->completeTeam('Fénix');
        $this->enroll($team, 'completed');

        $this->actingAs(User::factory()->create())->delete("/teams/{$team->id}")->assertStatus(422);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }
}
