<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicStorageConnection extends Model
{
    protected $table = 'clinic_storage_connections';

    protected $fillable = [
        'clinic_id',
        'provider',             // google
        'google_email',         // conta Google conectada
        'refresh_token',        // criptografado
        'access_token',         // criptografado (JSON do token completo)
        'expires_at',
        'status',               // connected, revoked, error
        'drive_root_folder_id', // ID da pasta Wildental no Drive
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
