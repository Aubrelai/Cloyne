<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloyne prospects every kind of business, not only trades:
 * - businesses.trade becomes businesses.category
 * - each audit (and the business, for fast filtering) records the best-fit LaiQ package
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['trade']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->renameColumn('trade', 'category');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->index('category');
            $table->string('package')->nullable()->index()->after('score');
        });
        Schema::table('audits', function (Blueprint $table) {
            $table->string('package')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('package');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['package']);
            $table->dropColumn('package');
            $table->dropIndex(['category']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->renameColumn('category', 'trade');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->index('trade');
        });
    }
};
