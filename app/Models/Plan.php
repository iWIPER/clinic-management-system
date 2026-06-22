<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'price_monthly_cents',
        'price_yearly_cents',
        'features',
        'max_clinics',
        'max_patients',
        'max_users',
        'storage_gb',
        'is_free',
    ];

    protected $casts = [
        'features' => 'array',
        'is_free' => 'boolean',
        'price_monthly_cents' => 'integer',
        'price_yearly_cents' => 'integer',
        'max_clinics' => 'integer',
        'max_patients' => 'integer',
        'max_users' => 'integer',
        'storage_gb' => 'integer',
    ];

    public function clinics()
    {
        return $this->hasMany(Clinic::class);
    }

    public function priceMonthlyFormatted(): string
    {
        return 'R$ ' . number_format($this->price_monthly_cents / 100, 2, ',', '.');
    }

    public function priceYearlyFormatted(): string
    {
        return 'R$ ' . number_format($this->price_yearly_cents / 100, 2, ',', '.');
    }
}
