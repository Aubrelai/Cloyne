<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A named set of Leads filters, e.g. "Hot med spas, FL". Each person has their own. */
class SavedView extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return route('leads', $this->filters + ['view' => $this->id]);
    }
}
