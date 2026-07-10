<?php

namespace App\Models;

use App\Support\BeybladeLines;
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

    /** @return array<string,string> slot => part name, ordered per BeybladeLines::slotsFor($this->line) */
    public function partsBySlot(): array
    {
        $bySlot = $this->parts
            ->mapWithKeys(fn (Part $part) => [$part->pivot->slot => $part->name]);

        return collect(BeybladeLines::slotsFor($this->line))
            ->filter(fn (string $slot) => $bySlot->has($slot))
            ->mapWithKeys(fn (string $slot) => [$slot => $bySlot->get($slot)])
            ->all();
    }
}
