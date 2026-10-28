<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_clandestine_teams_list_shows_only_team_clandestine_tournaments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $base = ['status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $admin->id];
        Tournament::create(['name' => 'TeamClan', 'type' => 'clandestine', 'format' => 'teams'] + $base);
        Tournament::create(['name' => 'IndClan', 'type' => 'clandestine', 'format' => 'individual'] + $base);
        Tournament::create(['name' => 'TeamOff', 'type' => 'official', 'format' => 'teams'] + $base);

        $this->actingAs($admin)
            ->get('/torneo-equipos/clandestinos')
            ->assertInertia(fn ($page) => $page
                ->component('Tournament/Teams/Index')
                ->where('tournaments', fn ($tournaments) => collect($tournaments)->pluck('name')->all() === ['TeamClan']));
    }
}
