<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        $deck = fn () => [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'cx', 'parts' => ['lock_chip' => 'C', 'main_blade' => 'M', 'assist_blade' => 'A', 'ratchet' => '4-80', 'bit' => 'Ball']],
            ['line' => 'bx_infinity', 'parts' => ['blade' => 'Hells', 'bit' => 'Point']],
        ];

        return [
            'name' => 'Storm Riders',
            'members' => [
                ['role' => 'captain', 'name' => 'Aoi', 'beyblades' => $deck()],
                ['role' => 'subcaptain', 'name' => 'Multi', 'beyblades' => $deck()],
                ['role' => 'official', 'name' => 'Kazami', 'beyblades' => $deck()],
            ],
        ];
    }

    public function test_rejects_team_with_missing_members(): void
    {
        $payload = $this->validPayload();
        $payload['members'] = array_slice($payload['members'], 0, 2);

        $this->post('/teams', $payload)->assertSessionHasErrors('members');
    }

    public function test_rejects_beyblade_missing_required_slot(): void
    {
        $payload = $this->validPayload();
        unset($payload['members'][0]['beyblades'][0]['parts']['ratchet']); // bx requires ratchet

        $this->post('/teams', $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
    }

    public function test_accepts_valid_team(): void
    {
        $this->post('/teams', $this->validPayload())->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Storm Riders']);
    }

    public function test_persists_nested_team_and_shows_it(): void
    {
        $this->withoutVite();
        $this->post('/teams', $this->validPayload())->assertRedirect();

        $this->assertDatabaseHas('members', ['role' => 'captain', 'name' => 'Aoi']);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
        $this->assertDatabaseCount('beyblades', 9);

        $teamId = Team::first()->id;
        $this->get("/teams/{$teamId}")->assertOk();
    }

    public function test_deletes_team_and_cascades(): void
    {
        $this->post('/teams', $this->validPayload());
        $teamId = Team::first()->id;

        $this->delete("/teams/{$teamId}")->assertRedirect('/teams');
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('beyblades', 0);
    }
}
