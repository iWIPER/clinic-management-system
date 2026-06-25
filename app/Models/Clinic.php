<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'trade_name',
        'slogan',
        'logo_path',
        'slug',
        'type',           // odontologia, medicina, etc.
        'cnpj',
        'status',         // active, suspended, trial
        'plan_id',
        'subscription_id', // Cashier
        'settings',
        'google_connected_at',
        'storage_disclaimer_confirmed_at',
    ];

    protected $casts = [
        'settings'                        => 'array',
        'google_connected_at'             => 'datetime',
        'storage_disclaimer_confirmed_at' => 'datetime',
    ];

    /**
     * Usuários da clínica (N:N via clinic_user)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinic_user')
                    ->withPivot('role', 'drive_doctor_folder_id')
                    ->withTimestamps();
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function storageConnection()
    {
        return $this->hasOne(ClinicStorageConnection::class);
    }

    // Helper para saber se o usuário atual é owner
    public function owner()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }
}
