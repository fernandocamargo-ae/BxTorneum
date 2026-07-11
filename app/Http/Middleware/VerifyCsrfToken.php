<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    /**
     * Laravel's default cookie name ("XSRF-TOKEN") is hardcoded and identical across every
     * unconfigured local Laravel project. On shared hosts like 127.0.0.1/localhost, cookies
     * are scoped by hostname only (not by port), so two local projects using the default name
     * silently overwrite each other's CSRF cookie. A project-specific name avoids that collision.
     */
    private const COOKIE_NAME = 'bxtorneum_xsrf_token';

    protected function newCookie($request, $config)
    {
        return new Cookie(
            self::COOKIE_NAME,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }

    public static function serialized()
    {
        return EncryptCookies::serialized(self::COOKIE_NAME);
    }
}
