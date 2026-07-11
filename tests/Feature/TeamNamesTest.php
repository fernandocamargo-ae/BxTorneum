<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamNamesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'Xplosivos',
            'members' => [
                ['role' => 'captain', 'name' => 'Ricardo'],
                ['role' => 'subcaptain', 'name' => 'Cielo azul'],
                ['role' => 'official', 'name' => 'Kristen'],
            ],
        ];
    }

    public function test_registers_team_with_names_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/teams', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('teams', ['name' => 'Xplosivos']);
        $this->assertDatabaseCount('members', 3);
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_rejects_missing_member_name(): void
    {
        $payload = $this->payload();
        $payload['members'][0]['name'] = '';

        $this->actingAs(User::factory()->create())
            ->post('/teams', $payload)->assertSessionHasErrors('members.0.name');
    }

    public function test_updates_member_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/teams', $this->payload());
        $team = Team::first();

        $payload = $this->payload();
        $payload['name'] = 'Xplosivos 2';
        $payload['members'][0]['name'] = 'Ricardo R.';

        $this->actingAs($user)
            ->put("/teams/{$team->id}", $payload)->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Xplosivos 2']);
        $this->assertDatabaseHas('members', ['name' => 'Ricardo R.', 'role' => 'captain']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/teams')->assertRedirect('/login');
        $this->post('/teams', $this->payload())->assertRedirect('/login');
    }
}
