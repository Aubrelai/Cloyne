<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The LaiQ Digital CRM's key for /api/* ("Authorization: Bearer <key>").
 * The Owner makes it in Settings → CRM connection; only its sha256 is stored.
 */
class RequireCrmKey
{
    public const SETTING = 'costs_key_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $hash = Setting::find(self::SETTING)?->value;
        $given = (string) ($request->bearerToken() ?: $request->header('X-Cloyne-Key'));

        if (! $hash || $given === '' || ! hash_equals((string) json_decode($hash, true), hash('sha256', $given))) {
            return response()->json(['error' => 'Wrong or missing key. Make one in Cloyne → Settings → CRM connection.'], 401);
        }

        return $next($request);
    }
}
