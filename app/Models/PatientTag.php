<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PatientTag extends Model
{

    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'color',
        'is_system',
        'is_patient_marker',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_patient_marker' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(PatientNote::class, 'patient_note_tag');
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_marker_assignments');
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_tag_assignments');
    }

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $query->where(function ($q) use ($clinicId) {
            $q->whereNull('clinic_id');
            if ($clinicId) {
                $q->orWhere('clinic_id', $clinicId);
            }
        });
    }

    /**
     * is_patient_marker identifica o vocabulário ativo de marcadores — a
     * única lista de onde tanto o paciente quanto as observações selecionam.
     * Registros com is_patient_marker=false são categorias legadas de notas
     * antigas: continuam exibidas onde já estão anexadas, mas não aparecem
     * mais como opção de seleção em lugar nenhum. Único ponto que conhece
     * essa coluna — todo o resto do código consulta via markers() ou
     * markerExistsRule(), nunca a coluna direto.
     */
    public function scopeMarkers($query)
    {
        return $query->where('is_patient_marker', true);
    }

    /**
     * Regra de validação reaproveitada por PatientNoteController (tag_ids)
     * e PatientMarkerController (marker_ids) — ambos só podem referenciar
     * marcadores ativos, nunca uma categoria legada nem um id qualquer.
     */
    public static function markerExistsRule(): Exists
    {
        return Rule::exists('patient_tags', 'id')->where('is_patient_marker', true);
    }
}