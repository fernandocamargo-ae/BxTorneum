<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function teamOwnedBy(User $captain, string $name = 'A'): Team
    {
        $team = Team::create(['name' => $name, 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);

        return $team;
    }

    public function test_a_user_without_a_team_can_create_one_as_captain(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/teams', ['name' => 'Dragones'])->assertRedirect();

        $team = Team::where('name', 'Dragones')->first();
        $this->assertNotNull($team);
        $this->assertSame($user->id, $team->created_by_user_id);
        $this->assertTrue($team->captain()->is($user));
    }

    public function test_a_user_already_in_a_team_cannot_create_another(): void
    {
        $user = User::factory()->create();
        $this->teamOwnedBy($user);

        $this->actingAs($user)->post('/teams', ['name' => 'Otro'])->assertStatus(422);
        $this->assertDatabaseMissing('teams', ['name' => 'Otro']);
    }

    public function test_team_name_is_required(): void
    {
        $this->actingAs(User::factory()->create())->post('/teams', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_joining_fills_subcaptain_then_official(): void
    {
        $team = $this->teamOwnedBy(User::factory()->create(), 'Dragones');

        $sub = User::factory()->create();
        $this->actingAs($sub)->post("/teams/{$team->id}/join")->assertRedirect();
        $this->assertSame('subcaptain', $team->memberFor($sub)->role);

        $official = User::factory()->create();
        $this->actingAs($official)->post("/teams/{$team->id}/join")->assertRedirect();
        $this->assertSame('official', $team->fresh()->memberFor($official)->role);
    }

    public function test_a_user_already_in_a_team_cannot_join_another(): void
    {
        $user = User::factory()->create();
        $this->teamOwnedBy($user, 'Mine');
        $other = $this->teamOwnedBy(User::factory()->create(), 'Other');

        $this->actingAs($user)->post("/teams/{$other->id}/join")->assertStatus(422);
        $this->assertNull($other->fresh()->memberFor($user));
    }

    public function test_a_full_team_cannot_be_joined(): void
    {
        $team = $this->teamOwnedBy(User::factory()->create());
        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'subcaptain']);
        $team->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'official']);

        $this->actingAs(User::factory()->create())->post("/teams/{$team->id}/join")->assertStatus(422);
    }

    private function enrollInActiveTournament(Team $team): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams', 'status' => 'registration',
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);
    }

    public function test_a_non_captain_member_can_leave(): void
    {
        $captain = User::factory()->create();
        $team = $this->teamOwnedBy($captain);
        $sub = User::factory()->create();
        $team->members()->create(['user_id' => $sub->id, 'role' => 'subcaptain']);

        $this->actingAs($sub)->delete("/teams/{$team->id}/leave")->assertRedirect();

        $this->assertNull($team->fresh()->memberFor($sub));
        $this->assertTrue($team->fresh()->captain()->is($captain));
    }

    public function test_when_the_captain_leaves_the_oldest_remaining_member_becomes_captain(): void
    {
        $captain = User::factory()->create();
        $team = $this->teamOwnedBy($captain);
        $sub = User::factory()->create();
        $official = User::factory()->create();
        $team->members()->create(['user_id' => $sub->id, 'role' => 'subcaptain']);
        $team->members()->create(['user_id' => $official->id, 'role' => 'official']);

        $this->actingAs($captain)->delete("/teams/{$team->id}/leave")->assertRedirect();

        $this->assertTrue($team->fresh()->captain()->is($sub));
    }

    public function test_when_the_last_member_leaves_the_team_is_deleted(): void
    {
        $captain = User::factory()->create();
        $team = $this->teamOwnedBy($captain);

        $this->actingAs($captain)->delete("/teams/{$team->id}/leave")->assertRedirect();

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_cannot_leave_while_enrolled_in_an_active_tournament(): void
    {
        $captain = User::factory()->create();
        $team = $this->teamOwnedBy($captain);
        $this->enrollInActiveTournament($team);

        $this->actingAs($captain)->delete("/teams/{$team->id}/leave")->assertStatus(422);
        $this->assertNotNull($team->fresh()->memberFor($captain));
    }

    public function test_a_non_member_cannot_leave(): void
    {
        $team = $this->teamOwnedBy(User::factory()->create());

        $this->actingAs(User::factory()->create())->delete("/teams/{$team->id}/leave")->assertStatus(422);
    }
}
