<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('viewer')->after('password');
            $table->boolean('must_change_password')->default(false)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        // The default Laravel test account (password "password") must never reach a live Cloyne.
        DB::table('users')->where('email', 'test@example.com')->delete();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'must_change_password', 'last_login_at']);
        });
    }
};
