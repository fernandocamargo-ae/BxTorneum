<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_a_known_email_is_sent_straight_to_the_reset_step(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect(route('password.reset', ['email' => $user->email]));
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'nadie@example.com']);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/reset-password?email='.urlencode($user->email));

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_by_email_alone(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->post('/reset-password', [
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_for_an_unknown_email(): void
    {
        $response = $this->post('/reset-password', [
            'email' => 'nadie@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
