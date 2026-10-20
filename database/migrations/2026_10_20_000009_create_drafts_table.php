<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('angle')->nullable();          // the gap the email is built on
            $table->string('package')->nullable();        // package it pitches
            $table->string('status', 20)->default('writing')->index(); // writing, pending, approved, skipped, failed
            $table->text('error')->nullable();
            $table->string('model')->nullable();
            $table->boolean('edited')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
