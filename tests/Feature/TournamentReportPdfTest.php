<?php

namespace Tests\Feature;

use App\Http\Controllers\TournamentReportController;
use App\Models\Part;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function joinedEntry(Tournament $tournament, string $name, string $nickname): \App\Models\TournamentEntry
    {
        $user = User::factory()->create(['name' => $name, 'nickname' => $nickname]);
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $beyblade = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    private function viewer(): User
    {
        return User::factory()->create();
    }

    public function test_downloads_pdf_with_correct_password(): void
    {
        config(['report.password' => 'secret123']);
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->joinedEntry($tournament, 'Ricardo', 'ricardox');

        $response = $this->actingAs($this->viewer())
            ->postJson('/tournament/report/pdf', ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_only_includes_players_registered_in_the_tournament(): void
    {
        // The PDF binary itself isn't reliably text-searchable (dompdf compresses streams
        // and embeds subset fonts with custom glyph encodings), so this asserts on the data
        // handed to the PDF renderer directly, rather than parsing the rendered bytes.
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->joinedEntry($tournament, 'Ricardo', 'ricardox');

        // Has a tournament deck marked but never joined this tournament — must NOT appear.
        $outsider = User::factory()->create(['nickname' => 'notinthistournament']);
        $outsider->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $players = (new TournamentReportController)->playersFor($tournament);

        $this->assertCount(1, $players);
        $this->assertSame('ricardox', $players[0]['nickname']);
    }

    public function test_rejects_wrong_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->actingAs($this->viewer())
            ->postJson('/tournament/report/pdf', ['password' => 'nope'])->assertForbidden();
    }

    public function test_requires_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->actingAs($this->viewer())
            ->postJson('/tournament/report/pdf', [])->assertStatus(422);
    }

    public function test_rejects_when_no_password_configured(): void
    {
        config(['report.password' => null]);

        $this->actingAs($this->viewer())
            ->postJson('/tournament/report/pdf', ['password' => 'anything'])->assertForbidden();
    }

    public function test_404s_when_there_is_no_tournament(): void
    {
        config(['report.password' => 'secret123']);

        $this->actingAs($this->viewer())
            ->postJson('/tournament/report/pdf', ['password' => 'secret123'])->assertNotFound();
    }

    public function test_guests_are_unauthorized(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/tournament/report/pdf', ['password' => 'secret123'])->assertUnauthorized();
    }
}
