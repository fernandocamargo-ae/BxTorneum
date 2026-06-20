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
}
