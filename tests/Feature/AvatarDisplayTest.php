<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_players_list_exposes_each_players_avatar_url(): void
    {
        $player = User::factory()->create(['nickname' => 'Pipo', 'avatar_path' => 'avatars/pipo.jpg']);
        $player->decks()->create(['name' => 'T', 'visibility' => 'public', 'is_tournament_deck' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/players')
            ->assertInertia(fn ($page) => $page
                ->component('Players/Index')
                ->where('players.0.nickname', 'Pipo')
                ->where('players.0.avatar_url', fn ($url) => is_string($url) && str_contains($url, 'avatars/pipo.jpg'))
            );
    }

    public function test_the_tournament_entries_expose_each_players_avatar_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'status' => 'registration',
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);
        $player = User::factory()->create(['nickname' => 'Haydee', 'avatar_path' => 'avatars/haydee.jpg']);
        $deck = $player->decks()->create(['name' => 'T', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $tournament->entries()->create(['user_id' => $player->id, 'deck_id' => $deck->id]);

        $this->actingAs($admin)
            ->get("/tournament/{$tournament->id}")
            ->assertInertia(fn ($page) => $page
                ->where('entries.0.nickname', 'Haydee')
                ->where('entries.0.avatar_url', fn ($url) => is_string($url) && str_contains($url, 'avatars/haydee.jpg'))
            );
    }
}
