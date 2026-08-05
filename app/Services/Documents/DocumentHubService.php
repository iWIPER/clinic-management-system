<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Treatment;

/**
 * Agrega documentos emitidos e modelos disponíveis para a aba "Documentos" do
 * prontuário do paciente — mesmo papel que AnamnesisService::listForPatient()
 * cumpre para a aba de Anamneses.
 */
class DocumentHubService
{
    public function listForPatient(Patient $patient, int $perPage = 6, int $page = 1): array
    {
        $paginator = Document::query()
            ->where('patient_id', $patient->id)
            ->with(['template.category', 'professional:id,name'])
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(fn (Document $d) => [
            'id'              => $d->id,
            'template_name'   => $d->template_name,
            'category'        => $d->template?->category?->name,
            'status'          => $d->status,
            'status_label'    => $d->statusEnum()->label(),
            'status_color'    => $d->statusEnum()->color(),
            'status_icon'     => $d->statusEnum()->icon(),
            'document_code'   => $d->document_code,
            'professional'    => $d->professional?->name,
            'issued_at'       => $d->issued_at?->format('d/m/Y H:i'),
            'is_fully_signed' => $d->isFullySigned(),
            'pdf_available'   => (bool) $d->pdf_path,
        ])->values()->all();

        return [
            'data'       => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    public function availableTemplates(?int $clinicId): array
    {
        return DocumentTemplate::query()
            ->active()
            ->forClinic($clinicId)
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DocumentTemplate $t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'category'    => $t->category?->name,
                'category_id' => $t->category_id,
            ])
            ->all();
    }

    public function availableTreatments(?int $clinicId): array
    {
        return Treatment::query()
            ->where('clinic_id', $clinicId)
            ->active()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Treatment $t) => ['id' => $t->id, 'nome' => $t->nome])
            ->all();
    }
}
