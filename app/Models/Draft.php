<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A first outreach email for one lead, written by Claude and checked by a person.
 * Nothing is ever sent without status "approved" (sending is Step 5).
 */
class Draft extends Model
{
    public const STATUSES = [
        'writing' => 'Writing',
        'pending' => 'Waiting for review',
        'approved' => 'Approved',
        'skipped' => 'Skipped',
        'failed' => 'Failed',
    ];

    /** Statuses that mean the lead already has a draft in play (don't write another). */
    public const ACTIVE = ['writing', 'pending', 'approved'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'writing', 'edited' => false];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'edited' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('drafts.status', $status);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Sign-off added under every email. */
    public static function signature(): string
    {
        $o = config('cloyne.outreach');

        return trim($o['sender_name']."\n".$o['sender_title']);
    }

    /** Legal footer (CAN-SPAM): who we are, where we are, how to opt out. */
    public static function footer(): string
    {
        $address = config('cloyne.outreach.mailing_address') ?: '[Mailing address missing: add CLOYNE_MAILING_ADDRESS to .env]';

        return $address."\nNot interested? Reply \"no thanks\" and I won't email you again.";
    }

    public static function readyToSend(): bool
    {
        return filled(config('cloyne.outreach.mailing_address'));
    }

    /** The whole email as the lead would see it. */
    public function fullText(): string
    {
        return trim((string) $this->body)."\n\n".self::signature()."\n\n".self::footer();
    }

    public function wordCount(): int
    {
        return str_word_count(strip_tags((string) $this->body));
    }

    /**
     * Quick checks against the outreach rules, shown to the reviewer.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $body = (string) $this->body;
        $lower = Str::lower($body.' '.$this->subject);
        $warnings = [];

        if (preg_match('/\$\s?\d|\d+\s?(dollars|\/mo\b|per month)/i', $body.' '.$this->subject)) {
            $warnings[] = 'Mentions a price. Pricing is for the call.';
        }
        foreach (['guarantee', 'double your', 'triple your', '10x', '100%'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                $warnings[] = "Promises results (\"{$phrase}\").";
                break;
            }
        }
        foreach (['driving by', 'drove by', 'was a customer', 'friend told me', 'friend of mine'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                $warnings[] = "Fakes familiarity (\"{$phrase}\").";
                break;
            }
        }
        $max = (int) config('cloyne.outreach.max_words', 120);
        if ($this->wordCount() > $max) {
            $warnings[] = "Long: {$this->wordCount()} words (aim for under {$max}).";
        }
        if (! $this->to_email) {
            $warnings[] = 'No email address for this lead.';
        }

        return $warnings;
    }
}
