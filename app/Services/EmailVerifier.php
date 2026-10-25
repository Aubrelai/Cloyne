<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checks that an email address really exists before Cloyne writes to it (MillionVerifier).
 * Docs: https://developer.millionverifier.com · key MILLIONVERIFIER_KEY in .env · off when the key is blank.
 * ok → verified · catch_all / unknown → risky (sent, but watched) · invalid / disposable → invalid (never emailed).
 */
class EmailVerifier
{
    private const BASE = 'https://api.millionverifier.com/api/v3';

    public static function isConfigured(): bool
    {
        return filled(config('services.millionverifier.key'));
    }

    /** Check one contact's email and save the answer on it. Returns the new status. */
    public function check(Contact $contact): string
    {
        $response = Http::acceptJson()->timeout(30)->get(self::BASE.'/', [
            'api' => config('services.millionverifier.key'),
            'email' => $contact->email,
            'timeout' => 15,
        ]);

        $error = $response->json('error');
        if ($response->failed() || filled($error)) {
            throw new RuntimeException($this->errorMessage((string) $error, $response->status()));
        }

        $result = strtolower((string) $response->json('result'));
        $status = match ($result) {
            'ok' => 'verified',
            'invalid', 'disposable' => 'invalid',
            default => 'risky', // catch_all, unknown, or anything new
        };

        $contact->update(['email_status' => $status, 'email_checked_at' => now(), 'email_check_result' => mb_substr($result ?: 'unknown', 0, 40)]);

        return $status;
    }

    /** Check the key works without spending a credit. Returns the credits left. */
    public function testConnection(): int
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('MillionVerifier key missing. Add MILLIONVERIFIER_KEY to your .env file.');
        }
        $response = Http::acceptJson()->timeout(15)->get(self::BASE.'/credits', ['api' => config('services.millionverifier.key')]);
        $error = $response->json('error');
        if ($response->failed() || filled($error)) {
            throw new RuntimeException($this->errorMessage((string) $error, $response->status()));
        }

        return (int) $response->json('credits');
    }

    private function errorMessage(string $error, int $status): string
    {
        $lower = strtolower($error);

        return match (true) {
            str_contains($lower, 'credit') => 'MillionVerifier is out of credits. Add credits at millionverifier.com.',
            str_contains($lower, 'key') || in_array($status, [401, 403], true) => 'MillionVerifier rejected the key. Check MILLIONVERIFIER_KEY in your .env file.',
            filled($error) => 'MillionVerifier error: '.$error,
            default => "MillionVerifier error (HTTP {$status}).",
        };
    }
}
