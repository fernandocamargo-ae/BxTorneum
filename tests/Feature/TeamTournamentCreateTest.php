<?php

namespace Tests\Feature;

use App\Models\Tournament;
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
}
