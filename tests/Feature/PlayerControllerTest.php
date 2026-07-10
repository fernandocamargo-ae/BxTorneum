<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Players/Index and Players/Show Vue pages don't exist yet (built in a later
        // task); disable Inertia's page-existence check for this test class only so
        // assertInertia() can verify props/component name without requiring the
        // frontend files to be present.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/players')->assertRedirect('/login');
    }

    public function test_index_lists_only_users_with_public_decks(): void
    {
        $withPublic = User::factory()->create();
        $withPublic->decks()->create(['name' => 'Público', 'visibility' => 'public']);

        $withPrivateOnly = User::factory()->create();
        $withPrivateOnly->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->has('players', 1)
            ->where('players.0.nickname', $withPublic->nickname)
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
