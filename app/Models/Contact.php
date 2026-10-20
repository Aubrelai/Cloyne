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
