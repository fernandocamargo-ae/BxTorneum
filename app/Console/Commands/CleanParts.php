<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Support\PartCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanParts extends Command
{
    protected $signature = 'parts:clean {--dry-run : Muestra lo que haría sin guardar nada}';

    protected $description = 'Une las piezas repetidas o mal escritas con su nombre oficial y quita las que nadie usa y no existen';

    public function handle(): int
    {
        DB::beginTransaction();

        PartCatalog::addMissing();
        $merged = $this->mergeIntoOfficialNames();
        $removed = $this->removeUnusedUnknown();

        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->info(($this->option('dry-run') ? '[Prueba, sin guardar] ' : '')."Unidas: {$merged}. Quitadas sin uso: {$removed}.");
        $this->reportLeftovers();

        return self::SUCCESS;
    }

    private function mergeIntoOfficialNames(): int
    {
        $count = 0;

        foreach (Part::orderBy('id')->get() as $part) {
            $official = PartCatalog::canonical($part->type, $part->name);
            if ($official === $part->name) {
                continue;
            }

            $typed = $part->name;

            // La colación de MySQL no distingue mayúsculas: "Silver wolf" y "Silver Wolf" son la misma fila.
            $target = Part::where('type', $part->type)->where('name', $official)->whereKeyNot($part->id)->first();

            if ($target) {
                $uses = DB::table('deck_beyblade_part')->where('part_id', $part->id)->update(['part_id' => $target->id]);
                $part->delete();
            } else {
                $uses = DB::table('deck_beyblade_part')->where('part_id', $part->id)->count();
                $part->update(['name' => $official]);
            }

            $this->line("{$part->type}: \"{$typed}\" -> \"{$official}\" ({$uses} combo(s))");
            $count++;
        }

        return $count;
    }

    private function removeUnusedUnknown(): int
    {
        $unused = $this->unknownParts()->filter(fn (Part $part) => ! $this->isUsed($part));
        $unused->each(function (Part $part) {
            $this->line("{$part->type}: \"{$part->name}\" quitada (no existe y nadie la usa)");
            $part->delete();
        });

        return $unused->count();
    }

    private function reportLeftovers(): void
    {
        foreach ($this->unknownParts() as $part) {
            $owners = DB::table('deck_beyblade_part')
                ->join('deck_beyblades', 'deck_beyblades.id', '=', 'deck_beyblade_part.deck_beyblade_id')
                ->join('decks', 'decks.id', '=', 'deck_beyblades.deck_id')
                ->join('users', 'users.id', '=', 'decks.user_id')
                ->where('deck_beyblade_part.part_id', $part->id)
                ->get(['users.nickname', 'decks.name']);

            $this->warn("Por revisar — {$part->type} \"{$part->name}\": ".($owners->map(fn ($o) => "{$o->nickname} ({$o->name})")->implode(', ') ?: 'sin uso'));
        }
    }

    private function unknownParts()
    {
        $known = collect(PartCatalog::all())->map(fn ($names) => array_flip($names));

        return Part::orderBy('type')->orderBy('name')->get()->reject(fn (Part $part) => isset($known[$part->type][$part->name]));
    }

    private function isUsed(Part $part): bool
    {
        return DB::table('deck_beyblade_part')->where('part_id', $part->id)->exists();
    }
}
