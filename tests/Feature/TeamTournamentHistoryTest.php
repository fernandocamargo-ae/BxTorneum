<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(array $attributes = []): Tournament
    {
        return Tournament::create([
            'name' => 'Copa de Equipos', 'format' => 'teams', 'type' => 'official', 'status' => 'completed',
            'swiss_rounds' => 1, 'cut_size' => 2, 'current_round' => 2,
            'created_by_user_id' => User::factory()->create()->id, ...$attributes,
        ]);
    }

    private function enroll(Tournament $tournament, string $name): TournamentEntry
    {
        $team = Team::create(['name' => $name, 'created_by_user_id' => User::factory()->create()->id]);

        return $tournament->entries()->create(['team_id' => $team->id]);
    }

    /** Un torneo por equipos terminado: suiza, semifinal con bye y final. */
    private function playedTournament(): array
    {
        $tournament = $this->tournament();
        [$a, $b, $c] = [$this->enroll($tournament, 'Bfury Galaxy'), $this->enroll($tournament, 'Dragones'), $this->enroll($tournament, 'Fénix')];

        $swiss = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $swiss->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $swiss->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => null, 'winner_entry_id' => $c->id, 'is_bye' => true]);
        $final = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $final->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $c->id, 'winner_entry_id' => $a->id]);
        $tournament->update(['champion_entry_id' => $a->id]);

        return [$tournament, $a, $b, $c];
    }

    public function test_the_history_lists_finished_team_tournaments_with_the_champion_team(): void
    {
        $this->playedTournament();

        $this->actingAs(User::factory()->create())->get('/tournament/history')->assertInertia(fn ($page) => $page
            ->component('Tournament/History')
            ->has('tournaments', 1)
            ->where('tournaments.0.name', 'Copa de Equipos')
            ->where('tournaments.0.format', 'teams')
            ->where('tournaments.0.champion_nickname', 'Bfury Galaxy')
            ->where('tournaments.0.entries_count', 3));
    }

    public function test_team_and_individual_tournaments_share_the_list_newest_first(): void
    {
        $old = $this->tournament(['name' => 'Individual viejo', 'format' => 'individual']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->tournament(['name' => 'Equipos nuevo']);

        $this->actingAs(User::factory()->create())->get('/tournament/history')->assertInertia(fn ($page) => $page
            ->has('tournaments', 2)
            ->where('tournaments.0.name', 'Equipos nuevo')
            ->where('tournaments.1.name', 'Individual viejo')
            ->where('tournaments.1.format', 'individual'));
    }

    public function test_unfinished_and_clandestine_team_tournaments_stay_out(): void
    {
        $this->tournament(['status' => 'elimination']);
        $this->tournament(['type' => 'clandestine']);

        $this->actingAs(User::factory()->create())->get('/tournament/history')->assertInertia(fn ($page) => $page->has('tournaments', 0));
    }

    public function test_the_detail_shows_teams_standings_rounds_and_bracket(): void
    {
        [$tournament] = $this->playedTournament();

        $this->actingAs(User::factory()->create())->get("/tournament/history/{$tournament->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tournament/HistoryShow')
            ->where('tournament.format', 'teams')
            ->where('tournament.champion_nickname', 'Bfury Galaxy')
            ->has('standings', 3)
            ->where('standings.0.nickname', 'Bfury Galaxy')
            ->has('rounds', 2)
            ->where('rounds.0.matches.0.entry_one_nickname', 'Bfury Galaxy')
            ->where('rounds.0.matches.0.entry_two_nickname', 'Dragones')
            ->where('rounds.0.matches.1.is_bye', true)
            ->where('rounds.0.matches.1.entry_two_nickname', null)
            ->has('bracket', 1)
            ->where('bracket.0.title', 'Final')
            ->where('bracket.0.matches.0.entry_two_nickname', 'Fénix'));
    }

    public function test_the_detail_works_when_the_teams_were_emptied_afterwards(): void
    {
        [$tournament] = $this->playedTournament();
        Team::query()->each(fn (Team $team) => $team->members()->delete());

        $this->actingAs(User::factory()->create())->get("/tournament/history/{$tournament->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('tournament.champion_nickname', 'Bfury Galaxy'));
    }

    public function test_individual_tournaments_keep_their_detail_without_bracket(): void
    {
        $tournament = $this->tournament(['format' => 'individual']);
        $user = User::factory()->create(['nickname' => 'Pipo']);
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private']);
        $entry = $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
        $tournament->update(['champion_entry_id' => $entry->id]);

        $this->actingAs($user)->get("/tournament/history/{$tournament->id}")->assertInertia(fn ($page) => $page
            ->where('tournament.format', 'individual')
            ->where('tournament.champion_nickname', 'Pipo')
            ->where('bracket', []));
    }
}
