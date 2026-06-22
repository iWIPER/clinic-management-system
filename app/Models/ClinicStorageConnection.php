<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicStorageConnection extends Model
{
    protected $table = 'clinic_storage_connections';

    protected $fillable = [
        'clinic_id',
        'provider',           // google
        'refresh_token',      // criptografado
        'access_token',       // temporário
        'expires_at',
        'status',             // connected, revoked, error
    ];

    protected $hidden = [
        'refresh_token',
        'access_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
