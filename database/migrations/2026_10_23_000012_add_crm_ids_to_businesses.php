<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Leads that came from the LaiQ Digital CRM's cold-call lists keep the CRM's own ID, so results go back to the right row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('crm_id', 64)->nullable()->unique()->after('place_id');
            $table->string('crm_list', 120)->nullable()->after('crm_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['crm_id']);
            $table->dropColumn(['crm_id', 'crm_list']);
        });
    }
};
