<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

/** Turns technical failures into one plain sentence anyone on the team can act on. */
class FriendlyError
{
    public static function message(?Throwable $e, string $service = 'Claude'): string
    {
        if (! $e) {
            return 'Something went wrong. Try again.';
        }

        $text = $e->getMessage();

        return match (true) {
            $e instanceof ConnectionException => "Couldn't reach {$service}. Check the internet connection and try again.",
            $e instanceof TimeoutExceededException, $e instanceof MaxAttemptsExceededException,
            str_contains($text, 'timed out') => "{$service} took too long to answer. Try again in a minute.",
            str_contains($text, 'cURL error 6') => "Couldn't reach {$service}. Check the internet connection.",
            str_contains(strtolower($text), 'sqlstate') => 'Cloyne couldn\'t save this. Try again; if it keeps happening, run update.bat.',
            // Messages Cloyne wrote itself are already plain.
            strlen($text) > 0 && strlen($text) < 200 && ! str_contains($text, '\\') => $text,
            default => 'Something went wrong. Try again.',
        };
    }
}
