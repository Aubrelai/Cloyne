<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.0, sending through Instantly:
 * - each draft gets two follow-ups (day 3, day 7) and its sending state;
 * - replies pulled back from Instantly land in `replies` (the Inbox screen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->text('follow_up_1')->nullable()->after('body');
            $table->text('follow_up_2')->nullable()->after('follow_up_1');
            // null (not sent) → queued (handed to Instantly) → replied | bounced | unsubscribed | finished
            $table->string('send_status', 20)->nullable()->index()->after('status');
            $table->timestamp('queued_at')->nullable()->index()->after('send_status');
            $table->string('instantly_lead_id', 64)->nullable()->after('queued_at');
        });

        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('instantly_email_id', 64)->unique();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_auto_reply')->default(false);
            $table->boolean('opt_out')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn(['follow_up_1', 'follow_up_2', 'send_status', 'queued_at', 'instantly_lead_id']);
        });
    }
};
