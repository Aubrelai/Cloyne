<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloyne's core lead tables:
 * - businesses: one row per company found by the Lead Finder
 * - contacts:   people and email addresses at each business
 * - audits:     each AI check of a business's website and ads, with its score
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trade')->index();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source')->default('google_maps');
            $table->string('place_id')->nullable()->unique();
            $table->string('license_number')->nullable();
            $table->string('license_status')->nullable();
            $table->date('license_expires_on')->nullable();
            $table->unsignedTinyInteger('score')->nullable()->index(); // latest audit score, kept here for fast sorting
            $table->string('stage')->default('new')->index();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('email_status')->default('unverified'); // verified | risky | invalid | bounced | unverified
            $table->timestamps();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->json('checks'); // [{title, detail, passed, points}, ...]
            $table->unsignedTinyInteger('score');
            $table->text('pitch')->nullable(); // best pitch angle, in one sentence
            $table->timestamp('audited_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('businesses');
    }
};
