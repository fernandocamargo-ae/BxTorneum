<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClandestineIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_clandestine_tournaments_with_their_creator(): void
    {
        $creator = User::factory()->create(['nickname' => 'Pipo']);
        Tournament::create(['name' => 'Reta nocturna', 'type' => 'clandestine', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $creator->id]);
        Tournament::create(['name' => 'Oficial', 'type' => 'official', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $creator->id]);

        $this->actingAs(User::factory()->create())
            ->get('/tournament/clandestinos')
            ->assertInertia(fn ($page) => $page
                ->component('Tournament/Clandestine/Index')
                ->has('tournaments', 1)
                ->where('tournaments.0.name', 'Reta nocturna')
                ->where('tournaments.0.status', 'registration')
                ->where('tournaments.0.entries_count', 0)
                ->where('tournaments.0.creator_nickname', 'Pipo')
            );
    }

    public function test_the_clandestinos_path_is_not_captured_by_the_tournament_id_route(): void
    {
        // Must resolve the list, not try to bind a tournament named "clandestinos" (404).
        $this->actingAs(User::factory()->create())
            ->get('/tournament/clandestinos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournament/Clandestine/Index'));
    }
}
