<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_member_belongs_to_a_user_with_a_role(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'Dragones', 'created_by_user_id' => $user->id]);

        $member = $team->members()->create(['user_id' => $user->id, 'role' => 'captain']);

        $this->assertTrue($member->user->is($user));
        $this->assertTrue($member->team->is($team));
        $this->assertSame('captain', $member->role);
        $this->assertTrue($team->captain()->is($user));
    }

    public function test_a_user_can_only_belong_to_one_team(): void
    {
        $user = User::factory()->create();
        $teamA = Team::create(['name' => 'A', 'created_by_user_id' => $user->id]);
        $teamB = Team::create(['name' => 'B', 'created_by_user_id' => $user->id]);

        $teamA->members()->create(['user_id' => $user->id, 'role' => 'captain']);

        $this->expectException(QueryException::class);
        $teamB->members()->create(['user_id' => $user->id, 'role' => 'captain']);
    }
}
