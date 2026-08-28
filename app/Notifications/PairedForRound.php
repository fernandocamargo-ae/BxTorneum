<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PairedForRound extends Notification
{
    public function __construct(
        private readonly string $opponentNickname,
        private readonly int $roundNumber,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("BxTorneum — Ronda {$this->roundNumber}: te toca contra {$this->opponentNickname}")
            ->greeting("¡Hola {$notifiable->nickname}!")
            ->line("En la ronda {$this->roundNumber} del torneo te toca jugar contra {$this->opponentNickname}.")
            ->action('Ver torneo', '/tournament')
            ->line('¡Mucha suerte!');
    }
}
