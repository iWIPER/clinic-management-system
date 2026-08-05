<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    protected $fillable = ['stripe_event_id', 'type', 'status', 'payload', 'error'];

    protected $casts = ['payload' => 'array'];
}
