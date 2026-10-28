<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCaptainManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Team,1:User,2:Member} team, captain, a subcaptain member */
    private function teamWithSub(): array
    {
        $captain = User::factory()->create();
        $team = Team::create(['name' => 'Dragones', 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);
        $sub = $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'subcaptain']);

        return [$team, $captain, $sub];
    }

    private function enrollInActiveTournament(Team $team): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams', 'status' => 'registration',
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);
    }

    public function test_a_captain_can_expel_a_member(): void
    {
        [$team, $captain, $sub] = $this->teamWithSub();

        $this->actingAs($captain)->delete("/teams/{$team->id}/members/{$sub->id}")->assertRedirect();
        $this->assertDatabaseMissing('members', ['id' => $sub->id]);
    }

    public function test_a_captain_cannot_expel_themselves(): void
    {
        [$team, $captain] = $this->teamWithSub();
        $captainMember = $team->members()->where('role', 'captain')->first();

        $this->actingAs($captain)->delete("/teams/{$team->id}/members/{$captainMember->id}")->assertStatus(422);
        $this->assertDatabaseHas('members', ['id' => $captainMember->id]);
    }

    public function test_a_non_captain_cannot_expel(): void
    {
        [$team, , $sub] = $this->teamWithSub();

        $this->actingAs($sub->user)->delete("/teams/{$team->id}/members/{$sub->id}")->assertForbidden();
    }

    public function test_cannot_expel_while_enrolled_in_an_active_tournament(): void
    {
        [$team, $captain, $sub] = $this->teamWithSub();
        $this->enrollInActiveTournament($team);

        $this->actingAs($captain)->delete("/teams/{$team->id}/members/{$sub->id}")->assertStatus(422);
        $this->assertDatabaseHas('members', ['id' => $sub->id]);
    }

    public function test_cannot_expel_a_member_of_another_team(): void
    {
        [$teamA, $captainA] = $this->teamWithSub();
        [, , $subB] = $this->teamWithSub();

        $this->actingAs($captainA)->delete("/teams/{$teamA->id}/members/{$subB->id}")->assertNotFound();
    }

    public function test_a_captain_swaps_subcaptain_and_official(): void
    {
        $captain = User::factory()->create();
        $team = Team::create(['name' => 'Dragones', 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);
        $subMember = $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'subcaptain']);
        $officialMember = $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'official']);

        $this->actingAs($captain)
            ->patch("/teams/{$team->id}/members/{$subMember->id}/role", ['role' => 'official'])
            ->assertRedirect();

        $this->assertSame('official', $subMember->fresh()->role);
        $this->assertSame('subcaptain', $officialMember->fresh()->role);
    }

    public function test_role_must_be_subcaptain_or_official(): void
    {
        [$team, $captain, $sub] = $this->teamWithSub();

        $this->actingAs($captain)
            ->patch("/teams/{$team->id}/members/{$sub->id}/role", ['role' => 'captain'])
            ->assertSessionHasErrors('role');
    }

    public function test_the_captains_role_cannot_be_changed(): void
    {
        [$team, $captain] = $this->teamWithSub();
        $captainMember = $team->members()->where('role', 'captain')->first();

        $this->actingAs($captain)
            ->patch("/teams/{$team->id}/members/{$captainMember->id}/role", ['role' => 'official'])
            ->assertStatus(422);
    }

    public function test_a_non_captain_cannot_change_roles(): void
    {
        [$team, , $sub] = $this->teamWithSub();

        $this->actingAs($sub->user)
            ->patch("/teams/{$team->id}/members/{$sub->id}/role", ['role' => 'official'])
            ->assertForbidden();
    }

    public function test_cannot_change_roles_while_enrolled_in_an_active_tournament(): void
    {
        [$team, $captain, $sub] = $this->teamWithSub();
        $this->enrollInActiveTournament($team);

        $this->actingAs($captain)
            ->patch("/teams/{$team->id}/members/{$sub->id}/role", ['role' => 'official'])
            ->assertStatus(422);
    }
}
