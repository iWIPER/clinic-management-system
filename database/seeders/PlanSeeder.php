<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Start Grátis',
                'slug' => 'start-gratis',
                'is_free' => true,
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'max_clinics' => 1,
                'max_patients' => 100,
                'max_users' => 1,
                'storage_gb' => 1,
                'features' => [
                    'agendamento' => true,
                    'prontuario' => true,
                    'fotos_drive' => true,
                    'estoque_basico' => true,
                    'financeiro_basico' => true,
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_monthly_cents' => 9900,
                'price_yearly_cents' => 95040, // -20%
                'max_clinics' => 1,
                'max_patients' => 500,
                'max_users' => 5,
                'storage_gb' => 5,
                'features' => ['*'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly_cents' => 19900,
                'price_yearly_cents' => 191040,
                'max_clinics' => 3,
                'max_patients' => 2000,
                'max_users' => 15,
                'storage_gb' => 20,
                'features' => ['*'],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'price_monthly_cents' => 34900,
                'price_yearly_cents' => 335040,
                'max_clinics' => 10,
                'max_patients' => 10000,
                'max_users' => 50,
                'storage_gb' => 100,
                'features' => ['*'],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(['slug' => $planData['slug']], $planData);
        }
    }
}
