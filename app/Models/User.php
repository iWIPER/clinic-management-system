<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; // teams feature ativa

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Todas as clínicas que o usuário participa (N:N)
     * Importante: NUNCA adicionar clinic_id direto em users.
     */
    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
                    ->withPivot('role', 'drive_doctor_folder_id')
                    ->withTimestamps();
    }

    /**
     * Clínica ativa atual (geralmente setada no middleware de tenant)
     */
    public function currentClinic()
    {
        // Implementação simples via session ou contexto
        $clinicId = session('current_clinic_id');
        return $clinicId ? $this->clinics()->where('clinics.id', $clinicId)->first() : null;
    }

    /**
     * Papel do usuário na clínica atual
     */
    public function roleInCurrentClinic(): ?string
    {
        $clinic = $this->currentClinic();
        if (!$clinic) return null;
        return $this->clinics()->where('clinics.id', $clinic->id)->first()?->pivot->role;
    }

    public function isOwnerOf(Clinic $clinic): bool
    {
        return $this->clinics()
            ->where('clinics.id', $clinic->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }
}
