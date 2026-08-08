<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskLabel extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'color',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $query->where(function ($q) use ($clinicId) {
            $q->whereNull('clinic_id');
            if ($clinicId) {
                $q->orWhere('clinic_id', $clinicId);
            }
        });
    }
}
