<?php

namespace App\Console\Commands;

use App\Models\Part;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePartNames extends Command
{
    protected $signature = 'parts:normalize {--dry-run : Run everything inside a transaction and roll it back at the end}';

    protected $description = 'Normalize casing/spacing/typos in part names, merge duplicates, and fix the ratchet dash format.';

    /** type => [oldName => newName]. Target does not already exist as a separate part. */
    private const RENAMES = [
        'blade' => [
            'KnightShield' => 'Knight Shield',
            'Phoenix wing' => 'Phoenix Wing',
            'PhoenixRudder' => 'Phoenix Rudder',
            'ScorpioSpear' => 'Scorpio Spear',
            'SphinxCowl' => 'Sphinx Cowl',
            'Soar Phoenyx' => 'Soar Phoenix',
            'Dran sword' => 'Dran Sword',
            'Golem rock' => 'Golem Rock',
            'Hells hammer' => 'Hells Hammer',
            'Shark edge' => 'Shark Edge',
            'Silver wolf' => 'Silver Wolf',
            'Samurai saber' => 'Samurai Saber',
            'Circle ghost' => 'Circle Ghost',
            'Wizard arrow' => 'Wizard Arrow',
            'Roar tirano' => 'Roar Tyranno',
            'Bite crock' => 'Bite Croc',
            'Tricera prees' => 'Tricera Press',
            'Tusk Mamuth' => 'Tusk Mammoth',
            'Cobalt drake' => 'Cobalt Drake',
        ],
        'bit' => [
            'Rubber Accell' => 'Rubber Accel',
            'Bounce spike' => 'Bounce Spike',
            'Gear ball' => 'Gear Ball',
            'Gear flat' => 'Gear Flat',
            'Gear needle' => 'Gear Needle',
            'Gear point' => 'Gear Point',
            'Gear rush' => 'Gear Rush',
            'Low flat' => 'Low Flat',
            'Metal needle' => 'Metal Needle',
            'Trans kick' => 'Trans Kick',
            'Trans point' => 'Trans Point',
            'Wall ball' => 'Wall Ball',
            'Wall wedge' => 'Wall Wedge',
        ],
        'lock_chip' => [
            'Fort hornet' => 'Fort Hornet',
        ],
        'assist_blade' => [
            'Shlash' => 'Slash',
        ],
    ];

    /** type => [canonicalName => [oldNames to merge in]]. Canonical already exists as its own part. */
    private const MERGES = [
        'blade' => [
            'Knight Lance' => ['Kinght Lance'],
            'Hover Wyvern' => ['Hovern Wyvern'],
            'Wizard Rod' => ['WizardRod'],
            'Dran Buster' => ['Buster Dran'],
            'Cobalt Dragoon' => ['Cobalt dragon'],
        ],
        'ratchet' => [
            '1-50' => ['150'],
            '1-70' => ['170'],
            '4-50' => ['450'],
            '5-60' => ['560'],
            '7-60' => ['760'],
            '7-70' => ['770'],
        ],
        'bit' => [
            'Taper' => ['Teiper'],
            'Wedge' => ['Weadge'],
        ],
        'assist_blade' => [
            'Heavy' => ['Heav'],
        ],
        'over_blade' => [
            'Break' => ['Breck'],
        ],
    ];

    /** Beyblade line => correct name for the shared "Leon" blade part (BX-Leon Claw / UX-Leon Crest). */
    private const LEON_BLADE_BY_LINE = [
        'bx' => 'Leon Claw',
        'bx_infinity' => 'Leon Claw',
        'ux' => 'Leon Crest',
        'ux_infinity' => 'Leon Crest',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        $this->fixLeonBlade();
        $this->applyRenames();
        $this->applyMerges();

        if ($dryRun) {
            DB::rollBack();
            $this->info('Dry run completo (sin cambios escritos).');
        } else {
            DB::commit();
            $this->info('Normalización aplicada.');
        }

        return self::SUCCESS;
    }

    private function fixLeonBlade(): void
    {
        $leon = Part::where('type', 'blade')->where('name', 'Leon')->first();
        if (! $leon) {
            return;
        }

        $rows = DB::table('beyblade_part')
            ->join('beyblades', 'beyblades.id', '=', 'beyblade_part.beyblade_id')
            ->where('beyblade_part.part_id', $leon->id)
            ->select('beyblade_part.id as pivot_id', 'beyblades.line')
            ->get();

        foreach ($rows->groupBy('line') as $line => $group) {
            $correctName = self::LEON_BLADE_BY_LINE[$line] ?? null;
            if (! $correctName) {
                $this->warn("blade: no sé el nombre correcto de 'Leon' para la línea '$line', se deja sin cambios.");
                continue;
            }

            $target = Part::firstOrCreate(['type' => 'blade', 'name' => $correctName]);

            DB::table('beyblade_part')
                ->whereIn('id', $group->pluck('pivot_id'))
                ->update(['part_id' => $target->id]);

            $this->line("blade: 'Leon' -> '$correctName' ({$group->count()} combo(s), línea $line)");
        }

        if (! DB::table('beyblade_part')->where('part_id', $leon->id)->exists()) {
            $leon->delete();
        }
    }

    private function applyRenames(): void
    {
        foreach (self::RENAMES as $type => $map) {
            foreach ($map as $old => $new) {
                $part = Part::where('type', $type)->where('name', $old)->first();
                if (! $part) {
                    continue;
                }

                $existingTarget = Part::where('type', $type)->where('name', $new)->first();

                // MySQL's default collation is case-insensitive, so a casing-only
                // rename (e.g. "Phoenix wing" -> "Phoenix Wing") matches itself here.
                if ($existingTarget && $existingTarget->is($part)) {
                    $existingTarget = null;
                }

                if ($existingTarget) {
                    $this->mergePart($part, $existingTarget);
                    continue;
                }

                $part->update(['name' => $new]);
                $this->line("$type: '$old' -> '$new'");
            }
        }
    }

    private function applyMerges(): void
    {
        foreach (self::MERGES as $type => $groups) {
            foreach ($groups as $canonical => $oldNames) {
                $target = Part::firstOrCreate(['type' => $type, 'name' => $canonical]);

                foreach ($oldNames as $old) {
                    $part = Part::where('type', $type)->where('name', $old)->first();
                    if (! $part) {
                        continue;
                    }

                    $this->mergePart($part, $target);
                }
            }
        }
    }

    private function mergePart(Part $from, Part $into): void
    {
        $count = DB::table('beyblade_part')->where('part_id', $from->id)->update(['part_id' => $into->id]);
        $from->delete();
        $this->line("{$from->type}: '{$from->name}' -> '{$into->name}' ($count combo(s) reasignados)");
    }
}
