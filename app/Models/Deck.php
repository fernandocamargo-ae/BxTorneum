<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deck extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_tournament_deck' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deckBeyblades()
    {
        return $this->hasMany(DeckBeyblade::class);
    }

    public function tournamentEntries()
    {
        return $this->hasMany(TournamentEntry::class);
    }
}
