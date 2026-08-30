<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentEntryRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function registrationTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    private function entryFor(Tournament $tournament, User $user): TournamentEntry
    {
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $tournament = $this->registrationTournament();
        $entry = $this->entryFor($tournament, User::factory()->create());

        $this->delete("/tournament/entries/{$entry->id}")->assertRedirect('/login');
    }

    public function test_non_admin_cannot_remove_a_participant(): void
    {
        $tournament = $this->registrationTournament();
        $entry = $this->entryFor($tournament, User::factory()->create());
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->delete("/tournament/entries/{$entry->id}")->assertForbidden();
        $this->assertDatabaseHas('tournament_entries', ['id' => $entry->id]);
    }

    public function test_admin_can_remove_a_participant_with_a_reason(): void
    {
        $tournament = $this->registrationTournament();
        $player = User::factory()->create(['nickname' => 'Removido']);
        $entry = $this->entryFor($tournament, $player);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->delete("/tournament/entries/{$entry->id}", ['reason' => 'Se salió del torneo'])
            ->assertRedirect();

        $this->assertDatabaseMissing('tournament_entries', ['id' => $entry->id]);
    }

    public function test_removed_participant_can_join_again_themselves(): void
    {
        $tournament = $this->registrationTournament();
        $player = User::factory()->create();
        $entry = $this->entryFor($tournament, $player);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete("/tournament/entries/{$entry->id}");

        $this->actingAs($player)->post('/tournament/join')->assertRedirect();

        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $player->id]);
    }

    public function test_cannot_remove_a_participant_once_swiss_rounds_have_started(): void
    {
        $tournament = $this->registrationTournament();
        $entry = $this->entryFor($tournament, User::factory()->create());
        $tournament->update(['status' => 'swiss', 'current_round' => 1]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete("/tournament/entries/{$entry->id}")->assertStatus(422);
        $this->assertDatabaseHas('tournament_entries', ['id' => $entry->id]);
    }

    public function test_cannot_remove_an_entry_from_a_different_tournament(): void
    {
        $tournament = $this->registrationTournament();
        $other = Tournament::create([
            'name' => 'Copa Vieja', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $entry = $this->entryFor($other, User::factory()->create());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete("/tournament/entries/{$entry->id}")->assertNotFound();
        $this->assertDatabaseHas('tournament_entries', ['id' => $entry->id]);
    }
}
