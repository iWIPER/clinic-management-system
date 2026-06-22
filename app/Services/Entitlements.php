<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Plan;

class Entitlements
{
    public static function check(Clinic $clinic, string $feature, int $currentCount = 0): bool
    {
        $plan = $clinic->plan ?? Plan::where('slug', 'start-gratis')->first();

        if (!$plan) {
            return false;
        }

        return match ($feature) {
            'patients' => $currentCount < ($plan->max_patients ?? 100),
            'users'    => $currentCount < ($plan->max_users ?? 1),
            'clinics'  => $currentCount < ($plan->max_clinics ?? 1),
            default    => true,
        };
    }

    public static function requiresUpgrade(Clinic $clinic, string $feature): bool
    {
        return !static::check($clinic, $feature);
    }
}
