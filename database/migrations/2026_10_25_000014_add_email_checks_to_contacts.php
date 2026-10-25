<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When an email was checked with MillionVerifier, and its raw answer (ok, catch_all, invalid…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('email_checked_at')->nullable()->after('email_status');
            $table->string('email_check_result', 40)->nullable()->after('email_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['email_checked_at', 'email_check_result']);
        });
    }
};
