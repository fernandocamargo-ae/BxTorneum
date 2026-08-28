<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_resolve_across_the_tournament_graph(): void
    {
        $organizer = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $deck = $player->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $tournament = Tournament::create([
            'name' => 'Copa X',
            'status' => 'registration',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => $organizer->id,
        ]);

        $entry = TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'deck_id' => $deck->id,
        ]);

        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);

        $match = $round->matches()->create([
            'entry_one_id' => $entry->id,
            'entry_two_id' => null,
            'winner_entry_id' => $entry->id,
            'is_bye' => true,
        ]);

        $this->assertTrue($tournament->entries->first()->is($entry));
        $this->assertTrue($tournament->rounds->first()->is($round));
        $this->assertTrue($round->tournament->is($tournament));
        $this->assertTrue($round->matches->first()->is($match));
        $this->assertTrue($match->round->is($round));
        $this->assertTrue($match->entryOne->is($entry));
        $this->assertTrue($match->winner->is($entry));
        $this->assertTrue($entry->user->is($player));
        $this->assertTrue($entry->deck->is($deck));
        $this->assertTrue($entry->tournament->is($tournament));
        $this->assertTrue($tournament->createdBy->is($organizer));
        $this->assertTrue($match->is_bye);
    }
}
