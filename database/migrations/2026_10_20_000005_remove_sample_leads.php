<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the made-up sample businesses that earlier versions loaded,
 * so only real leads from the Lead Finder remain.
 */
return new class extends Migration
{
    private const SAMPLES = [
        'Sunshine Roofing Co.', 'Bayside Plumbing', 'Bright Smile Dental', 'Iron Peak Fitness',
        'Glow Med Spa', 'Desert Sun Landscaping', 'Casa Verde Taqueria', 'Gulf Coast Pools',
        'Lone Star HVAC', 'Coastal Air Pros', 'Ridgeline Painting', 'Peak Electric',
    ];

    public function up(): void
    {
        DB::table('businesses')->whereIn('name', self::SAMPLES)->whereNull('place_id')->delete();
    }

    public function down(): void
    {
        // Nothing to restore: run `php artisan db:seed --class=LeadSeeder` to load samples again.
    }
};
