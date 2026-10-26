<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Values the Owner changes on the Settings screen. Each one overrides a
 * config value (config/cloyne.php or .env) while it's set; "Reset" removes it.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** Setting key => the config value it overrides. */
    public const OVERRIDES = [
        'selling' => 'cloyne.selling',
        'booking_url' => 'cloyne.outreach.booking_url',
        'mailing_address' => 'cloyne.outreach.mailing_address',
        'tone' => 'cloyne.outreach.tone',
        'never' => 'cloyne.outreach.never',
        'min_score' => 'cloyne.outreach.min_score',
        'max_words' => 'cloyne.outreach.max_words',
        'monthly_budget' => 'cloyne.monthly_budget',
        'crm_min_score' => 'cloyne.crm.min_score',
        'crm_always_packages' => 'cloyne.crm.always_packages',
        'factsheet' => 'factsheet.entries',
    ];

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        config([self::OVERRIDES[$key] ?? "cloyne.settings.{$key}" => $value]);
    }

    public static function forget(string $key): void
    {
        static::whereKey($key)->delete();
    }

    /** Copy saved settings over the config. Called once per request; quiet before the table exists. */
    public static function applyToConfig(): void
    {
        try {
            foreach (static::all() as $setting) {
                if (isset(self::OVERRIDES[$setting->key])) {
                    config([self::OVERRIDES[$setting->key] => json_decode((string) $setting->value, true)]);
                }
            }
        } catch (Throwable) {
            // No settings table yet (fresh install, before migrate).
        }
    }

    public static function isSet(string $key): bool
    {
        try {
            return static::whereKey($key)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
