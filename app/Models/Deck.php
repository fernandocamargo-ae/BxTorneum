<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
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
}
