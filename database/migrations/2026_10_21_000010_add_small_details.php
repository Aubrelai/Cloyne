<?php

use App\Support\Phone;
use App\Support\WebAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Small details plan:
 * - users: theme + time zone per person
 * - businesses: exclude reason, chain flag
 * - searches: why results were skipped
 * - saved_views: named Leads filters per person
 * - settings: values the Owner edits on screen (email rules, packages on sale)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 10)->default('dark');                    // dark | light | system
            $table->string('timezone', 64)->default('America/New_York');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('exclude_reason')->nullable();
            $table->boolean('is_chain')->default(false);
            $table->string('website_host')->nullable()->index(); // "bright-smile.com", for duplicates and chains
            $table->index('phone');
        });

        // Tidy the leads already found: one phone format, clean links, the site's host.
        DB::table('businesses')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $website = WebAddress::clean($row->website);
                DB::table('businesses')->where('id', $row->id)->update([
                    'phone' => Phone::normalize($row->phone),
                    'website' => $website,
                    'website_host' => WebAddress::host($website),
                ]);
            }
        });

        Schema::table('searches', function (Blueprint $table) {
            $table->unsignedInteger('skipped_existing')->nullable();   // already in Cloyne
            $table->unsignedInteger('skipped_dismissed')->nullable();  // deleted before
            $table->unsignedInteger('skipped_closed')->nullable();     // permanently closed
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('filters');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable(); // JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('saved_views');
        Schema::table('searches', fn (Blueprint $t) => $t->dropColumn(['skipped_existing', 'skipped_dismissed', 'skipped_closed']));
        Schema::table('businesses', function (Blueprint $t) {
            $t->dropIndex(['phone']);
            $t->dropIndex(['website_host']);
            $t->dropColumn(['exclude_reason', 'is_chain', 'website_host']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['theme', 'timezone']));
    }
};
