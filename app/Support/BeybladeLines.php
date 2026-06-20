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
