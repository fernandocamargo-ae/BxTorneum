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
}
