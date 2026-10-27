<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v3.0 reply agent: how each reply was sorted, the answer Claude drafted from the fact sheet, and what happened to it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replies', function (Blueprint $table) {
            $table->string('eaccount')->nullable()->after('from_email');           // our inbox that received it (answers go from here)
            $table->string('category', 20)->nullable()->index()->after('opt_out'); // interested, question, not_now, opt_out, other
            $table->boolean('needs_person')->default(false)->after('category');
            $table->string('handoff_reason')->nullable()->after('needs_person');
            $table->text('answer')->nullable()->after('handoff_reason');
            // null → drafted → sent | handled (a person took it) | skipped ; or failed
            $table->string('answer_status', 20)->nullable()->index()->after('answer');
            $table->text('answer_error')->nullable()->after('answer_status');
            $table->foreignId('answered_by')->nullable()->after('answer_error')->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable()->after('answered_by');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answered_by');
            $table->dropColumn(['eaccount', 'category', 'needs_person', 'handoff_reason', 'answer', 'answer_status', 'answer_error', 'answered_at']);
        });
    }
};
