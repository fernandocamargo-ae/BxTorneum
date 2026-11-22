<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pre-tournament sweep for team tournaments: every phase, cut size, odd fields,
 * admin mistakes, permissions and roster locks — driven through the same
 * endpoints the admin clicks.
 */
class TeamTournamentScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function player(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_admin' => false, 'is_amateur' => false, 'is_guest' => false], $attributes));
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $user;
    }

    private function readyTeam(string $name): Team
    {
        $captain = $this->player();
        $team = Team::create(['name' => $name, 'created_by_user_id' => $captain->id]);
        $team->members()->create(['user_id' => $captain->id, 'role' => 'captain']);
        $team->members()->create(['user_id' => $this->player()->id, 'role' => 'subcaptain']);
        $team->members()->create(['user_id' => $this->player()->id, 'role' => 'official']);

        return $team;
    }

    /** Create a team tournament over HTTP and enroll $count ready teams through the add-team endpoint. */
    private function tournamentWithTeams(User $manager, int $count, int $swissRounds, int $cut, string $type = 'official'): Tournament
    {
        $url = $type === 'official' ? '/torneo-equipos' : '/torneo-equipos/clandestinos';
        $this->actingAs($manager)
            ->post($url, ['name' => "Copa {$count}", 'swiss_rounds' => $swissRounds, 'cut_size' => $cut])
            ->assertRedirect();

        $tournament = Tournament::where('format', 'teams')->latest('id')->first();

        foreach (range(1, $count) as $i) {
            $team = $this->readyTeam("Equipo {$tournament->id}-{$i}");
            $this->actingAs($manager)
                ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $team->id])
                ->assertRedirect();
        }

        return $tournament->fresh();
    }

    private function currentRound(Tournament $tournament)
    {
        $tournament->refresh();

        return $tournament->rounds()->where('number', $tournament->current_round)->first();
    }

    private function reportAll(User $manager, Tournament $tournament): void
    {
        foreach ($this->currentRound($tournament)->matches()->whereNull('winner_entry_id')->get() as $match) {
            $this->actingAs($manager)
                ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                ->assertRedirect();
        }
    }

    /** Drive a tournament from registration to its champion, exactly like the admin clicks. */
    private function playToTheEnd(User $manager, Tournament $tournament): void
    {
        $this->actingAs($manager)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $this->reportAll($manager, $tournament);

        while ($tournament->fresh()->current_round < $tournament->swiss_rounds) {
            $this->actingAs($manager)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
            $this->reportAll($manager, $tournament);
        }

        $this->actingAs($manager)->post("/tournament/{$tournament->id}/cut")->assertRedirect();

        for ($guard = 0; $tournament->fresh()->status !== 'completed' && $guard < 6; $guard++) {
            $this->reportAll($manager, $tournament);
            if ($tournament->fresh()->status !== 'completed') {
                $this->actingAs($manager)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
            }
        }

        $this->assertSame('completed', $tournament->fresh()->status);
    }

    private function showProps(User $viewer, Tournament $tournament): array
    {
        $response = $this->actingAs($viewer)->get("/torneo-equipos/{$tournament->id}")->assertOk();

        return json_decode(json_encode($response->viewData('page')), true)['props'];
    }

    // ---------------------------------------------------------------- formats of the bracket

    public function test_top_8_cut_plays_quarterfinals_semifinal_and_final(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 9, 3, 8);

        $this->playToTheEnd($admin, $tournament);

        $props = $this->showProps($admin, $tournament);
        $this->assertSame(['Cuartos', 'Semifinal', 'Final'], array_column($props['bracket'], 'title'));
        $this->assertSame([4, 2, 1], array_map(fn ($round) => count($round['matches']), $props['bracket']));
        $this->assertNotEmpty($props['tournament']['champion_nickname']);
    }

    public function test_top_2_cut_goes_straight_to_the_final(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 4, 2, 2);

        $this->playToTheEnd($admin, $tournament);

        $this->assertSame(['Final'], array_column($this->showProps($admin, $tournament)['bracket'], 'title'));
    }

    public function test_an_odd_field_gets_one_bye_per_swiss_round_without_repeating(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 7, 3, 4);
        $byeEntries = [];

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        for ($round = 1; $round <= 3; $round++) {
            $matches = $this->currentRound($tournament)->matches;
            $byes = $matches->where('is_bye', true);

            $this->assertCount(4, $matches, "ronda {$round}");
            $this->assertCount(1, $byes, "ronda {$round}");
            $byeEntries[] = $byes->first()->entry_one_id;

            $this->reportAll($admin, $tournament);
            if ($round < 3) {
                $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
            }
        }

        $this->assertCount(3, array_unique($byeEntries));
    }

    public function test_a_small_field_with_more_rounds_than_opponents_still_finishes(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 3, 4, 2);

        $this->playToTheEnd($admin, $tournament);
    }

    // ---------------------------------------------------------------- admin mistakes

    public function test_admin_mistakes_are_rejected_cleanly(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 3, 2, 4, 'clandestine');

        // Cut while still in registration.
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut")->assertStatus(422);

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();

        // Next round while results are pending; cut before the swiss phase is done.
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertStatus(422);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut")->assertStatus(422);

        // Reporting a bye, or a winner who is not in the match.
        $bye = $this->currentRound($tournament)->matches()->where('is_bye', true)->first();
        $this->actingAs($admin)->patch("/tournament/matches/{$bye->id}", ['winner_entry_id' => $bye->entry_one_id])->assertStatus(422);

        $match = $this->currentRound($tournament)->matches()->where('is_bye', false)->first();
        $outsider = $tournament->entries()->whereNotIn('id', [$match->entry_one_id, $match->entry_two_id])->first();
        $this->actingAs($admin)->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $outsider->id])->assertStatus(422);

        $this->reportAll($admin, $tournament);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $this->reportAll($admin, $tournament);

        // Past the last swiss round, and a cut bigger than the field.
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertStatus(422);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut")->assertStatus(422);
    }

    public function test_a_round_needs_at_least_two_teams(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 1, 2, 2, 'clandestine');

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertStatus(422);
        $this->assertSame('registration', $tournament->fresh()->status);
    }

    public function test_no_round_can_follow_the_final(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2, 'clandestine');
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $this->reportAll($admin, $tournament);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut")->assertRedirect();

        // Final pending: another round makes no sense.
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertStatus(422);

        $this->reportAll($admin, $tournament);
        $this->assertSame('completed', $tournament->fresh()->status);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertStatus(422);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut")->assertStatus(422);
    }

    public function test_results_can_be_corrected_in_the_current_round_but_not_after_moving_on(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 4, 2, 2, 'clandestine');
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $match = $this->currentRound($tournament)->matches()->first();

        $this->actingAs($admin)->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id]);
        $this->actingAs($admin)->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_two_id])->assertRedirect();
        $this->assertSame($match->entry_two_id, $match->fresh()->winner_entry_id);

        $this->reportAll($admin, $tournament);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();

        $this->actingAs($admin)->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])->assertStatus(422);
    }

    public function test_correcting_the_final_updates_the_champion(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2, 'clandestine');
        $this->playToTheEnd($admin, $tournament);

        $final = $this->currentRound($tournament)->matches()->first();
        $runnerUp = $final->winner_entry_id === $final->entry_one_id ? $final->entry_two_id : $final->entry_one_id;

        $this->actingAs($admin)->patch("/tournament/matches/{$final->id}", ['winner_entry_id' => $runnerUp])->assertRedirect();
        $this->assertSame($runnerUp, $tournament->fresh()->champion_entry_id);
    }

    public function test_tournament_settings_can_be_edited_only_during_registration(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 4, 2, 2);

        $this->actingAs($admin)
            ->patch("/tournament/{$tournament->id}", ['name' => 'Copa Mañana', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();
        $this->assertSame('Copa Mañana', $tournament->fresh()->name);

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $this->actingAs($admin)
            ->patch("/tournament/{$tournament->id}", ['name' => 'Otra', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------- permissions

    public function test_only_admins_can_run_an_official_team_tournament(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 4, 1, 2);
        $intruders = [$this->player(), $this->player(['is_amateur' => true])];

        foreach ($intruders as $intruder) {
            $this->actingAs($intruder)->post("/tournament/{$tournament->id}/rounds")->assertForbidden();
            $this->actingAs($intruder)
                ->post("/torneo-equipos/{$tournament->id}/equipos", ['team_id' => $this->readyTeam("Z{$intruder->id}")->id])
                ->assertForbidden();
        }

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $match = $this->currentRound($tournament)->matches()->first();

        foreach ($intruders as $intruder) {
            $this->actingAs($intruder)
                ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                ->assertForbidden();
            $this->actingAs($intruder)->post("/tournament/{$tournament->id}/cut")->assertForbidden();
        }

        $this->assertNull($match->fresh()->winner_entry_id);
    }

    public function test_an_amateur_can_run_a_clandestine_team_tournament_end_to_end(): void
    {
        $amateur = $this->player(['is_amateur' => true]);
        $tournament = $this->tournamentWithTeams($amateur, 5, 2, 4, 'clandestine');

        $this->playToTheEnd($amateur, $tournament);
    }

    // ---------------------------------------------------------------- rosters

    public function test_rosters_are_locked_while_the_tournament_runs_and_unlocked_after(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2);
        $team = $tournament->entries()->first()->team;
        $captain = $team->captain();
        $sub = $team->members()->where('role', 'subcaptain')->first();

        // Registration already counts as "in a tournament".
        $this->actingAs($sub->user)->delete("/teams/{$team->id}/leave")->assertStatus(422);
        $this->actingAs($captain)->delete("/teams/{$team->id}/members/{$sub->id}")->assertStatus(422);
        $this->actingAs($captain)->patch("/teams/{$team->id}/members/{$sub->id}/role", ['role' => 'official'])->assertStatus(422);

        // The logo is not part of the roster: it can still change.
        $this->actingAs($sub->user)
            ->post("/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertRedirect();

        $this->playToTheEnd($admin, $tournament);

        $this->actingAs($captain)->patch("/teams/{$team->id}/members/{$sub->id}/role", ['role' => 'official'])->assertRedirect();
        $this->actingAs($sub->user)->delete("/teams/{$team->id}/leave")->assertRedirect();
    }

    public function test_a_team_that_played_can_be_emptied_afterwards_without_breaking_its_history(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2);
        $this->playToTheEnd($admin, $tournament);

        $team = TournamentEntry::find($tournament->fresh()->champion_entry_id)->team;
        $championName = $team->name;

        foreach (['official', 'subcaptain', 'captain'] as $role) {
            $member = $team->members()->where('role', $role)->first();
            $this->actingAs($member->user)->delete("/teams/{$team->id}/leave")->assertRedirect();
        }

        $this->assertSame($championName, $this->showProps($admin, $tournament)['tournament']['champion_nickname']);
        $this->actingAs($admin)->get('/teams')->assertInertia(fn ($page) => $page
            ->where('teams', fn ($teams) => collect($teams)->doesntContain('name', $championName)));
    }

    public function test_whoever_joins_an_emptied_team_becomes_its_captain(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2);
        $this->playToTheEnd($admin, $tournament);
        $team = $tournament->entries()->first()->team;
        foreach (['official', 'subcaptain', 'captain'] as $role) {
            $this->actingAs($team->members()->where('role', $role)->first()->user)->delete("/teams/{$team->id}/leave");
        }

        $newcomer = $this->player();
        $this->actingAs($newcomer)->post("/teams/{$team->id}/join")->assertRedirect();

        $this->assertTrue($team->fresh()->captain()->is($newcomer));
    }

    public function test_deleting_the_accounts_of_a_team_that_played_does_not_fail(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2);
        $this->playToTheEnd($admin, $tournament);

        $team = TournamentEntry::find($tournament->fresh()->champion_entry_id)->team;
        foreach ($team->members()->with('user')->get() as $member) {
            $member->user->delete();
        }

        $this->assertSame($team->name, $this->showProps($admin, $tournament)['tournament']['champion_nickname']);
    }

    // ---------------------------------------------------------------- pages

    public function test_the_page_renders_every_phase_for_admin_member_and_outsider(): void
    {
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 5, 2, 4);
        $member = $tournament->entries()->first()->team->captain();
        $outsider = $this->player();

        $check = function (string $status) use ($tournament, $admin, $member, $outsider) {
            foreach ([$admin, $member, $outsider] as $viewer) {
                $props = $this->showProps($viewer, $tournament);
                $this->assertSame($status, $props['tournament']['status']);
                $this->assertSame('teams', $props['format']);
                $this->assertSame($viewer->is($admin), $props['can_manage']);
            }
        };

        $check('registration');
        $this->actingAs($admin)->get('/torneo-equipos')->assertInertia(fn ($page) => $page->where('tournament.id', $tournament->id));

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $check('swiss');
        $this->reportAll($admin, $tournament);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $this->reportAll($admin, $tournament);

        $this->actingAs($admin)->post("/tournament/{$tournament->id}/cut");
        $check('elimination');
        $this->assertSame(['Semifinal'], array_column($this->showProps($admin, $tournament)['bracket'], 'title'));

        $this->reportAll($admin, $tournament);
        $this->actingAs($admin)->post("/tournament/{$tournament->id}/rounds");
        $this->reportAll($admin, $tournament);
        $check('completed');
        $this->assertSame(['Semifinal', 'Final'], array_column($this->showProps($outsider, $tournament)['bracket'], 'title'));
    }

    public function test_individual_and_team_tournaments_can_run_at_the_same_time(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/tournament', ['name' => 'Individual', 'swiss_rounds' => 3, 'cut_size' => 4])->assertRedirect();
        $official = $this->tournamentWithTeams($admin, 2, 1, 2);
        $clandestine = $this->tournamentWithTeams($admin, 2, 1, 2, 'clandestine');

        $this->actingAs($admin)->get('/tournament')->assertInertia(fn ($page) => $page->where('tournament.name', 'Individual'));
        $this->actingAs($admin)->get('/torneo-equipos')->assertInertia(fn ($page) => $page->where('tournament.id', $official->id));
        $this->actingAs($admin)->get('/torneo-equipos/clandestinos')->assertInertia(fn ($page) => $page->where('tournaments.0.id', $clandestine->id));

        $this->playToTheEnd($admin, $official);

        $this->actingAs($admin)->get('/tournament')->assertInertia(fn ($page) => $page->where('tournament.status', 'registration'));
        $this->assertSame('registration', $clandestine->fresh()->status);
    }

    public function test_team_logos_show_up_in_the_tournament(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $tournament = $this->tournamentWithTeams($admin, 2, 1, 2);
        foreach ($tournament->entries()->with('team')->get() as $entry) {
            $entry->team->update(['logo_path' => UploadedFile::fake()->image('logo.png')->store('team-logos', 'public')]);
        }

        $this->playToTheEnd($admin, $tournament);

        $props = $this->showProps($admin, $tournament);
        $final = $props['bracket'][0]['matches'][0];
        $this->assertNotNull($final['entry_one_avatar']);
        $this->assertNotNull($final['entry_two_avatar']);
        $this->assertCount(2, array_filter(array_column($props['standings'], 'avatar_url')));
    }
}
