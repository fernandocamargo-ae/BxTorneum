<?php

namespace Tests\Feature;

use Database\Seeders\PartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_parts_for_every_slot_type(): void
    {
        $this->seed(PartSeeder::class);

        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
        $this->assertDatabaseHas('parts', ['type' => 'ratchet', 'name' => '4-60']);
        $this->assertDatabaseHas('parts', ['type' => 'bit', 'name' => 'Flat']);
        $this->assertDatabaseHas('parts', ['type' => 'lock_chip', 'name' => 'Dran']);
        $this->assertDatabaseHas('parts', ['type' => 'main_blade', 'name' => 'Sword']);
        $this->assertDatabaseHas('parts', ['type' => 'assist_blade', 'name' => 'Level']);
        $this->assertDatabaseHas('parts', ['type' => 'over_blade', 'name' => 'Dran Sword']);
        $this->assertDatabaseHas('parts', ['type' => 'metal_blade', 'name' => 'Dran']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PartSeeder::class);
        $countAfterFirstRun = \App\Models\Part::count();

        $this->seed(PartSeeder::class);

        $this->assertSame($countAfterFirstRun, \App\Models\Part::count());
    }
}
