<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientMarkerService
{
    /**
     * Paleta fixa inspirada na Codental — círculos clicáveis, sem hex livre.
     * Fonte de verdade da validação server-side (PatientMarkerController usa
     * esta constante em Rule::in()). O front tem sua própria cópia em
     * MarkerColorPicker.vue — não dá pra compartilhar um array literal entre
     * PHP e JS sem uma API própria só pra isso, desproporcional para 8 hex.
     */
    public const PALETTE = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];

    /**
     * Fonte de verdade do limite de marcadores por paciente — usada pela
     * validação em PatientMarkerController::sync() e exposta ao front via
     * prop (PatientController) para o contador/estado desabilitado do popover.
     */
    public const MAX_MARKERS_PER_PATIENT = 6;

    /**
     * Usado tanto pelo pop-up "Categorizar" (atribuir/remover) quanto pelo
     * modal "Gerenciar Marcadores" (criar/editar/excluir) — uma única
     * consulta, patients_count já incluído para a confirmação de exclusão.
     */
    public function availableMarkers(?int $clinicId): array
    {
        return PatientTag::query()
            ->forClinic($clinicId)
            ->markers()
            ->withCount('patients')
            ->orderBy('name')
            ->get()
            ->map(fn (PatientTag $tag) => [
                'id'             => $tag->id,
                'name'           => $tag->name,
                'slug'           => $tag->slug,
                'color'          => $tag->color,
                'is_system'      => $tag->is_system,
                'patients_count' => $tag->patients_count,
            ])
            ->toArray();
    }

    public function syncForPatient(Patient $patient, array $markerIds): void
    {
        $patient->markers()->sync($markerIds);
    }

    /**
     * Único ponto que decide se um nome de marcador está disponível na
     * clínica — reaproveitado por create() e update() para não duplicar a
     * checagem de unicidade de slug.
     */
    private function ensureSlugAvailable(?int $clinicId, string $slug, ?int $excludeId = null): void
    {
        $taken = PatientTag::query()
            ->forClinic($clinicId)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Já existe um marcador ou categoria com esse nome.']);
        }
    }

    public function create(?int $clinicId, string $name, string $color): PatientTag
    {
        $slug = Str::slug($name);
        $this->ensureSlugAvailable($clinicId, $slug);

        return PatientTag::create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'is_patient_marker' => true,
        ]);
    }

    /**
     * $name null = só a cor muda (caso de marcador de sistema, que não pode
     * ser renomeado). Com $name preenchido, também atualiza nome/slug —
     * único método para os dois cenários da seção 3, em vez de duplicar a
     * lógica de update em dois métodos separados.
     */
    public function update(PatientTag $marker, ?string $name, string $color): PatientTag
    {
        $attributes = ['color' => $color];

        if ($name !== null) {
            $slug = Str::slug($name);
            $this->ensureSlugAvailable($marker->clinic_id, $slug, $marker->id);
            $attributes['name'] = $name;
            $attributes['slug'] = $slug;
        }

        $marker->update($attributes);

        return $marker;
    }

    /**
     * As FKs de patient_marker_assignments e patient_note_tag já têm
     * cascadeOnDelete() — o banco desfaz as duas associações sozinho. A
     * transação aqui é reforço explícito, não o que garante a consistência.
     */
    public function delete(PatientTag $marker): void
    {
        DB::transaction(function () use ($marker) {
            $marker->delete();
        });
    }
}
