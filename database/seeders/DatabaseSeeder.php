<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Cloyne seeds nothing by default. The Owner account is created on the
     * first-run screen (/setup) or with: php artisan cloyne:owner
     * Sample leads are for tests only: php artisan db:seed --class=LeadSeeder
     */
    public function run(): void
    {
        //
    }
}
