<?php

namespace App\Console\Commands;

use App\Models\Beyblade;
use App\Models\Part;
use App\Support\BeybladeLines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SwapBeybladeSlots extends Command
{
    protected $signature = 'beyblade:swap-slots {beyblade : Beyblade id} {slotA} {slotB} {--dry-run}';

    protected $description = 'Fix a combo where two part values were entered into the wrong slots (e.g. lock_chip/main_blade swapped).';

    public function handle(): int
    {
        $beyblade = Beyblade::find((int) $this->argument('beyblade'));
        if (! $beyblade) {
            $this->error('Beyblade no encontrado.');

            return self::FAILURE;
        }

        $slotA = (string) $this->argument('slotA');
        $slotB = (string) $this->argument('slotB');
        $validSlots = BeybladeLines::slotsFor($beyblade->line);

        if (! in_array($slotA, $validSlots, true) || ! in_array($slotB, $validSlots, true)) {
            $this->error("Slots inválidos para la línea '{$beyblade->line}'. Válidos: ".implode(', ', $validSlots));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        $pivotA = DB::table('beyblade_part')->where('beyblade_id', $beyblade->id)->where('slot', $slotA)->first();
        $pivotB = DB::table('beyblade_part')->where('beyblade_id', $beyblade->id)->where('slot', $slotB)->first();

        if (! $pivotA || ! $pivotB) {
            DB::rollBack();
            $this->error('El combo no tiene piezas en ambos slots.');

            return self::FAILURE;
        }

        $partA = Part::find($pivotA->part_id);
        $partB = Part::find($pivotB->part_id);

        foreach ([$partA, $partB] as $part) {
            $usedElsewhere = DB::table('beyblade_part')
                ->where('part_id', $part->id)
                ->where('beyblade_id', '!=', $beyblade->id)
                ->exists();

            if ($usedElsewhere) {
                DB::rollBack();
                $this->error("'{$part->name}' ({$part->type}) también se usa en otros combos — no se puede reasignar el tipo globalmente. Requiere arreglo manual.");

                return self::FAILURE;
            }
        }

        if (Part::where('type', $slotB)->where('name', $partA->name)->exists()
            || Part::where('type', $slotA)->where('name', $partB->name)->exists()) {
            DB::rollBack();
            $this->error('Ya existe una pieza con ese nombre en el tipo destino — abortando.');

            return self::FAILURE;
        }

        $partA->update(['type' => $slotB]);
        $partB->update(['type' => $slotA]);

        DB::table('beyblade_part')->where('id', $pivotA->id)->update(['part_id' => $partB->id]);
        DB::table('beyblade_part')->where('id', $pivotB->id)->update(['part_id' => $partA->id]);

        $this->line("beyblade {$beyblade->id}: $slotA <-> $slotB — '{$partA->name}' ahora es $slotB, '{$partB->name}' ahora es $slotA");

        if ($dryRun) {
            DB::rollBack();
            $this->info('Dry run completo (sin cambios escritos).');
        } else {
            DB::commit();
            $this->info('Swap aplicado.');
        }

        return self::SUCCESS;
    }
}
