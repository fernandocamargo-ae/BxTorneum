<?php

namespace Tests\Feature;

use App\Models\Team;
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
}
