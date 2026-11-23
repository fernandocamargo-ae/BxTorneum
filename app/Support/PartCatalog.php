<?php

namespace App\Support;

use App\Models\Part;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every Beyblade X part released from June 2023 to November 2026 (database/data/beyblade_x_parts.json),
 * plus the other ways players write them: Hasbro names, abbreviations and common misspellings
 * (database/data/beyblade_x_part_aliases.json).
 */
class PartCatalog
{
    /** @return array<string, array<int, string>> part type => names */
    public static function all(): array
    {
        static $parts;

        return $parts ??= self::read('beyblade_x_parts.json');
    }

    /** Compares names ignoring case, accents, spaces and dashes. */
    public static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }

    /**
     * The official name of a part however it was typed. Unknown names are kept as they are, so a
     * brand-new release can still be registered, but reuse an existing part that only differs in
     * case, spaces or accents.
     */
    public static function canonical(string $type, string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $key = self::key($name);

        return self::names($type)[$key]
            ?? self::aliases($type)[$key]
            ?? Part::where('type', $type)->pluck('name')->first(fn ($existing) => $key !== '' && self::key($existing) === $key)
            ?? $name;
    }

    /** Whether a typed name is a real part of that slot: in the catalog, or registered later by an admin. */
    public static function exists(string $type, string $name): bool
    {
        $official = self::canonical($type, $name);
        if (isset(self::names($type)[self::key($official)])) {
            return true;
        }

        $fake = array_map(self::key(...), self::data()['not_parts'][$type] ?? []);

        return ! in_array(self::key($official), $fake, true) && Part::where('type', $type)->where('name', $official)->exists();
    }

    /** The other slot where that name is an official part, if any ("Blitz" => metal_blade). */
    public static function slotOf(string $name): ?string
    {
        return collect(array_keys(self::all()))->first(fn ($type) => isset(self::names($type)[self::key($name)]));
    }

    /** @return Collection<int, string> names for the autocomplete, best matches first */
    public static function search(string $type, string $query, int $limit = 12): Collection
    {
        $key = self::key($query);
        // "hidden": nombres generales que se conservan en decks viejos, pero ya no se ofrecen.
        $hidden = array_flip(array_map(self::key(...), [...self::data()['not_parts'][$type] ?? [], ...self::data()['hidden'][$type] ?? []]));
        $byAlias = collect(self::aliases($type))->filter(fn ($name, $alias) => $key !== '' && str_contains((string) $alias, $key))->flip();

        return Part::where('type', $type)->orderBy('name')->pluck('name')
            ->reject(fn ($name) => isset($hidden[self::key($name)]))
            ->filter(fn ($name) => $key === '' || str_contains(self::key($name), $key) || $byAlias->has($name))
            ->sortBy(fn ($name) => match (true) {
                $key === '' || str_starts_with(self::key($name), $key) => 0,
                str_contains(self::key($name), $key) => 1,
                default => 2,
            })
            ->take($limit)
            ->values();
    }

    /**
     * Adds what is missing. Never renames or removes a part: players' decks point at them.
     */
    public static function addMissing(): void
    {
        DB::transaction(function () {
            foreach (self::all() as $type => $names) {
                $existing = Part::where('type', $type)->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))->flip();

                foreach ($names as $name) {
                    if (! $existing->has(mb_strtolower($name))) {
                        Part::firstOrCreate(['type' => $type, 'name' => $name]);
                    }
                }
            }
        });
    }

    /** @return array<string, string> key => official name */
    private static function names(string $type): array
    {
        static $names = [];

        return $names[$type] ??= collect(self::all()[$type] ?? [])->keyBy(fn ($name) => self::key($name))->all();
    }

    /** @return array<string, string> key => official name */
    private static function aliases(string $type): array
    {
        return self::data()['aliases'][$type] ?? [];
    }

    private static function data(): array
    {
        static $data;

        return $data ??= self::read('beyblade_x_part_aliases.json');
    }

    private static function read(string $file): array
    {
        return json_decode(file_get_contents(database_path("data/{$file}")), true, flags: JSON_THROW_ON_ERROR);
    }
}
