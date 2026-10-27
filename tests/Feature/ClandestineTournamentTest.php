<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentMatch;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClandestineTournamentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function amateur(): User
    {
        return User::factory()->create(['is_admin' => false, 'is_amateur' => true, 'is_guest' => false]);
    }

    private function entryIn(Tournament $tournament, ?User $user = null): TournamentEntry
    {
        $user ??= User::factory()->create();
        $deck = $user->decks()->create(['name' => 'D', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    private function payload(string $name = 'Reta'): array
    {
        return ['name' => $name, 'swiss_rounds' => 3, 'cut_size' => 4];
    }

    public function test_a_non_admin_can_create_a_clandestine_tournament(): void
    {
        $amateur = $this->amateur();

        $this->actingAs($amateur)->post('/tournament/clandestinos', $this->payload('Reta nocturna'))->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Reta nocturna',
            'type' => 'clandestine',
            'created_by_user_id' => $amateur->id,
        ]);
    }

    public function test_a_guest_cannot_create_a_clandestine_tournament(): void
    {
        $guest = User::factory()->create(['is_admin' => false, 'is_guest' => true]);

        $this->actingAs($guest)->post('/tournament/clandestinos', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('tournaments', 0);
    }

    public function test_several_clandestine_tournaments_can_be_active_at_once(): void
    {
        $amateur = $this->amateur();

        $this->actingAs($amateur)->post('/tournament/clandestinos', $this->payload('Uno'))->assertRedirect();
        $this->actingAs($amateur)->post('/tournament/clandestinos', $this->payload('Dos'))->assertRedirect();

        $this->assertSame(2, Tournament::where('type', 'clandestine')->where('status', '!=', 'completed')->count());
    }

    public function test_only_one_official_can_be_active_but_a_clandestine_may_run_alongside(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/tournament', $this->payload('Oficial 1'))->assertRedirect();

        // A second official is rejected while one is active.
        $this->actingAs($admin)->post('/tournament', $this->payload('Oficial 2'))->assertStatus(422);

        // A clandestine can still be created at the same time.
        $this->actingAs($this->amateur())->post('/tournament/clandestinos', $this->payload('Clandes'))->assertRedirect();

        $this->assertSame(1, Tournament::where('type', 'official')->count());
        $this->assertSame(1, Tournament::where('type', 'clandestine')->count());
    }

    public function test_a_non_admin_can_generate_a_round_in_a_clandestine_tournament(): void
    {
        $amateur = $this->amateur();
        $tournament = Tournament::create([
            'name' => 'Reta', 'type' => 'clandestine', 'status' => 'registration',
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $amateur->id,
        ]);
        $this->entryIn($tournament);
        $this->entryIn($tournament);

        $this->actingAs($amateur)->post("/tournament/{$tournament->id}/rounds")->assertRedirect();

        $this->assertSame('swiss', $tournament->fresh()->status);
    }

    public function test_completing_a_clandestine_does_not_end_a_guest_session(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        $this->seedSession($guest);

        $tournament = $this->eliminationFinal('clandestine', $guest);

        // The creator (any non-guest) reports the final; the tournament completes.
        $this->actingAs($this->amateur())
            ->patch("/tournament/matches/{$tournament->finalMatchId}", ['winner_entry_id' => $tournament->guestEntryId])
            ->assertRedirect();

        $this->assertSame('completed', Tournament::find($tournament->id)->status);
        $this->assertDatabaseHas('sessions', ['user_id' => $guest->id]);
    }

    public function test_completing_an_official_ends_a_guest_session(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        $this->seedSession($guest);

        $tournament = $this->eliminationFinal('official', $guest);

        $this->actingAs($this->admin())
            ->patch("/tournament/matches/{$tournament->finalMatchId}", ['winner_entry_id' => $tournament->guestEntryId])
            ->assertRedirect();

        $this->assertSame('completed', Tournament::find($tournament->id)->status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $guest->id]);
    }

    public function test_the_official_history_excludes_clandestine_tournaments(): void
    {
        Tournament::create(['name' => 'Oficial viejo', 'type' => 'official', 'status' => 'completed', 'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 3, 'created_by_user_id' => $this->admin()->id]);
        Tournament::create(['name' => 'Clandes viejo', 'type' => 'clandestine', 'status' => 'completed', 'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 3, 'created_by_user_id' => $this->amateur()->id]);

        $this->actingAs($this->amateur())->get('/tournament/history')->assertInertia(fn ($page) => $page
            ->component('Tournament/History')
            ->has('tournaments', 1)
            ->where('tournaments.0.name', 'Oficial viejo')
        );
    }

    private function seedSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'sess_'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
    }

    /**
     * A tournament sitting on its elimination final: a single match between the guest and
     * one opponent. Reporting that match's winner completes the tournament.
     */
    private function eliminationFinal(string $type, User $guest): object
    {
        $tournament = Tournament::create([
            'name' => 'Final', 'type' => $type, 'status' => 'elimination',
            'swiss_rounds' => 1, 'cut_size' => 2, 'current_round' => 1,
            'created_by_user_id' => $this->admin()->id,
        ]);

        $guestEntry = $this->entryIn($tournament, $guest);
        $rivalEntry = $this->entryIn($tournament);

        $round = TournamentRound::create(['tournament_id' => $tournament->id, 'number' => 1, 'phase' => 'elimination']);
        $match = TournamentMatch::create([
            'tournament_round_id' => $round->id,
            'entry_one_id' => $guestEntry->id,
            'entry_two_id' => $rivalEntry->id,
            'is_bye' => false,
        ]);

        return (object) [
            'id' => $tournament->id,
            'finalMatchId' => $match->id,
            'guestEntryId' => $guestEntry->id,
        ];
    }
}
