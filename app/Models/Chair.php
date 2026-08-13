<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recurso físico da clínica ("Cadeira 01", "Cadeira 02"...) — independente
 * do profissional. Um agendamento tem os dois, separadamente: profissional
 * (quem atende) e cadeira (onde acontece). Ver Appointment::chair().
 */
class Chair extends Model
{
    use BelongsToClinic;

    /**
     * Limite máximo de cadeiras por clínica — única fonte da verdade,
     * referenciada por ChairController::store() (autoridade real) e pelo
     * onboarding (validação de chairs_count). O frontend só espelha esse
     * número pra UX (esconder/desabilitar botões); nunca é ele quem decide.
     */
    public const MAX_PER_CLINIC = 6;

    protected $fillable = [
        'clinic_id',
        'name',
        'color',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public static function countForClinic(int $clinicId): int
    {
        return static::where('clinic_id', $clinicId)->count();
    }

    /**
     * Cria "Cadeira 01".."Cadeira 0N" para a clínica (usado pelo onboarding).
     * Idempotente: se a clínica já tem alguma cadeira, não cria nenhuma —
     * nunca duplica e nunca apaga o que já existe.
     */
    public static function seedDefaultsForClinic(int $clinicId, int $count): void
    {
        if (static::where('clinic_id', $clinicId)->exists()) {
            return;
        }

        $palette = ['#0d9488', '#2563eb', '#7c3aed', '#d97706', '#e11d48', '#0891b2'];
        $count = min($count, self::MAX_PER_CLINIC);

        for ($i = 1; $i <= $count; $i++) {
            static::create([
                'clinic_id' => $clinicId,
                'name' => sprintf('Cadeira %02d', $i),
                'color' => $palette[($i - 1) % count($palette)],
            ]);
        }
    }
}
