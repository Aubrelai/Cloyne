<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** Email statuses and the word shown in the app. */
    public const EMAIL_STATUSES = [
        'verified' => 'Verified',
        'risky' => 'Risky',
        'invalid' => 'Invalid',
        'bounced' => 'Bounced',
        'unverified' => 'Unverified',
    ];

    protected $guarded = ['id'];

    /** Addresses that never reach a person: system mailboxes, website-builder and tracking addresses. */
    private const NO_PERSON = ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'mailer-daemon', 'postmaster',
        'abuse', 'bounce', 'bounces', 'unsubscribe', 'privacy', 'dmca', 'nobody'];

    private const NOT_THEIRS = ['sentry', 'wixpress', 'wix.com', 'example.com', 'example.org', 'domain.com', 'yourname',
        'yourdomain', 'email.com', 'godaddy', 'squarespace', 'sentry.io', 'mysite.com', 'website.com', 'test.com'];

    /** True if an email is worth saving: a real-looking address a person reads. */
    public static function usableEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        [$local, $domain] = explode('@', $email, 2);

        return ! preg_match('/\.(png|jpe?g|gif|webp|svg|css|js|ico)$/', $email)
            && ! str_contains($email, '@2x')
            && ! preg_match('/^[a-f0-9]{16,}$/', $local)             // tracking IDs
            && ! in_array($local, self::NO_PERSON, true)
            && ! collect(self::NOT_THEIRS)->contains(fn ($bad) => str_contains($domain, $bad) || str_contains($local, $bad));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function emailLabel(): string
    {
        if (! $this->email) {
            return 'Not found';
        }

        return self::EMAIL_STATUSES[$this->email_status] ?? 'Unverified';
    }
}
