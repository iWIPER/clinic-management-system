<?php

namespace App\Models\Concerns;

use App\Scopes\ClinicScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClinic
{
    protected static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope());

        static::creating(function ($model) {
            if (empty($model->clinic_id) && session()->has('current_clinic_id')) {
                $model->clinic_id = session('current_clinic_id');
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Clinic::class);
    }
}
