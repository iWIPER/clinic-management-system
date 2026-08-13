<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    // A migration original criou a tabela no singular ("subscription_history"),
    // divergindo da convenção plural do Eloquent para este model — sem isso,
    // toda gravação aqui falha (tabela "subscription_histories" não existe).
    protected $table = 'subscription_history';

    public $timestamps = false;

    protected $fillable = [
        'subscription_id', 'event', 'plan_id_from', 'plan_id_to', 'notes', 'created_by',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function planFrom(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id_from');
    }

    public function planTo(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
