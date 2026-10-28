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

    private function userWithDeck(): User
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $user;
    }

    private function teamWith(array $users): Team
    {
        $team = Team::create(['name' => 'T', 'created_by_user_id' => $users[0]->id]);
        $roles = ['captain', 'subcaptain', 'official'];
        foreach ($users as $i => $user) {
            $team->members()->create(['user_id' => $user->id, 'role' => $roles[$i]]);
        }

        return $team;
    }

    public function test_has_room_until_three_members(): void
    {
        $team = $this->teamWith([User::factory()->create()]);
        $this->assertTrue($team->hasRoom());

        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'subcaptain']);
        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'official']);
        $this->assertFalse($team->fresh()->hasRoom());
    }

    public function test_first_open_role_fills_subcaptain_then_official(): void
    {
        $team = $this->teamWith([User::factory()->create()]);
        $this->assertSame('subcaptain', $team->firstOpenRole());

        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'subcaptain']);
        $this->assertSame('official', $team->fresh()->firstOpenRole());

        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'official']);
        $this->assertNull($team->fresh()->firstOpenRole());
    }

    public function test_tournament_readiness_needs_three_members_each_with_a_tournament_deck(): void
    {
        $this->assertFalse($this->teamWith([$this->userWithDeck(), $this->userWithDeck()])->isTournamentReady());
        $this->assertFalse($this->teamWith([$this->userWithDeck(), $this->userWithDeck(), User::factory()->create()])->isTournamentReady());
        $this->assertTrue($this->teamWith([$this->userWithDeck(), $this->userWithDeck(), $this->userWithDeck()])->isTournamentReady());
    }

    public function test_promoting_oldest_moves_captaincy_to_the_earliest_remaining_member(): void
    {
        $subUser = User::factory()->create();
        $team = $this->teamWith([User::factory()->create(), $subUser, User::factory()->create()]);

        $team->members()->where('role', 'captain')->delete();
        $team->promoteOldestToCaptain();

        $this->assertTrue($team->fresh()->captain()->is($subUser));
    }
}
