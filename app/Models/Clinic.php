<?php

namespace App\Models;

use App\Services\ClinicLogoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Clinic extends Model
{
    use HasFactory, Billable;

    protected $fillable = [
        'name',
        'trade_name',
        'slogan',
        'logo_path',
        'logo_type',
        'default_logo',
        'slug',
        'type',
        'cnpj',
        'city',
        'status',
        'plan_id',
        'subscription_id',
        'settings',
        'google_connected_at',
        'storage_disclaimer_confirmed_at',
        'phone',
        'email',
        'website',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zipcode',
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

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function referral()
    {
        return $this->hasOne(Referral::class);
    }

    public function referralWallet()
    {
        return $this->hasOne(ReferralWallet::class);
    }

    public function storageConnection()
    {
        return $this->hasOne(ClinicStorageConnection::class);
    }

    public function financialConnections(): HasMany
    {
        return $this->hasMany(ClinicFinancialConnection::class);
    }

    public function documentSettings()
    {
        return $this->hasOne(ClinicDocumentSetting::class);
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address_street && $this->address_number
                ? "{$this->address_street}, {$this->address_number}"
                : $this->address_street,
            $this->address_complement,
            $this->address_neighborhood,
            $this->address_city && $this->address_state
                ? "{$this->address_city}/{$this->address_state}"
                : $this->address_city,
            $this->address_zipcode,
        ]);

        return implode(' — ', $parts);
    }

    // Helper para saber se o usuário atual é owner
    public function owner()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->name;
    }

    public function logoUrl(): string
    {
        return ClinicLogoService::url($this);
    }

    public function toSessionPayload(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->displayName(),
            'trade_name' => $this->trade_name,
            'type'       => $this->type,
            'logo_url'   => $this->logoUrl(),
        ];
    }
}
