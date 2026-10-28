<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentFormatGuardTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(string $format): Tournament
    {
        return Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => $format,
            'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create(['is_admin' => true])->id,
        ]);
    }

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

    public function test_an_individual_join_is_blocked_on_a_team_tournament(): void
    {
        $teamTournament = $this->tournament('teams');

        // A user who WOULD otherwise be able to join (eligible tournament deck),
        // so the 422 proves the format guard, not a missing deck.
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $this->actingAs($user)
            ->post("/tournament/{$teamTournament->id}/join")
            ->assertStatus(422);

        $this->assertDatabaseMissing('tournament_entries', ['tournament_id' => $teamTournament->id]);
    }

    public function test_a_team_cannot_be_added_to_an_individual_tournament(): void
    {
        $individual = $this->tournament('individual');
        $team = $this->completeTeam('Fénix');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post("/torneo-equipos/{$individual->id}/equipos", ['team_id' => $team->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('tournament_entries', ['team_id' => $team->id]);
    }

    public function test_the_team_show_route_rejects_an_individual_tournament(): void
    {
        $individual = $this->tournament('individual');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/torneo-equipos/{$individual->id}")
            ->assertStatus(404);
    }
}
