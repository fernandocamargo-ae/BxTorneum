<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_deck_with_up_to_three_combos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/decks', [
            'name' => 'Ofensivo',
            'visibility' => 'private',
            'beyblades' => [
                ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
                ['line' => '', 'parts' => []],
                ['line' => '', 'parts' => []],
            ],
        ]);

        $response->assertRedirect('/decks');
        $deck = Deck::first();
        $this->assertSame('Ofensivo', $deck->name);
        $this->assertCount(1, $deck->deckBeyblades);
    }

    public function test_user_can_create_a_deck_with_more_than_three_combos(): void
    {
        $user = User::factory()->create();
        $blank = fn (string $blade) => ['line' => 'bx', 'parts' => ['blade' => $blade, 'ratchet' => '3-60', 'bit' => 'Flat']];

        $response = $this->actingAs($user)->post('/decks', [
            'name' => 'Full ataque',
            'visibility' => 'private',
            'beyblades' => [
                $blank('Dran Sword'),
                $blank('Wizard Arrow'),
                $blank('Cobalt Drake'),
                $blank('Meteor Dragoon'),
                $blank('Shark Edge'),
            ],
        ]);

        $response->assertRedirect('/decks');
        $this->assertCount(5, Deck::first()->deckBeyblades);
    }

    public function test_user_cannot_touch_another_users_deck(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $deck = $owner->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private']);

        $this->actingAs($other)->get("/decks/{$deck->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/decks/{$deck->id}", [
            'name' => 'Hackeado',
            'visibility' => 'private',
            'beyblades' => [],
        ])->assertForbidden();
        $this->actingAs($other)->delete("/decks/{$deck->id}")->assertForbidden();
    }

    public function test_marking_a_deck_as_tournament_unmarks_the_previous_one(): void
    {
        $user = User::factory()->create();
        $deckA = $user->decks()->create(['name' => 'A', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deckB = $user->decks()->create(['name' => 'B', 'visibility' => 'private']);

        $this->actingAs($user)->patch("/decks/{$deckB->id}/tournament", ['value' => true]);

        $this->assertFalse($deckA->fresh()->is_tournament_deck);
        $this->assertTrue($deckB->fresh()->is_tournament_deck);
    }

    public function test_editing_a_deck_replaces_its_combos(): void
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private']);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $response = $this->actingAs($user)->put("/decks/{$deck->id}", [
            'name' => 'Ofensivo',
            'visibility' => 'public',
            'beyblades' => [
                ['line' => 'ux', 'parts' => ['blade' => 'Cobalt Drake', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ],
        ]);

        $response->assertRedirect('/decks');
        $this->assertCount(1, $deck->fresh()->deckBeyblades);
        $this->assertSame('ux', $deck->fresh()->deckBeyblades->first()->line);
    }
}
