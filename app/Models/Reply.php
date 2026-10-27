<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reply to a Cloyne email, pulled back from Instantly (version 2.0). Answering them comes in 3.0. */
class Reply extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'read_at' => 'datetime', 'answered_at' => 'datetime', 'is_auto_reply' => 'boolean', 'opt_out' => 'boolean', 'needs_person' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function categoryLabel(): ?string
    {
        return $this->category ? (\App\Services\ReplyAgent::CATEGORIES[$this->category] ?? ucfirst($this->category)) : null;
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    /** True if the reply asks us to stop: we never email that business again. */
    public static function isOptOut(?string $text): bool
    {
        $text = mb_strtolower(trim((string) $text));
        // Only the reply's own first lines, not the quoted email below it.
        $own = mb_substr(preg_split('/\n\s*(>|on .+ wrote:)/u', $text)[0] ?? $text, 0, 400);

        return (bool) preg_match('/\b(unsubscribe|stop emailing|stop sending|remove me|take me off|no thanks|not interested|do not (contact|email)|don\'t (contact|email))\b|^\s*stop\b/u', $own);
    }
}
