<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Notifications\PairedForRound;
use App\Support\TournamentRoundFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TournamentRoundFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_creates_round_and_matches_updates_tournament_and_notifies_players(): void
    {
        Notification::fake();

        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);

        $round = TournamentRoundFactory::create($tournament, 'swiss', [[$a->id, $b->id], [$c->id, null]]);

        $this->assertSame(1, $round->number);
        $this->assertSame('swiss', $round->phase);
        $this->assertCount(2, $round->matches);

        $tournament->refresh();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(1, $tournament->current_round);

        $byeMatch = $round->matches->firstWhere('is_bye', true);
        $this->assertSame($c->id, $byeMatch->winner_entry_id);

        Notification::assertSentTo($a->user, PairedForRound::class);
        Notification::assertSentTo($b->user, PairedForRound::class);
        Notification::assertNotSentTo($c->user, PairedForRound::class);
    }
}
