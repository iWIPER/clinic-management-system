<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $fillable = ['plan_id', 'feature_key', 'feature_label', 'feature_value', 'included', 'sort_order'];

    protected $casts = ['included' => 'boolean'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
