<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/players')->assertRedirect('/login');
    }

    public function test_index_only_lists_users_with_a_tournament_deck_marked(): void
    {
        $withTournamentDeck = User::factory()->create();
        $withTournamentDeck->decks()->create(['name' => 'Torneo', 'visibility' => 'public', 'is_tournament_deck' => true]);

        $withUnmarkedDeckOnly = User::factory()->create();
        $withUnmarkedDeckOnly->decks()->create(['name' => 'Borrador', 'visibility' => 'public', 'is_tournament_deck' => false]);

        $withNoDecks = User::factory()->create();

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->has('players', 1)
            ->where('players.0.nickname', $withTournamentDeck->nickname)
        );
    }

    public function test_index_lists_a_private_tournament_deck_too(): void
    {
        $withPrivateTournamentDeck = User::factory()->create();
        $withPrivateTournamentDeck->decks()->create(['name' => 'Torneo privado', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->where('players.0.nickname', $withPrivateTournamentDeck->nickname)
            ->where('players.0.public_decks_count', 0)
        );
    }

    public function test_show_only_exposes_public_decks(): void
    {
        $player = User::factory()->create();
        $player->decks()->create(['name' => 'Público', 'visibility' => 'public']);
        $player->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get("/players/{$player->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Show')
            ->has('decks', 1)
            ->where('decks.0.name', 'Público')
        );
    }
}
