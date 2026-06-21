<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamComboTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        $team = Team::create(['name' => 'Xplosivos']);
        foreach ([['captain', 'Ricardo'], ['subcaptain', 'Cielo'], ['official', 'Kristen']] as [$role, $name]) {
            $team->members()->create(['role' => $role, 'name' => $name]);
        }

        return $team->fresh('members');
    }

    private function comboPayload(Team $team, array $captainDecks): array
    {
        $members = $team->members->sortBy('id')->values();
        $blank = ['line' => 'bx', 'parts' => []];

        return [
            'members' => [
                ['id' => $members[0]->id, 'beyblades' => $captainDecks],
                ['id' => $members[1]->id, 'beyblades' => [$blank, $blank, $blank]],
                ['id' => $members[2]->id, 'beyblades' => [$blank, $blank, $blank]],
            ],
        ];
    }

    public function test_saves_partial_combos_and_keeps_team_incomplete(): void
    {
        $team = $this->team();
        $payload = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'bx', 'parts' => []], // empty -> skipped
            ['line' => 'bx', 'parts' => []], // empty -> skipped
        ]);

        $this->put("/teams/{$team->id}/combos", $payload)->assertRedirect();

        $this->assertDatabaseCount('beyblades', 1);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
        $this->assertFalse($team->fresh()->isComplete());
    }

    public function test_rejects_half_filled_combo(): void
    {
        $team = $this->team();
        $payload = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword']], // missing ratchet + bit
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);

        $this->put("/teams/{$team->id}/combos", $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_update_replaces_previous_combos(): void
    {
        $team = $this->team();
        $first = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'A', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);
        $this->put("/teams/{$team->id}/combos", $first);
        $this->assertDatabaseCount('beyblades', 1);

        $second = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);
        $this->put("/teams/{$team->id}/combos", $second);
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_empty_combos_without_line_are_ignored(): void
    {
        $team = $this->team();
        $blankNoLine = ['parts' => []];
        $payload = $this->comboPayload($team, [
            $blankNoLine,
            $blankNoLine,
            $blankNoLine,
        ]);

        $this->put("/teams/{$team->id}/combos", $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('beyblades', 0);
    }
}
