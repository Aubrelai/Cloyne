<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One cold call, logged in the LaiQ Digital CRM and sent back to Cloyne. */
class Call extends Model
{
    public const OUTCOMES = [
        'no_answer' => 'No answer',
        'voicemail' => 'Left voicemail',
        'call_back' => 'Call back',
        'talked' => 'Talked',
        'not_interested' => 'Not interested',
        'booked' => 'Booked',
        'wrong_number' => 'Wrong number',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['called_at' => 'datetime', 'call_back_on' => 'date'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst(str_replace('_', ' ', $this->outcome));
    }
}
