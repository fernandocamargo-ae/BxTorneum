<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beyblade extends Model
{
    protected $guarded = [];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function parts()
    {
        return $this->belongsToMany(Part::class)->withPivot('slot');
    }

    /** @return array<string,string> slot => part name */
    public function partsBySlot(): array
    {
        return $this->parts
            ->mapWithKeys(fn (Part $part) => [$part->pivot->slot => $part->name])
            ->all();
    }
}
