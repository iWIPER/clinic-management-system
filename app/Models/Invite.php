<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invite extends Model
{
    protected $fillable = [
        'clinic_id',
        'email',
        'role',
        'token',
        'expires_at',
        'invited_by_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(32);
            }
            if (empty($invite->expires_at)) {
                $invite->expires_at = now()->addDays(7);
            }
        });
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function accept(User $user): void
    {
        // Attach user to clinic with the invited role
        $this->clinic->users()->attach($user->id, ['role' => $this->role]);

        // Optionally set current clinic
        session(['current_clinic_id' => $this->clinic_id]);

        $this->delete(); // consume the invite
    }
}
