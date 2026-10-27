<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v4.0, the full CRM loop: when and how a lead booked, and the deal the LaiQ Digital CRM reports back
 * (showed, no-show, quoted, won, lost; the package sold and its monthly value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('booked_at')->nullable()->index()->after('call_script_at');
            $table->string('booked_via', 10)->nullable()->after('booked_at');   // call, email, manual
            $table->string('crm_deal_id', 64)->nullable()->after('booked_via');
            $table->string('deal_stage', 20)->nullable()->index()->after('crm_deal_id');
            $table->string('deal_package', 40)->nullable()->after('deal_stage');
            $table->decimal('deal_value', 10, 2)->nullable()->after('deal_package'); // per month, USD
            $table->string('deal_note', 500)->nullable()->after('deal_value');
            $table->timestamp('deal_updated_at')->nullable()->after('deal_note');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['booked_at']);
            $table->dropIndex(['deal_stage']);
            $table->dropColumn(['booked_at', 'booked_via', 'crm_deal_id', 'deal_stage', 'deal_package', 'deal_value', 'deal_note', 'deal_updated_at']);
        });
    }
};
