<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Support\TournamentRoundFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TournamentRoundFactoryTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_round_for_team_entries_does_not_require_a_user(): void
    {
        $creator = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'Equipos',
            'type' => 'official',
            'format' => 'teams',
            'status' => 'registration',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => $creator->id,
        ]);

        $entryOne = TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => Team::create(['name' => 'A'])->id]);
        $entryTwo = TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => Team::create(['name' => 'B'])->id]);

        Log::spy();

        $round = TournamentRoundFactory::create($tournament, 'swiss', [[$entryOne->id, $entryTwo->id]]);

        $this->assertCount(1, $round->matches);
        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'status' => 'swiss',
            'current_round' => 1,
        ]);

        // Teams have no user account; pairing must not fall into the notifier's
        // null-user path (which logs a warning).
        Log::shouldNotHaveReceived('warning');
    }
}
