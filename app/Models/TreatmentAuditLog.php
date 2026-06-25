<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class TreatmentAuditLog extends Model
{
    use BelongsToClinic;

    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'treatment_id',
        'user_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'created' => 'Criação',
        'updated' => 'Edição',
        'deactivated' => 'Desativação',
        'reactivated' => 'Reativação',
        'deleted' => 'Exclusão',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}