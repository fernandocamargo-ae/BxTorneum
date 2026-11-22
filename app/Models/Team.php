<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    protected $guarded = [];

    /** Expose the logo URL wherever a team is serialized (list, team page, tournaments). */
    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    /** The user who is this team's captain. */
    public function captain(): ?User
    {
        return $this->members()->where('role', 'captain')->first()?->user;
    }

    public function hasRoom(): bool
    {
        return $this->members()->count() < 3;
    }

    public function memberFor(User $user): ?Member
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    /**
     * The role a joining member takes: subcaptain, then official, else null (full).
     * A team left without a captain (emptied after playing) hands it to the joiner.
     */
    public function firstOpenRole(): ?string
    {
        foreach (['captain', 'subcaptain', 'official'] as $role) {
            if (! $this->members()->where('role', $role)->exists()) {
                return $role;
            }
        }

        return null;
    }

    /** Ready to enter a team tournament: three members, each with an eligible tournament deck. */
    public function isTournamentReady(): bool
    {
        $members = $this->members()->with('user')->get();

        if ($members->count() !== 3) {
            return false;
        }

        return $members->every(fn (Member $member) => $member->user
            && $member->user->decks()->where('is_tournament_deck', true)->whereHas('deckBeyblades')->exists());
    }

    /** Hand captaincy to the earliest-joined remaining member (used when the captain leaves). */
    public function promoteOldestToCaptain(): void
    {
        $this->members()->orderBy('id')->first()?->update(['role' => 'captain']);
    }

    /**
     * Take a member out the way leaving works: the oldest member inherits the captaincy,
     * and an emptied team dissolves. A team that already played keeps its row — brackets
     * and history point at it — and just stops being listed.
     */
    public function removeMember(Member $member): void
    {
        $wasCaptain = $member->role === 'captain';
        $member->delete();

        if ($this->members()->count() === 0) {
            if (! $this->hasAnyTournamentEntry()) {
                $this->delete();
            }

            return;
        }

        if ($wasCaptain) {
            $this->promoteOldestToCaptain();
        }
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
