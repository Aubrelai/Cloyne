<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cold calls the team logs in the LaiQ Digital CRM, sent back to Cloyne. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('crm_call_id', 64)->nullable()->unique(); // so the same call sent twice is saved once
            $table->string('outcome', 30);                           // see App\Models\Call::OUTCOMES
            $table->text('notes')->nullable();
            $table->string('caller', 120)->nullable();
            $table->date('call_back_on')->nullable();
            $table->timestamp('called_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
