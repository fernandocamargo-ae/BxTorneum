<?php

namespace Tests\Feature;

use App\Http\Controllers\TournamentReportController;
use App\Models\Deck;
use App\Models\Part;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteEnrolledDeckTest extends TestCase
{
    use RefreshDatabase;

    private function deck(User $user): Deck
    {
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'public', 'is_tournament_deck' => true]);
        $combo = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        foreach (['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat'] as $slot => $part) {
            $combo->parts()->attach(Part::firstOrCreate(['type' => $slot, 'name' => $part])->id, ['slot' => $slot]);
        }

        return $deck;
    }

    private function tournament(User $creator, string $status): Tournament
    {
        return Tournament::create([
            'name' => 'Copa Noviembre', 'created_by_user_id' => $creator->id,
            'swiss_rounds' => 3, 'cut_size' => 4, 'status' => $status,
        ]);
    }

    private function enroll(Deck $deck, string $status): Tournament
    {
        $tournament = $this->tournament($deck->user, $status);
        TournamentEntry::create(['tournament_id' => $tournament->id, 'user_id' => $deck->user_id, 'deck_id' => $deck->id]);

        return $tournament;
    }

    public function test_a_deck_that_never_played_is_deleted_for_good(): void
    {
        $deck = $this->deck(User::factory()->create());

        $this->actingAs($deck->user)->delete("/decks/{$deck->id}")
            ->assertRedirect('/decks')->assertSessionHas('success', 'Deck eliminado.');

        $this->assertSame(0, DB::table('decks')->count());
        $this->assertSame(0, DB::table('deck_beyblade_part')->count());
    }

    public function test_a_deck_enrolled_in_an_open_tournament_explains_why_it_cannot_be_deleted(): void
    {
        foreach (['registration', 'swiss', 'elimination'] as $status) {
            $deck = $this->deck(User::factory()->create());
            $this->enroll($deck, $status);

            $this->actingAs($deck->user)->delete("/decks/{$deck->id}")
                ->assertRedirect('/decks')
                ->assertSessionHas('error', 'No puedes eliminar "Ofensivo": está inscrito en "Copa Noviembre". Podrás hacerlo cuando salgas del torneo o termine.');

            $this->assertNotNull(Deck::find($deck->id), $status);
        }
    }

    public function test_a_deck_that_already_played_disappears_but_the_tournament_history_keeps_it(): void
    {
        $deck = $this->deck(User::factory()->create(['nickname' => 'Pipo']));
        $tournament = $this->enroll($deck, 'completed');

        $this->actingAs($deck->user)->delete("/decks/{$deck->id}")
            ->assertRedirect('/decks')->assertSessionHas('success', 'Deck eliminado.');

        $this->get('/decks')->assertInertia(fn ($page) => $page->where('decks', []));
        $this->get("/players/{$deck->user_id}")->assertInertia(fn ($page) => $page->where('decks', []));
        $this->get("/decks/{$deck->id}/edit")->assertNotFound();

        $players = app(TournamentReportController::class)->playersFor($tournament);
        $this->assertSame('Ofensivo', $players[0]['deck_name']);
        $this->assertCount(1, $players[0]['beyblades']);
    }

    public function test_a_deleted_deck_no_longer_counts_to_join_a_tournament(): void
    {
        $deck = $this->deck(User::factory()->create());
        $this->enroll($deck, 'completed');
        $this->actingAs($deck->user)->delete("/decks/{$deck->id}");

        $open = $this->tournament($deck->user, 'registration');
        $this->post("/tournament/{$open->id}/join")->assertStatus(422);

        // El siguiente deck vuelve a quedar como deck de torneo, igual que el primero de cualquiera.
        $this->post('/decks', ['name' => 'Nuevo', 'visibility' => 'private', 'beyblades' => [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
        ]]);
        $this->assertTrue(Deck::where('name', 'Nuevo')->first()->is_tournament_deck);
    }

    public function test_nobody_can_delete_the_deck_of_another_player(): void
    {
        $deck = $this->deck(User::factory()->create());

        $this->actingAs(User::factory()->create())->delete("/decks/{$deck->id}")->assertForbidden();

        $this->assertNotNull(Deck::find($deck->id));
    }

    public function test_the_error_message_reaches_the_page(): void
    {
        $deck = $this->deck(User::factory()->create());
        $this->enroll($deck, 'swiss');

        $this->actingAs($deck->user)->followingRedirects()->delete("/decks/{$deck->id}")
            ->assertInertia(fn ($page) => $page->where('flash.error', fn ($text) => str_contains((string) $text, 'Copa Noviembre')));
    }
}
