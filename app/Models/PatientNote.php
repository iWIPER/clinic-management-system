<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PatientNote extends Model
{
    use BelongsToClinic;

    // Só é relevante quando is_alert = true; is_alert sozinho continua sendo
    // o que decide se a nota aparece no badge do topo do perfil.
    public const PRIORITIES = [
        'critico'     => 'Crítico',
        'atencao'     => 'Atenção',
        'informativo' => 'Informativo',
    ];

    // Origem da observação — não exposta na UI (ver PatientNoteService::mapNote).
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CADASTRO = 'cadastro';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'author_id',
        'title',
        'description',
        'color',
        'is_pinned',
        'is_alert',
        'priority',
        'source',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_alert' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Sem filtro por is_patient_marker: notas antigas podem ter categorias
    // legadas anexadas (continuam exibidas), e notas novas podem anexar
    // marcadores ativos — a restrição de quais ids são elegíveis para uma
    // NOVA seleção fica na validação (PatientTag::markerExistsRule()), não
    // na relação de leitura.
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PatientTag::class, 'patient_note_tag');
    }
}