<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Services\WildentalCatalogService;
use Illuminate\Database\Seeder;

class DentalTreatmentsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(WildentalCatalogService::class);

        Clinic::withoutGlobalScopes()->each(function (Clinic $clinic) use ($service) {
            $service->seedForClinic($clinic);
        });
    }
}
