<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralWallet extends Model
{
    const PIX_TYPES = ['cpf', 'cnpj', 'email', 'phone', 'random'];

    const PIX_TYPE_LABELS = [
        'cpf'    => 'CPF',
        'cnpj'   => 'CNPJ',
        'email'  => 'E-mail',
        'phone'  => 'Telefone',
        'random' => 'Chave aleatória',
    ];

    protected $fillable = [
        'clinic_id', 'affiliate_user_id', 'balance', 'pending_balance', 'total_earned',
        'total_withdrawn', 'last_payment_at', 'pix_type', 'pix_key',
    ];

    protected $casts = [
        'balance'          => 'float',
        'pending_balance'  => 'float',
        'total_earned'     => 'float',
        'total_withdrawn'  => 'float',
        'last_payment_at'  => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ReferralTransaction::class, 'wallet_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReferralPayment::class, 'wallet_id');
    }
}
