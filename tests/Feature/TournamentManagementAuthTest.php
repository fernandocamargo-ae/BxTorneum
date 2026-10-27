<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentMatch;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function amateur(): User
    {
        return User::factory()->create(['is_admin' => false, 'is_guest' => false]);
    }

    private function official(string $status = 'registration'): Tournament
    {
        return Tournament::create([
            'name' => 'Oficial', 'type' => 'official', 'status' => $status,
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => $status === 'registration' ? 0 : 1,
            'created_by_user_id' => $this->admin()->id,
        ]);
    }

    private function entryIn(Tournament $tournament): TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    private function tournamentDeck(User $user): Deck
    {
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $deck;
    }

    public function test_admin_can_create_official_non_admin_cannot(): void
    {
        $payload = ['name' => 'Copa', 'swiss_rounds' => 3, 'cut_size' => 4];

        $this->actingAs($this->amateur())->post('/tournament', $payload)->assertForbidden();
        $this->actingAs($this->admin())->post('/tournament', $payload)->assertRedirect();
        $this->assertDatabaseHas('tournaments', ['name' => 'Copa', 'type' => 'official']);
    }

    public function test_non_admin_cannot_manage_an_official_tournament(): void
    {
        $tournament = $this->official();
        $entry = $this->entryIn($tournament);
        $amateur = $this->amateur();

        $this->actingAs($amateur)->patch("/tournament/{$tournament->id}", ['name' => 'X', 'swiss_rounds' => 3, 'cut_size' => 4])->assertForbidden();
        $this->actingAs($amateur)->delete("/tournament/{$tournament->id}/entries/{$entry->id}")->assertForbidden();
        $this->actingAs($amateur)->post("/tournament/{$tournament->id}/rounds")->assertForbidden();
        $this->actingAs($amateur)->post("/tournament/{$tournament->id}/cut")->assertForbidden();
    }

    public function test_admin_can_manage_an_official_tournament(): void
    {
        $tournament = $this->official();
        $this->entryIn($tournament);
        $this->entryIn($tournament);

        $this->actingAs($this->admin())->post("/tournament/{$tournament->id}/rounds")->assertRedirect();
        $this->assertSame('swiss', $tournament->fresh()->status);
    }

    public function test_non_admin_cannot_report_a_result_in_an_official_tournament(): void
    {
        $tournament = $this->official('swiss');
        $round = TournamentRound::create(['tournament_id' => $tournament->id, 'number' => 1, 'phase' => 'swiss']);
        $e1 = $this->entryIn($tournament);
        $e2 = $this->entryIn($tournament);
        $match = TournamentMatch::create([
            'tournament_round_id' => $round->id, 'entry_one_id' => $e1->id, 'entry_two_id' => $e2->id, 'is_bye' => false,
        ]);

        $this->actingAs($this->amateur())
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $e1->id])
            ->assertForbidden();
    }

    public function test_any_signed_in_user_can_join_and_leave(): void
    {
        $tournament = $this->official();
        $user = $this->amateur();
        $this->tournamentDeck($user);

        $this->actingAs($user)->post("/tournament/{$tournament->id}/join")->assertRedirect();
        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $user->id]);

        $this->actingAs($user)->delete("/tournament/{$tournament->id}/leave")->assertRedirect();
        $this->assertDatabaseMissing('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $user->id]);
    }

    public function test_report_pdf_is_per_tournament(): void
    {
        config(['report.password' => 'secret123']);
        $tournament = $this->official();
        $this->entryIn($tournament);

        $response = $this->actingAs($this->amateur())
            ->postJson("/tournament/{$tournament->id}/report/pdf", ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
