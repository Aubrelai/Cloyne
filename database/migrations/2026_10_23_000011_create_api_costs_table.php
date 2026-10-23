<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per paid Claude call, so Cloyne knows what it spends (shared with the CRM's Profit page). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_costs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 20);           // claude
            $table->string('purpose', 20);           // audit, draft, other
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('amount', 12, 6)->default(0); // US dollars, estimated from the token counts
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_costs');
    }
};
