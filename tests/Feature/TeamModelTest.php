<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_aggregates_members_beyblades_and_parts(): void
    {
        $team = Team::create(['name' => 'Storm']);
        $member = $team->members()->create(['role' => 'captain', 'name' => 'Aoi']);
        $beyblade = $member->beyblades()->create(['line' => 'bx', 'position' => 1]);

        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);

        $this->assertCount(1, $team->members);
        $this->assertCount(1, $member->beyblades);
        $this->assertSame('Dran Sword', $beyblade->partsBySlot()['blade']);
    }

    public function test_parts_by_slot_orders_cx_fields_regardless_of_attach_order(): void
    {
        $team = Team::create(['name' => 'Storm']);
        $member = $team->members()->create(['role' => 'captain', 'name' => 'Aoi']);
        $beyblade = $member->beyblades()->create(['line' => 'cx', 'position' => 1]);

        // Attach in a scrambled order to prove the display order doesn't depend on it.
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'bit', 'name' => 'Kick'])->id, ['slot' => 'bit']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'ratchet', 'name' => '4-50'])->id, ['slot' => 'ratchet']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'lock_chip', 'name' => 'Emperor'])->id, ['slot' => 'lock_chip']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'assist_blade', 'name' => 'Heavy'])->id, ['slot' => 'assist_blade']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'main_blade', 'name' => 'Hunt'])->id, ['slot' => 'main_blade']);

        $this->assertSame(
            ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
            array_keys($beyblade->partsBySlot())
        );
    }
}
