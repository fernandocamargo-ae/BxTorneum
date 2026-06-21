<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_until_nine_beyblades(): void
    {
        $team = Team::create(['name' => 'T']);
        $captain = $team->members()->create(['role' => 'captain', 'name' => 'A']);
        $team->members()->create(['role' => 'subcaptain', 'name' => 'B']);
        $team->members()->create(['role' => 'official', 'name' => 'C']);

        $this->assertSame(0, $team->beybladesCount());
        $this->assertFalse($team->isComplete());

        foreach ($team->members as $member) {
            for ($i = 1; $i <= 3; $i++) {
                $member->beyblades()->create(['line' => 'bx', 'position' => $i]);
            }
        }

        $this->assertSame(9, $team->fresh()->beybladesCount());
        $this->assertTrue($team->fresh()->isComplete());
    }
}
