<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentCreateTest extends TestCase
{
    use RefreshDatabase;

    private function tournamentAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'X',
            'type' => 'official',
            'status' => 'registration',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => User::factory()->create()->id,
        ], $overrides);
    }

    public function test_tournaments_default_to_individual_format_and_report_it(): void
    {
        $individual = Tournament::create($this->tournamentAttributes());
        $this->assertSame('individual', $individual->refresh()->format);
        $this->assertFalse($individual->isTeams());

        $teams = Tournament::create($this->tournamentAttributes(['format' => 'teams']));
        $this->assertTrue($teams->isTeams());
    }

    public function test_a_tournament_entry_can_reference_a_team_without_user_or_deck(): void
    {
        $team = Team::create(['name' => 'Equipo E']);
        $tournament = Tournament::create($this->tournamentAttributes(['format' => 'teams']));

        $entry = TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
        ]);

        $this->assertNull($entry->user_id);
        $this->assertNull($entry->deck_id);
        $this->assertTrue($entry->team->is($team));
    }
}
