<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PairedForRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PairedForRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_a_mail_notification_naming_the_opponent_and_round(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->notify(new PairedForRound('RivalNick', 2));

        Notification::assertSentTo($user, PairedForRound::class, function (PairedForRound $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->subject, 'Ronda 2')
                && str_contains(implode(' ', $mail->introLines), 'RivalNick')
                && $mail->actionUrl === route('tournament.show');
        });
    }
}
