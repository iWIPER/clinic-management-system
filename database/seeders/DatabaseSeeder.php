<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            RolesAndPermissionsSeeder::class,
            DentalTreatmentsSeeder::class,
            AnamnesisTemplatesSeeder::class,
            DocumentTemplatesSeeder::class,
        ]);
    }
}
