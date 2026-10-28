<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
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

    /** Build a PUT payload: captain gets $captainDecks, others get $others (default empty). */
    private function payload(Team $team, array $captainDecks, array $others = []): array
    {
        $members = $team->members->sortBy('id')->values();
        $blank = ['line' => 'bx', 'parts' => []];
        $others = $others ?: [$blank, $blank, $blank];

        return [
            'members' => [
                ['id' => $members[0]->id, 'beyblades' => $captainDecks],
                ['id' => $members[1]->id, 'beyblades' => $others],
                ['id' => $members[2]->id, 'beyblades' => $others],
            ],
        ];
    }

    private function bx(string $blade, string $ratchet = '3-60', string $bit = 'Flat'): array
    {
        return ['line' => 'bx', 'parts' => ['blade' => $blade, 'ratchet' => $ratchet, 'bit' => $bit]];
    }

    private function asUser()
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_registers_first_combo(): void
    {
        $team = $this->team();

        $this->asUser()
            ->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Dran Sword')]))
            ->assertRedirect();

        $this->assertDatabaseCount('beyblades', 1);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
    }

    public function test_existing_combo_is_immutable_and_new_one_is_appended(): void
    {
        $team = $this->team();
        $user = User::factory()->create();
        // Register combo 1 = "Aero" at position 1.
        $this->actingAs($user)->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Aero')]));
        $captain = $team->members->firstWhere('role', 'captain');
        $this->assertSame(1, $captain->beyblades()->count());

        // Second submit registers a new combo "Dran" — must NOT touch the existing one.
        $this->actingAs($user)->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Dran')]));

        $captain->refresh();
        $this->assertSame(2, $captain->beyblades()->count());
        $pos1 = $captain->beyblades()->where('position', 1)->first();
        $pos2 = $captain->beyblades()->where('position', 2)->first();
        $this->assertSame('Aero', $pos1->partsBySlot()['blade']);   // unchanged / locked
        $this->assertSame('Dran', $pos2->partsBySlot()['blade']);   // appended
    }

    public function test_rejects_half_filled_combo(): void
    {
        $team = $this->team();
        $payload = $this->payload($team, [['line' => 'bx', 'parts' => ['blade' => 'Only blade']]]);

        $this->asUser()
            ->put("/teams/{$team->id}/combos", $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_empty_combos_without_line_are_ignored(): void
    {
        $team = $this->team();
        $payload = $this->payload($team, [['parts' => []], ['parts' => []], ['parts' => []]]);

        $this->asUser()
            ->put("/teams/{$team->id}/combos", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_rejects_exceeding_three_per_member(): void
    {
        $team = $this->team();
        $user = User::factory()->create();
        // Pre-register 2 combos for the captain.
        $this->actingAs($user)->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('A')]));
        $this->actingAs($user)->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('B')]));
        $captain = $team->members->firstWhere('role', 'captain');
        $this->assertSame(2, $captain->beyblades()->count());

        // Now submit 2 more new combos => existing(2)+new(2)=4 > 3 => rejected, no change.
        $this->actingAs($user)
            ->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('C'), $this->bx('D')]))
            ->assertSessionHasErrors('members.0.beyblades');
        $this->assertSame(2, $captain->fresh()->beyblades()->count());
    }

    public function test_deleting_team_clears_beyblades(): void
    {
        $team = $this->team();
        $user = User::factory()->create();
        $this->actingAs($user)->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('A')]));

        $this->actingAs($user)->delete("/teams/{$team->id}")->assertRedirect('/teams');
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $team = $this->team();

        $this->get("/teams/{$team->id}/combos")->assertRedirect('/login');
    }
}
