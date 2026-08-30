<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentFullSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_eight_player_tournament_runs_end_to_end_to_a_champion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $players = User::factory()->count(8)->create();
        foreach ($players as $player) {
            $deck = $player->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
            $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        }

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        foreach ($players as $player) {
            $this->actingAs($player)->post('/tournament/join')->assertRedirect();
        }

        // Three Swiss rounds: after each, report every match by declaring entry_one the winner
        // (arbitrary but deterministic), then generate the next round.
        for ($round = 1; $round <= 3; $round++) {
            $this->actingAs($admin)->post('/tournament/rounds')->assertRedirect();

            $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();
            $currentRound = $tournament->rounds()->where('number', $round)->firstOrFail();

            foreach ($currentRound->matches as $match) {
                if ($match->is_bye) {
                    continue;
                }
                $this->actingAs($admin)
                    ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                    ->assertRedirect();
            }
        }

        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(3, $tournament->current_round);

        $this->actingAs($admin)->post('/tournament/cut')->assertRedirect();

        $tournament->refresh();
        $this->assertSame('elimination', $tournament->status);
        $this->assertSame(4, $tournament->current_round); // round 4 = semifinal (top 4 cut)

        // Semifinal (round 4): 2 matches.
        $semifinal = $tournament->rounds()->where('number', 4)->firstOrFail();
        $this->assertCount(2, $semifinal->matches);
        foreach ($semifinal->matches as $match) {
            $this->actingAs($admin)
                ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                ->assertRedirect();
        }

        $this->actingAs($admin)->post('/tournament/rounds')->assertRedirect();

        $tournament->refresh();
        $this->assertSame(5, $tournament->current_round);

        // Final (round 5): 1 match; reporting it must close the tournament.
        $final = $tournament->rounds()->where('number', 5)->firstOrFail();
        $this->assertCount(1, $final->matches);
        $finalMatch = $final->matches->first();

        $this->actingAs($admin)
            ->patch("/tournament/matches/{$finalMatch->id}", ['winner_entry_id' => $finalMatch->entry_one_id])
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertSame($finalMatch->entry_one_id, $tournament->champion_entry_id);

        // No further round can be generated once the tournament is completed.
        $this->actingAs($admin)->post('/tournament/rounds')->assertStatus(404);
    }
}
