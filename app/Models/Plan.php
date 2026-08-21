<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'price_monthly_cents',
        'price_yearly_cents',
        'price_monthly',
        'price_yearly',
        'trial_days',
        'max_clinics',
        'max_patients',
        'max_users',
        'storage_gb',
        'is_free',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'is_free'             => 'boolean',
        'is_active'           => 'boolean',
        'is_featured'         => 'boolean',
        'price_monthly_cents' => 'integer',
        'price_yearly_cents'  => 'integer',
        'price_monthly'       => 'float',
        'price_yearly'        => 'float',
        'trial_days'          => 'integer',
        'max_clinics'         => 'integer',
        'max_patients'        => 'integer',
        'max_users'           => 'integer',
        'storage_gb'          => 'integer',
        'sort_order'          => 'integer',
    ];

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    // Não adicionar 'features' de volta a $casts/$fillable: a coluna JSON
    // legada `plans.features` foi substituída pela tabela relacional
    // plan_features, e um cast com o mesmo nome do método abaixo faz o
    // Eloquent resolver o atributo em vez de chamar a relação.
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceMonthlyFormatted(): string
    {
        $value = $this->price_monthly ?? ($this->price_monthly_cents / 100);

        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function priceYearlyFormatted(): string
    {
        $value = $this->price_yearly ?? ($this->price_yearly_cents / 100);

        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}