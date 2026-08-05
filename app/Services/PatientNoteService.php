<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientNote;
use Illuminate\Support\Facades\DB;

class PatientNoteService
{
    private function mapNote(PatientNote $n): array
    {
        return [
            'id'           => $n->id,
            'title'        => $n->title,
            'description'  => $n->description,
            'color'        => $n->color,
            'is_pinned'    => $n->is_pinned,
            'is_alert'     => $n->is_alert,
            'priority'     => $n->priority,
            'author'       => $n->author?->name,
            'date'         => $n->created_at->format('d/m/Y'),
            'time'         => $n->created_at->format('H:i'),
            'edited'       => $n->updated_at->ne($n->created_at),
            'updated_date' => $n->updated_at->format('d/m/Y'),
            'updated_time' => $n->updated_at->format('H:i'),
            'tags'         => $n->tags->map(fn ($t) => [
                'id'    => $t->id,
                'name'  => $t->name,
                'color' => $t->color,
            ])->values()->all(),
        ];
    }

    public function listForPatient(Patient $patient, int $perPage = 3, int $page = 1): array
    {
        $paginator = PatientNote::query()
            ->where('patient_id', $patient->id)
            ->with(['author:id,name', 'tags:id,name,color,slug'])
            ->orderByDesc('is_pinned')
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data'       => $paginator->getCollection()->map(fn ($n) => $this->mapNote($n))->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    public function alertNotes(Patient $patient): array
    {
        return PatientNote::query()
            ->where('patient_id', $patient->id)
            ->where('is_alert', true)
            ->with(['author:id,name', 'tags:id,name,color,slug'])
            ->orderByDesc('is_pinned')
            ->latest('created_at')
            ->get()
            ->map(fn ($n) => $this->mapNote($n))
            ->values()
            ->all();
    }

    /**
     * Observações relevantes para um contexto específico do sistema (ex:
     * "financeiro", "tratamentos"), com base no mapeamento categoria (tag) →
     * contexto definido em config/patient_notes.php.
     *
     * Arquitetura preparada, ainda sem consumidor: nenhum controller chama
     * este método hoje. Quando os módulos Financeiro/Tratamentos/Atendimento/
     * Orçamento tiverem um ponto de entrada pronto para exibir observações
     * contextuais, basta chamar `forContext($patient, 'financeiro', ...)` etc.
     */
    public function forContext(Patient $patient, string $context): array
    {
        $tagSlugs = config("patient_notes.contexts.{$context}", []);

        if (empty($tagSlugs)) {
            return [];
        }

        return PatientNote::query()
            ->where('patient_id', $patient->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs))
            ->with(['author:id,name', 'tags:id,name,color,slug'])
            ->orderByDesc('is_pinned')
            ->latest('created_at')
            ->get()
            ->map(fn ($n) => $this->mapNote($n))
            ->values()
            ->all();
    }

    /**
     * Nível só existe enquanto a nota for um alerta; sem is_alert, priority
     * é sempre null (evita estado inconsistente "prioridade sem alerta").
     */
    private function resolvePriority(array $data): ?string
    {
        if (! ($data['is_alert'] ?? false)) {
            return null;
        }

        return $data['priority'] ?? 'critico';
    }

    public function store(Patient $patient, array $data, int $authorId): PatientNote
    {
        return DB::transaction(function () use ($patient, $data, $authorId) {
            $note = PatientNote::create([
                'clinic_id' => $patient->clinic_id,
                'patient_id' => $patient->id,
                'author_id' => $authorId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#64748b',
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'is_alert' => (bool) ($data['is_alert'] ?? false),
                'priority' => $this->resolvePriority($data),
                'source' => $data['source'] ?? PatientNote::SOURCE_MANUAL,
            ]);

            if (! empty($data['tag_ids'])) {
                $note->tags()->sync($data['tag_ids']);
            }

            return $note->load(['author:id,name', 'tags']);
        });
    }

    public function update(PatientNote $note, array $data): PatientNote
    {
        return DB::transaction(function () use ($note, $data) {
            $note->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? $note->color,
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'is_alert' => (bool) ($data['is_alert'] ?? false),
                'priority' => $this->resolvePriority($data),
            ]);

            if (array_key_exists('tag_ids', $data)) {
                $note->tags()->sync($data['tag_ids'] ?? []);
            }

            return $note->fresh(['author:id,name', 'tags']);
        });
    }
}