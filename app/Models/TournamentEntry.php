<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntry extends Model
{
    protected $guarded = [];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}
