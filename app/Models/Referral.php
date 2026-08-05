<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Referral extends Model
{
    protected $fillable = [
        'clinic_id', 'affiliate_user_id', 'code', 'clicks_count', 'conversions_count', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    /**
     * The Clinic or affiliate User that owns this referral link — exactly one is set.
     */
    public function owner(): Clinic|User|null
    {
        return $this->clinic ?? $this->affiliate;
    }

    public function ownerDisplayName(): string
    {
        return $this->clinic?->displayName() ?? $this->affiliate?->name ?? '—';
    }

    /**
     * clinic_id for AccessLog::record()'s clinicId param — null when this
     * referral belongs to a standalone affiliate (no clinic to attribute to).
     */
    public function loggableClinicId(): ?int
    {
        return $this->clinic_id;
    }

    public function belongsToOwner(Clinic|User $owner): bool
    {
        return $owner instanceof Clinic
            ? $this->clinic_id === $owner->id
            : $this->affiliate_user_id === $owner->id;
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ReferralConversion::class);
    }

    public function link(): string
    {
        return config('app.url') . '/r/' . $this->code;
    }

    public function conversionRate(): float
    {
        if ($this->clicks_count === 0) return 0.0;
        return round(($this->conversions_count / $this->clicks_count) * 100, 1);
    }

    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
        do {
            $part1 = '';
            $part2 = '';
            for ($i = 0; $i < 3; $i++) $part1 .= $chars[random_int(0, strlen($chars) - 1)];
            for ($i = 0; $i < 3; $i++) $part2 .= $chars[random_int(0, strlen($chars) - 1)];
            $code = $part1 . '-' . $part2;
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
