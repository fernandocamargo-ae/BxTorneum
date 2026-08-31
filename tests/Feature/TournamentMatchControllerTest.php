<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TournamentMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    private function swissTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_non_admin_cannot_report_a_result(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $a->id])
            ->assertForbidden();
    }

    public function test_admin_reports_a_swiss_match_result(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $a->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_matches', ['id' => $match->id, 'winner_entry_id' => $a->id]);
        $this->assertSame('swiss', $tournament->fresh()->status); // reporting a swiss match never completes the tournament
    }

    public function test_admin_can_correct_a_previously_reported_result_in_the_current_round(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $b->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_matches', ['id' => $match->id, 'winner_entry_id' => $b->id]);
    }

    public function test_cannot_correct_a_result_once_a_later_round_has_been_generated(): void
    {
        $tournament = $this->swissTournament(); // current_round = 1
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $tournament->update(['current_round' => 2]);
        $tournament->rounds()->create(['number' => 2, 'phase' => 'swiss']);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $b->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('tournament_matches', ['id' => $match->id, 'winner_entry_id' => $a->id]);
    }

    public function test_winner_must_be_one_of_the_two_entries_in_the_match(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $stranger = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $stranger->id])
            ->assertStatus(422);
    }

    public function test_reporting_the_final_completes_the_tournament_with_a_champion(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 3, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $finalRound = $tournament->rounds()->create(['number' => 3, 'phase' => 'elimination']);
        $final = $finalRound->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$final->id}", ['winner_entry_id' => $a->id])
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertSame($a->id, $tournament->champion_entry_id);
    }

    public function test_admin_can_correct_the_champion_of_a_completed_final_match(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 3, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $finalRound = $tournament->rounds()->create(['number' => 3, 'phase' => 'elimination']);
        $final = $finalRound->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $tournament->update(['status' => 'completed', 'champion_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$final->id}", ['winner_entry_id' => $b->id])
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertSame($b->id, $tournament->champion_entry_id);
    }

    public function test_completing_the_tournament_destroys_guest_sessions_but_not_real_members(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 3, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $guest = User::factory()->create(['is_guest' => true]);
        $guestDeck = $guest->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $guestEntry = $tournament->entries()->create(['user_id' => $guest->id, 'deck_id' => $guestDeck->id]);

        $member = User::factory()->create(['is_guest' => false]);
        $memberDeck = $member->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $memberEntry = $tournament->entries()->create(['user_id' => $member->id, 'deck_id' => $memberDeck->id]);

        DB::table('sessions')->insert([
            ['id' => 'guest-session', 'user_id' => $guest->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'member-session', 'user_id' => $member->id, 'payload' => 'x', 'last_activity' => time()],
        ]);

        $finalRound = $tournament->rounds()->create(['number' => 3, 'phase' => 'elimination']);
        $final = $finalRound->matches()->create(['entry_one_id' => $guestEntry->id, 'entry_two_id' => $memberEntry->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$final->id}", ['winner_entry_id' => $guestEntry->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'guest-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'member-session']);
        $this->assertDatabaseHas('tournament_entries', ['id' => $guestEntry->id]);
        $this->assertDatabaseHas('decks', ['id' => $guestDeck->id]);
    }
}
