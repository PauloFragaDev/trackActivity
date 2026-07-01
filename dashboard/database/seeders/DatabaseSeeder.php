<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ScoringRulesSeeder::class,
            ProjectsSeeder::class,
            MappingsSeeder::class,
            TeamUsersSeeder::class,
        ]);
    }
}
