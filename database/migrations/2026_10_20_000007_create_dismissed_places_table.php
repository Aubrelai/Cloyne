<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dismissed_places: Google places you deleted from Cloyne.
 * The Lead Finder skips them, so deleted leads don't come back in later searches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissed_places', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_places');
    }
};
