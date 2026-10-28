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

    public function test_an_admin_creates_an_official_team_tournament(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/torneo-equipos', ['name' => 'Copa', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['name' => 'Copa', 'type' => 'official', 'format' => 'teams']);
    }

    public function test_an_amateur_creates_a_clandestine_team_tournament(): void
    {
        $amateur = User::factory()->create(['is_admin' => false, 'is_amateur' => true, 'is_guest' => false]);

        $this->actingAs($amateur)
            ->post('/torneo-equipos/clandestinos', ['name' => 'Under', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['name' => 'Under', 'type' => 'clandestine', 'format' => 'teams']);
    }

    public function test_a_plain_player_cannot_create_team_tournaments(): void
    {
        $jugador = User::factory()->create(['is_admin' => false, 'is_amateur' => false, 'is_guest' => false]);

        $this->actingAs($jugador)->post('/torneo-equipos', ['name' => 'X', 'swiss_rounds' => 3, 'cut_size' => 4])->assertForbidden();
        $this->actingAs($jugador)->post('/torneo-equipos/clandestinos', ['name' => 'X', 'swiss_rounds' => 3, 'cut_size' => 4])->assertForbidden();
    }

    public function test_an_active_team_official_does_not_block_an_individual_official(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tournament::create($this->tournamentAttributes(['name' => 'TeamOff', 'format' => 'teams']));

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'IndOff', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['name' => 'IndOff', 'type' => 'official', 'format' => 'individual']);
    }

    public function test_an_active_individual_official_does_not_block_a_team_official(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tournament::create($this->tournamentAttributes(['name' => 'IndOff', 'format' => 'individual']));

        $this->actingAs($admin)
            ->post('/torneo-equipos', ['name' => 'TeamOff', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['name' => 'TeamOff', 'type' => 'official', 'format' => 'teams']);
    }
}
