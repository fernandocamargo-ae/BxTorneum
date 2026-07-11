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

    public function test_index_lists_any_user_with_a_deck_public_or_private(): void
    {
        $withPublic = User::factory()->create();
        $withPublic->decks()->create(['name' => 'Público', 'visibility' => 'public']);

        $withPrivateOnly = User::factory()->create();
        $withPrivateOnly->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $withNoDecks = User::factory()->create();

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->has('players', 2)
        );
    }

    public function test_index_shows_zero_public_decks_for_a_private_only_player(): void
    {
        $withPrivateOnly = User::factory()->create();
        $withPrivateOnly->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->where('players.0.nickname', $withPrivateOnly->nickname)
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
