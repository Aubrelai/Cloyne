<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Cloyne installs its own updates on the server (deploy/auto-update.sh, run by cron every 15 minutes).
 * The script writes storage/app/auto-update.json; Settings → Updates shows it. Owners switch it off,
 * which leaves storage/app/auto-update.off for the script to see.
 */
class AutoUpdate
{
    public static function statusFile(): string
    {
        return storage_path('app/auto-update.json');
    }

    public static function offFile(): string
    {
        return storage_path('app/auto-update.off');
    }

    public static function isOn(): bool
    {
        return ! is_file(self::offFile());
    }

    public static function turn(bool $on): void
    {
        if ($on) {
            @unlink(self::offFile());
        } else {
            @file_put_contents(self::offFile(), 'Turned off in Settings at '.now()->toIso8601String()."\n");
        }
    }

    /** @return array{state: string, message: string, checked_at: ?Carbon, updated_at: ?Carbon, commit: ?string}|null null = the server hasn't checked yet */
    public static function status(): ?array
    {
        $raw = is_file(self::statusFile()) ? json_decode((string) file_get_contents(self::statusFile()), true) : null;
        if (! is_array($raw)) {
            return null;
        }
        $date = fn ($v) => filled($v) ? rescue(fn () => Carbon::parse($v), null, false) : null;

        return [
            'state' => (string) ($raw['state'] ?? 'unknown'),
            'message' => (string) ($raw['message'] ?? ''),
            'checked_at' => $date($raw['checked_at'] ?? null),
            'updated_at' => $date($raw['updated_at'] ?? null),
            'commit' => filled($raw['commit'] ?? null) ? (string) $raw['commit'] : null,
        ];
    }
}
