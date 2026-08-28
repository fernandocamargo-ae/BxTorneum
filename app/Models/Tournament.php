<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $guarded = [];

    public function entries()
    {
        return $this->hasMany(TournamentEntry::class);
    }

    public function rounds()
    {
        return $this->hasMany(TournamentRound::class);
    }

    public function champion()
    {
        return $this->belongsTo(TournamentEntry::class, 'champion_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
