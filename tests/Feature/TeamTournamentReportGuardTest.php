<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentReportGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_players_pdf_is_unavailable_for_team_tournaments(): void
    {
        config(['report.password' => 'secret']);

        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams',
            'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);
        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => Team::create(['name' => 'Fénix'])->id]);

        $this->actingAs($admin)
            ->post("/tournament/{$tournament->id}/report/pdf", ['password' => 'secret'])
            ->assertStatus(422);
    }
}
