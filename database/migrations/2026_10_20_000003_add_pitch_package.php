<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pitch_package: a smaller package that also fits the business, pitched while
 * its best-fit package isn't on sale (see CLOYNE_SELLING in config/cloyne.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('pitch_package')->nullable()->after('package');
        });
        Schema::table('audits', function (Blueprint $table) {
            $table->string('pitch_package')->nullable()->after('package');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('pitch_package');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('pitch_package');
        });
    }
};
