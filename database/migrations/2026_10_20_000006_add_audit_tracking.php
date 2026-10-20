<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * businesses.audit_status: null (not queued) | queued | failed
 * businesses.audit_error:  why the last audit failed
 * audits.signals:          what the website scan found (pixel, forms, emails...)
 * audits.model:            which Claude model ran the audit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('audit_status')->nullable()->index()->after('stage');
            $table->text('audit_error')->nullable()->after('audit_status');
        });
        Schema::table('audits', function (Blueprint $table) {
            $table->json('signals')->nullable()->after('checks');
            $table->string('model')->nullable()->after('pitch');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['signals', 'model']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['audit_status']);
            $table->dropColumn(['audit_status', 'audit_error']);
        });
    }
};
