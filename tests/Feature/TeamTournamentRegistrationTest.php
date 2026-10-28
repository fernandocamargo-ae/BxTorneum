<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

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

    public function test_a_manager_can_add_a_complete_team(): void
    {
        $tournament = $this->teamTournament();
        $team = $this->completeTeam('Fénix');

        $this->actingAs($this->admin())
            ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'team_id' => $team->id]);
    }

    public function test_an_incomplete_team_cannot_be_added(): void
    {
        $tournament = $this->teamTournament();
        $team = Team::create(['name' => 'Incompleto']);

        $this->actingAs($this->admin())
            ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('tournament_entries', ['team_id' => $team->id]);
    }

    public function test_a_team_cannot_be_added_twice(): void
    {
        $tournament = $this->teamTournament();
        $team = $this->completeTeam('Fénix');
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);

        $this->actingAs($this->admin())
            ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
            ->assertStatus(422);
    }

    public function test_teams_cannot_be_added_after_registration(): void
    {
        $tournament = $this->teamTournament(['status' => 'swiss', 'current_round' => 1]);
        $team = $this->completeTeam('Fénix');

        $this->actingAs($this->admin())
            ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
            ->assertStatus(422);
    }

    public function test_a_non_manager_cannot_add_teams(): void
    {
        $tournament = $this->teamTournament();
        $team = $this->completeTeam('Fénix');
        $jugador = User::factory()->create(['is_admin' => false, 'is_amateur' => false, 'is_guest' => false]);

        $this->actingAs($jugador)
            ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
            ->assertForbidden();
    }

    public function test_removing_a_team_entry_uses_the_team_name(): void
    {
        $tournament = $this->teamTournament();
        $team = $this->completeTeam('Fénix');
        $entry = TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);

        $this->actingAs($this->admin())
            ->delete("/tournament/{$tournament->id}/entries/{$entry->id}")
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Fénix'));

        $this->assertDatabaseMissing('tournament_entries', ['id' => $entry->id]);
    }
}
