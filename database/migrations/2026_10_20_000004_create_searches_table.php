<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * searches: each Lead Finder search (one Apify Google Maps run).
 * businesses.search_id: which search first found the business.
 * businesses.google_category: Google's own category label, e.g. "Cosmetic dentist".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->string('category');          // what the user searched for, e.g. "Dentists"
            $table->string('area');              // e.g. "Dallas, TX"
            $table->unsignedInteger('max_results');
            $table->string('status')->default('running')->index(); // running | done | failed
            $table->string('apify_run_id')->nullable();
            $table->string('apify_dataset_id')->nullable();
            $table->unsignedInteger('found')->nullable();   // businesses Google returned
            $table->unsignedInteger('added')->nullable();   // new to Cloyne
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('search_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('google_category')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('search_id');
            $table->dropColumn('google_category');
        });
        Schema::dropIfExists('searches');
    }
};
