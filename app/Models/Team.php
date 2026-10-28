<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function beybladesCount(): int
    {
        return $this->members()->withCount('beyblades')->get()->sum('beyblades_count');
    }

    public function isComplete(): bool
    {
        return $this->beybladesCount() === 9;
    }

    /** True once the team has entered any tournament — its brackets reference it. */
    public function hasAnyTournamentEntry(): bool
    {
        return TournamentEntry::where('team_id', $this->id)->exists();
    }

    /** True while the team is enrolled in a tournament that hasn't finished yet. */
    public function isLockedByActiveTournament(): bool
    {
        return TournamentEntry::where('team_id', $this->id)
            ->whereHas('tournament', fn ($query) => $query->where('status', '!=', 'completed'))
            ->exists();
    }
}
