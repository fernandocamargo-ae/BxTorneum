<?php

namespace Tests\Feature;

use App\Http\Controllers\TournamentReportController;
use App\Models\Part;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTournamentReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa', 'type' => 'official', 'format' => 'teams',
            'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    private function player(string $nickname, bool $withDeck = true): User
    {
        $user = User::factory()->create(['name' => "Nombre {$nickname}", 'nickname' => $nickname]);

        if ($withDeck) {
            // A personal deck that is not the tournament one must never show up.
            $user->decks()->create(['name' => 'Casual', 'visibility' => 'private', 'is_tournament_deck' => false])
                ->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

            $deck = $user->decks()->create(['name' => "Deck {$nickname}", 'visibility' => 'private', 'is_tournament_deck' => true]);
            foreach ([2, 1] as $position) {
                $beyblade = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => $position]);
                $blade = Part::firstOrCreate(['type' => 'blade', 'name' => "Blade {$position}"]);
                $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);
            }
        }

        return $user;
    }

    /** @param array<string, User> $members role => user */
    private function team(string $name, array $members): Team
    {
        $team = Team::create(['name' => $name]);
        foreach ($members as $role => $user) {
            $team->members()->create(['user_id' => $user->id, 'role' => $role]);
        }

        return $team;
    }

    public function test_downloads_a_pdf_for_a_team_tournament(): void
    {
        config(['report.password' => 'secret']);
        $tournament = $this->tournament();
        $tournament->entries()->create(['team_id' => $this->team('Fénix', [
            'captain' => $this->player('cap'), 'subcaptain' => $this->player('sub'), 'official' => $this->player('ofi'),
        ])->id]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson("/tournament/{$tournament->id}/report/pdf", ['password' => 'secret']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_the_password_is_still_required(): void
    {
        config(['report.password' => 'secret']);

        $this->actingAs(User::factory()->create())
            ->postJson("/tournament/{$this->tournament()->id}/report/pdf", ['password' => 'nope'])
            ->assertForbidden();
    }

    public function test_lists_each_enrolled_team_with_its_members_and_tournament_decks(): void
    {
        $tournament = $this->tournament();
        // Members stored out of order on purpose: the report goes captain, subcaptain, official.
        $tournament->entries()->create(['team_id' => $this->team('Zorros', [
            'official' => $this->player('ofi'), 'captain' => $this->player('cap'), 'subcaptain' => $this->player('sub'),
        ])->id]);
        $tournament->entries()->create(['team_id' => $this->team('Águilas', ['captain' => $this->player('agui')])->id]);
        $this->team('No inscrito', ['captain' => $this->player('fuera')]);

        $teams = (new TournamentReportController)->teamsFor($tournament);

        $this->assertEqualsCanonicalizing(['Águilas', 'Zorros'], array_column($teams, 'name'));
        $zorros = collect($teams)->firstWhere('name', 'Zorros');
        $this->assertSame(['Capitán', 'Subcapitán', 'Oficial'], array_column($zorros['members'], 'role_label'));
        $this->assertSame(['cap', 'sub', 'ofi'], array_column($zorros['members'], 'nickname'));
        $this->assertSame('Deck cap', $zorros['members'][0]['deck_name']);
        $this->assertSame(
            ['Blade 1', 'Blade 2'],
            array_map(fn ($combo) => $combo['parts'][0]['name'], $zorros['members'][0]['beyblades']),
        );
    }

    public function test_survives_members_without_a_deck_and_emptied_teams(): void
    {
        config(['report.password' => 'secret']);
        $tournament = $this->tournament();
        $tournament->entries()->create(['team_id' => $this->team('Sin deck', ['captain' => $this->player('cap', withDeck: false)])->id]);
        $tournament->entries()->create(['team_id' => $this->team('Vacío', [])->id]);

        $teams = collect((new TournamentReportController)->teamsFor($tournament))->keyBy('name');
        $this->assertNull($teams['Sin deck']['members'][0]['deck_name']);
        $this->assertSame([], $teams['Sin deck']['members'][0]['beyblades']);
        $this->assertSame([], $teams['Vacío']['members']);

        $this->actingAs(User::factory()->create())
            ->postJson("/tournament/{$tournament->id}/report/pdf", ['password' => 'secret'])
            ->assertOk();
    }
}
