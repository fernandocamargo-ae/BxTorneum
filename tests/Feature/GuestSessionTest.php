<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_guest_user_logs_them_in_and_redirects_to_create_a_deck(): void
    {
        $response = $this->post('/play-as-guest', ['nickname' => 'PandaKid']);

        $response->assertRedirect('/decks/create');
        $this->assertAuthenticated();

        $guest = User::where('nickname', 'PandaKid')->first();
        $this->assertNotNull($guest);
        $this->assertTrue($guest->is_guest);
        $this->assertFalse($guest->is_admin);
    }

    public function test_nickname_may_contain_spaces(): void
    {
        $response = $this->post('/play-as-guest', ['nickname' => 'Don Gsan']);

        $response->assertRedirect('/decks/create');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['nickname' => 'Don Gsan', 'is_guest' => true]);
    }

    public function test_each_guest_gets_a_unique_synthetic_email(): void
    {
        $this->post('/play-as-guest', ['nickname' => 'Kid1']);
        auth()->logout();

        $this->post('/play-as-guest', ['nickname' => 'Kid2']);

        $emails = User::whereIn('nickname', ['Kid1', 'Kid2'])->pluck('email');
        $this->assertCount(2, $emails->unique());
    }

    public function test_nickname_must_be_unique(): void
    {
        User::factory()->create(['nickname' => 'Taken']);

        $this->post('/play-as-guest', ['nickname' => 'Taken'])
            ->assertSessionHasErrors('nickname');

        $this->assertGuest();
    }

    public function test_nickname_is_required(): void
    {
        $this->post('/play-as-guest', ['nickname' => ''])
            ->assertSessionHasErrors('nickname');
    }

    public function test_a_logged_in_user_cannot_reach_the_guest_flow(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/play-as-guest')
            ->assertRedirect('/');
    }

    public function test_a_guest_participant_appears_in_the_players_pdf_report_like_any_other_player(): void
    {
        $this->post('/play-as-guest', ['nickname' => 'ReportKid']);
        $guest = User::where('nickname', 'ReportKid')->first();
        $guest->decks()->create(['name' => 'Deck de torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->where('players.0.nickname', 'ReportKid')
        );
    }
}
