<?php

namespace App\Http\Controllers;

use App\Enums\Documents\DocumentStatus;
use App\Models\Budget;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Treatment;
use App\Services\Documents\DocumentPdfService;
use App\Services\Documents\DocumentPlaceholderResolver;
use App\Services\Documents\DocumentStatusService;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PatientDocumentController extends Controller
{
    public function __construct(
        private DocumentPlaceholderResolver $resolver,
        private DocumentPdfService $pdfService,
    ) {}

    public function store(Request $request, Patient $patient)
    {
        $this->authorize('update', $patient);

        $validated = $request->validate([
            // DocumentTemplate não tem ClinicScope automático (clinic_id
            // nulo = modelo global de sistema, ver DocumentTemplatePolicy) —
            // precisa da mesma checagem explícita de scopeForClinic() aqui,
            // senão um template PRIVADO de outra clínica passaria.
            'template_id'  => ['required', \Illuminate\Validation\Rule::exists('document_templates', 'id')->where(
                fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $patient->clinic_id)
            )],
            'treatment_id' => 'nullable|exists:treatments,id',
            'budget_id'    => 'nullable|exists:budgets,id',
        ]);

        $template = DocumentTemplate::with('currentVersion')->findOrFail($validated['template_id']);

        if (! $template->currentVersion) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'template_id' => 'Este modelo ainda não possui conteúdo salvo.',
            ]);
        }

        $clinic = Clinic::find($patient->clinic_id);
        $professional = $request->user();
        $treatment = ! empty($validated['treatment_id']) ? Treatment::find($validated['treatment_id']) : null;
        $budget = ! empty($validated['budget_id']) ? Budget::find($validated['budget_id']) : null;

        $renderedHtml = $this->resolver->resolve($template->currentVersion->content_html, [
            'patient'      => $patient,
            'clinic'       => $clinic,
            'professional' => $professional,
            'treatment'    => $treatment,
            'budget'       => $budget,
        ]);

        // content_html do modelo já é sanitizado ao salvar (DocumentTemplate::
        // createNewVersion) — esta segunda passada é defesa em profundidade
        // contra o caso de um placeholder (ex.: nome do paciente) conter HTML,
        // já que resolve() concatena valores de dados sem escapar.
        $renderedHtml = HtmlSanitizer::richText($renderedHtml);

        $document = Document::create([
            'clinic_id'           => $patient->clinic_id,
            'patient_id'          => $patient->id,
            'template_id'         => $template->id,
            'template_version_id' => $template->current_version_id,
            'template_name'       => $template->name,
            'professional_id'     => $professional->id,
            'status'              => DocumentStatus::Issued->value,
            'rendered_html'       => $renderedHtml,
            'validation_token'    => bin2hex(random_bytes(32)),
            'issued_at'           => now(),
            'document_code'       => 'TEMP',
            'created_by_id'       => $professional->id,
        ]);

        $document->update([
            'document_code' => 'DOC-' . now()->format('Y') . '-' . str_pad((string) $document->id, 6, '0', STR_PAD_LEFT),
        ]);

        if (empty($template->requiredSignerRoles())) {
            $document->update(['status' => DocumentStatus::Completed->value, 'completed_at' => now()]);
        }

        if ($treatment) {
            $document->relatedTreatments()->attach($treatment->id);
        }
        if ($budget) {
            $document->relatedBudgets()->attach($budget->id);
        }

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'created',
            'user_id'     => $professional->id,
            'ip_address'  => $request->ip(),
        ]);

        return redirect()->route('patients.show', ['patient' => $patient, 'tab' => 'documents'])
            ->with('success', "Documento \"{$document->template_name}\" emitido com sucesso.");
    }

    public function show(Patient $patient, Document $document)
    {
        $this->authorize('view', $patient);
        abort_unless($document->patient_id === $patient->id, 404);

        $document->load(['signatures', 'template.category', 'professional', 'relatedTreatments', 'relatedBudgets']);

        return Inertia::render('Documents/Show', [
            'patient'  => $patient,
            'document' => [
                'id'             => $document->id,
                'template_name'  => $document->template_name,
                'category'       => $document->template?->category?->name,
                'status'         => $document->status,
                'status_label'   => $document->statusEnum()->label(),
                'status_color'   => $document->statusEnum()->color(),
                'document_code'  => $document->document_code,
                'rendered_html'  => $document->rendered_html,
                'pdf_url'        => $document->pdf_path ? route('patients.documents.file', [$patient, $document]) : null,
                'issued_at'      => $document->issued_at?->format('d/m/Y H:i'),
                'professional'   => $document->professional?->name,
                'required_roles' => $document->requiredSignerRoles(),
                'is_fully_signed' => $document->isFullySigned(),
                'related_treatments' => $document->relatedTreatments->map(fn ($t) => ['id' => $t->id, 'nome' => $t->nome]),
                'related_budgets'    => $document->relatedBudgets->map(fn ($b) => ['id' => $b->id, 'total' => $b->total]),
            ],
        ]);
    }

    public function pdf(Request $request, Patient $patient, Document $document)
    {
        $this->authorize('view', $patient);
        abort_unless($document->patient_id === $patient->id, 404);

        $this->pdfService->generate($document, $request->user()->id, $request);

        return Storage::disk('s3')->response($document->fresh()->pdf_path);
    }

    /**
     * Exibe o PDF já gerado sem regenerar (evita duplicar log de auditoria e
     * reenviar ao Drive a cada abertura de "Ver PDF" — ver pdf() acima para
     * o fluxo que gera/regenera).
     */
    public function file(Patient $patient, Document $document)
    {
        $this->authorize('view', $patient);
        abort_unless($document->patient_id === $patient->id, 404);
        abort_unless($document->pdf_path, 404);

        return Storage::disk('s3')->response($document->pdf_path);
    }

    public function cancel(Request $request, Patient $patient, Document $document, DocumentStatusService $statusService)
    {
        $this->authorize('update', $patient);
        abort_unless($document->patient_id === $patient->id, 404);

        $statusService->cancel($document, $request->input('reason', 'Cancelado pelo usuário.'), $request->user()?->id);

        return back()->with('success', 'Documento cancelado.');
    }

    public function destroy(Patient $patient, Document $document)
    {
        $this->authorize('update', $patient);
        abort_unless($document->patient_id === $patient->id, 404);
        abort_unless($document->status === DocumentStatus::Draft->value, 422, 'Somente rascunhos podem ser excluídos.');

        $document->delete();

        return back()->with('success', 'Rascunho excluído.');
    }
}
