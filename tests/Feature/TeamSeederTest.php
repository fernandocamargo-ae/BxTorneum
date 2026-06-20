<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_thirteen_teams_with_members_and_no_beyblades(): void
    {
        $this->seed(TeamSeeder::class);

        $this->assertDatabaseCount('teams', 13);
        $this->assertDatabaseCount('members', 39);
        $this->assertDatabaseCount('beyblades', 0);
        $this->assertDatabaseHas('members', ['name' => 'Ricardo', 'role' => 'captain']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(TeamSeeder::class);

        $this->assertDatabaseCount('teams', 13);
        $this->assertDatabaseCount('members', 39);
    }
}
