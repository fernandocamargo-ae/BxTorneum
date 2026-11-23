<?php

namespace App\Support;

/**
 * Ready-made avatars (characters and beys of every generation). The list is shared with the
 * picker (resources/js/lib/avatars.json); the pictures live in public/images/avatars.
 */
class AvatarPresets
{
    /** Marks avatar_path as a ready-made avatar instead of an uploaded file. */
    private const PREFIX = 'preset:';

    /** @return array<int, array{id: string, gen: string, kind: string, name: string}> */
    public static function all(): array
    {
        static $avatars;

        return $avatars ??= json_decode(file_get_contents(resource_path('js/lib/avatars.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    public static function pathFor(string $id): string
    {
        return self::PREFIX.$id;
    }

    public static function isPreset(?string $path): bool
    {
        return $path !== null && str_starts_with($path, self::PREFIX);
    }

    public static function url(string $path): string
    {
        return '/images/avatars/'.substr($path, strlen(self::PREFIX)).'.webp';
    }
}
