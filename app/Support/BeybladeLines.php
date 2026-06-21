<?php

namespace App\Support;

class BeybladeLines
{
    public const SLOTS = [
        'bx' => ['blade', 'ratchet', 'bit'],
        'ux' => ['blade', 'ratchet', 'bit'],
        'bx_infinity' => ['blade', 'ratchet', 'bit'],
        'ux_infinity' => ['blade', 'ratchet', 'bit'],
        'cx' => ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
        'cx_infinity' => ['lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit'],
    ];

    public const OPTIONAL = [
        'bx_infinity' => ['ratchet'],
        'ux_infinity' => ['ratchet'],
    ];

    public const LINE_LABELS = [
        'bx' => 'BX',
        'ux' => 'UX',
        'bx_infinity' => 'BX Infinity',
        'ux_infinity' => 'UX Infinity',
        'cx' => 'CX',
        'cx_infinity' => 'CX Infinity',
    ];

    public const SLOT_LABELS = [
        'blade' => 'Blade',
        'ratchet' => 'Ratchet',
        'bit' => 'Bit',
        'lock_chip' => 'Lock Chip',
        'main_blade' => 'Main Blade',
        'assist_blade' => 'Assist Blade',
        'over_blade' => 'Over Blade',
        'metal_blade' => 'Metal Blade',
    ];

    public static function lineLabel(string $line): string
    {
        return self::LINE_LABELS[$line] ?? $line;
    }

    public static function slotLabel(string $slot): string
    {
        return self::SLOT_LABELS[$slot] ?? $slot;
    }

    public static function lines(): array
    {
        return array_keys(self::SLOTS);
    }

    public static function slotsFor(string $line): array
    {
        return self::SLOTS[$line] ?? [];
    }

    public static function isOptional(string $line, string $slot): bool
    {
        return in_array($slot, self::OPTIONAL[$line] ?? [], true);
    }

    /** Slots that must be present for a line (excludes optional ones). */
    public static function requiredSlotsFor(string $line): array
    {
        return array_values(array_filter(
            self::slotsFor($line),
            fn (string $slot) => ! self::isOptional($line, $slot),
        ));
    }
}
