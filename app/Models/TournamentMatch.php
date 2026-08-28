<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_bye' => 'boolean',
        ];
    }

    public function round()
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function entryOne()
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_one_id');
    }

    public function entryTwo()
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_two_id');
    }

    public function winner()
    {
        return $this->belongsTo(TournamentEntry::class, 'winner_entry_id');
    }
}
