<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlayerWithTournamentDeck(): void
    {
        $user = User::factory()->create(['name' => 'Ricardo', 'nickname' => 'ricardox']);
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $beyblade = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);
    }

    public function test_downloads_pdf_with_correct_password(): void
    {
        config(['report.password' => 'secret123']);
        $this->seedPlayerWithTournamentDeck();

        $response = $this->postJson('/report/players/pdf', ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_rejects_wrong_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/players/pdf', ['password' => 'nope'])->assertForbidden();
    }

    public function test_requires_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/players/pdf', [])->assertStatus(422);
    }

    public function test_rejects_when_no_password_configured(): void
    {
        config(['report.password' => null]);

        $this->postJson('/report/players/pdf', ['password' => 'anything'])->assertForbidden();
    }
}
