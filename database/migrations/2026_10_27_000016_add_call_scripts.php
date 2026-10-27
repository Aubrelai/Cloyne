<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2.1: a call script per lead (opener, answers to pushback), written by Claude from its latest audit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('call_script')->nullable()->after('crm_list');
            $table->timestamp('call_script_at')->nullable()->after('call_script');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['call_script', 'call_script_at']);
        });
    }
};
