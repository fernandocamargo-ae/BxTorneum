<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentRound extends Model
{
    protected $guarded = [];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }
}
