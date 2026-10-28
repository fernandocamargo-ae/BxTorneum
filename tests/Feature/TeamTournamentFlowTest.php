<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function completeTeam(string $name): Team
    {
        $team = Team::create(['name' => $name]);
        foreach (['captain', 'subcaptain', 'official'] as $role) {
            $member = $team->members()->create(['role' => $role, 'name' => $role]);
            for ($position = 1; $position <= 3; $position++) {
                $member->beyblades()->create(['line' => 'bx', 'position' => $position]);
            }
        }

        return $team;
    }

    private function reportCurrentRound(User $admin, Tournament $tournament): void
    {
        $tournament->refresh();
        $round = $tournament->rounds()->where('number', $tournament->current_round)->first();

        foreach ($round->matches()->whereNull('winner_entry_id')->get() as $match) {
            $this->actingAs($admin)
                ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                ->assertRedirect();
        }
    }

    public function test_a_team_tournament_runs_end_to_end(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams',
            'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);

        // Odd field (5) so swiss round 1 produces a bye handled without a user.
        foreach (range(1, 5) as $i) {
            TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $this->completeTeam("Equipo $i")->id]);
        }

        // Swiss rounds
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $this->reportCurrentRound($admin, $tournament);
        $this->assertSame('swiss', $tournament->fresh()->status);

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $this->reportCurrentRound($admin, $tournament);

        // Cut: the redirect must land on the team page, not the individual one.
        $this->actingAs($admin)
            ->post("/tournament/{$tournament->id}/cut")
            ->assertRedirect(route('team-tournament.show'));
        $this->assertSame('elimination', $tournament->fresh()->status);

        // Play elimination to the final.
        $guard = 0;
        while ($tournament->fresh()->status !== 'completed' && $guard++ < 6) {
            $this->reportCurrentRound($admin, $tournament);
            if ($tournament->fresh()->status === 'completed') {
                break;
            }
            $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        }

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertNotNull($tournament->champion_entry_id);
        $this->assertNotNull($tournament->champion->team);
    }
}
