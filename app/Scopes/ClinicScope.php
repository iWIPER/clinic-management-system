<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ClinicScope implements Scope
{
    /**
     * Aplica filtro automático por clínica ativa em todos os modelos que usam.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() || !Auth::check()) {
            return;
        }

        $clinicId = session('current_clinic_id');

        if ($clinicId) {
            $builder->where($model->getTable() . '.clinic_id', $clinicId);
        }
    }
}
