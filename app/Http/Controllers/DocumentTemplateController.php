<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentPlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocumentTemplateController extends Controller
{
    public function __construct(private DocumentPlaceholderResolver $resolver) {}

    public function index()
    {
        $clinicId = session('current_clinic_id');

        $templates = DocumentTemplate::query()
            ->forClinic($clinicId)
            ->active()
            ->with(['category', 'currentVersion'])
            ->withCount('documents')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (DocumentTemplate $t) => $t->category?->name ?? 'Sem categoria');

        return Inertia::render('Documents/Templates/Index', [
            'templatesByCategory' => $templates,
        ]);
    }

    public function create(Request $request)
    {
        $clinicId = session('current_clinic_id');

        return Inertia::render('Documents/Templates/Editor', [
            'template'     => null,
            'categoryId'   => $request->integer('category_id') ?: null,
            'categories'   => DocumentCategory::query()->active()->forClinic($clinicId)->orderBy('sort_order')->get(['id', 'name']),
            'placeholders' => $this->resolver->availablePlaceholders(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $clinicId = session('current_clinic_id');

        $template = DocumentTemplate::create([
            'clinic_id'                       => $clinicId,
            'category_id'                     => $validated['category_id'],
            'name'                             => $validated['name'],
            'slug'                             => Str::slug($validated['name']) . '-' . Str::random(5),
            'description'                      => $validated['description'] ?? null,
            'requires_patient_signature'       => $validated['requires_patient_signature'] ?? true,
            'requires_professional_signature'  => $validated['requires_professional_signature'] ?? false,
            'requires_responsible_signature'   => $validated['requires_responsible_signature'] ?? false,
            'requires_witness_signature'       => $validated['requires_witness_signature'] ?? false,
            'signature_expiration_hours'       => $validated['signature_expiration_hours'] ?? 72,
            'created_by_id'                    => $request->user()->id,
        ]);

        $template->createNewVersion($validated['name'], $validated['content_html'], 'Criação do modelo', $request->user()->id);

        return redirect()->route('document-templates.edit', $template)->with('success', 'Modelo criado com sucesso.');
    }

    public function edit(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load(['currentVersion', 'category', 'versions.createdBy']);
        $clinicId = session('current_clinic_id');

        return Inertia::render('Documents/Templates/Editor', [
            'template' => [
                'id'                               => $documentTemplate->id,
                'name'                             => $documentTemplate->name,
                'description'                      => $documentTemplate->description,
                'category_id'                      => $documentTemplate->category_id,
                'content_html'                     => $documentTemplate->currentVersion?->content_html ?? '',
                'version'                          => $documentTemplate->currentVersion?->version,
                'requires_patient_signature'       => $documentTemplate->requires_patient_signature,
                'requires_professional_signature'  => $documentTemplate->requires_professional_signature,
                'requires_responsible_signature'   => $documentTemplate->requires_responsible_signature,
                'requires_witness_signature'       => $documentTemplate->requires_witness_signature,
                'signature_expiration_hours'       => $documentTemplate->signature_expiration_hours,
                'is_system'                        => $documentTemplate->is_system,
                'is_default'                       => $documentTemplate->is_default,
                'versions' => $documentTemplate->versions->map(fn ($v) => [
                    'version'     => $v->version,
                    'title'       => $v->title,
                    'created_at'  => $v->created_at->format('d/m/Y H:i'),
                    'created_by'  => $v->createdBy?->name,
                ]),
            ],
            'categories'   => DocumentCategory::query()->active()->forClinic($clinicId)->orderBy('sort_order')->get(['id', 'name']),
            'placeholders' => $this->resolver->availablePlaceholders(),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $this->validated($request);

        $documentTemplate->update([
            'category_id'                     => $validated['category_id'],
            'name'                             => $validated['name'],
            'description'                      => $validated['description'] ?? null,
            'requires_patient_signature'       => $validated['requires_patient_signature'] ?? true,
            'requires_professional_signature'  => $validated['requires_professional_signature'] ?? false,
            'requires_responsible_signature'   => $validated['requires_responsible_signature'] ?? false,
            'requires_witness_signature'       => $validated['requires_witness_signature'] ?? false,
            'signature_expiration_hours'       => $validated['signature_expiration_hours'] ?? 72,
        ]);

        $currentContent = $documentTemplate->currentVersion?->content_html;
        if ($currentContent !== $validated['content_html']) {
            $documentTemplate->createNewVersion(
                $validated['name'],
                $validated['content_html'],
                $request->input('change_summary'),
                $request->user()->id
            );
        }

        return back()->with('success', 'Modelo atualizado.');
    }

    public function duplicate(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load('currentVersion');
        $clinicId = session('current_clinic_id');

        $copy = DocumentTemplate::create([
            'clinic_id'                       => $clinicId,
            'category_id'                     => $documentTemplate->category_id,
            'name'                             => $documentTemplate->name . ' (cópia)',
            'slug'                             => Str::slug($documentTemplate->name) . '-copia-' . Str::random(5),
            'description'                      => $documentTemplate->description,
            'requires_patient_signature'       => $documentTemplate->requires_patient_signature,
            'requires_professional_signature'  => $documentTemplate->requires_professional_signature,
            'requires_responsible_signature'   => $documentTemplate->requires_responsible_signature,
            'requires_witness_signature'       => $documentTemplate->requires_witness_signature,
            'signature_expiration_hours'       => $documentTemplate->signature_expiration_hours,
            'is_system'                        => false,
            'created_by_id'                    => request()->user()->id,
        ]);

        $copy->createNewVersion(
            $copy->name,
            $documentTemplate->currentVersion?->content_html ?? '',
            'Duplicado a partir de "' . $documentTemplate->name . '"',
            request()->user()->id
        );

        return redirect()->route('document-templates.edit', $copy)->with('success', 'Modelo duplicado.');
    }

    public function archive(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->update(['is_active' => false]);

        return back()->with('success', 'Modelo arquivado.');
    }

    public function setDefault(DocumentTemplate $documentTemplate)
    {
        DocumentTemplate::query()
            ->where('category_id', $documentTemplate->category_id)
            ->where('clinic_id', $documentTemplate->clinic_id)
            ->update(['is_default' => false]);

        $documentTemplate->update(['is_default' => true]);

        return back()->with('success', 'Modelo definido como padrão.');
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->documents()->exists()) {
            return back()->withErrors([
                'template' => 'Não é possível excluir: existem documentos emitidos com este modelo. Arquive-o em vez disso.',
            ]);
        }

        $documentTemplate->versions()->delete();
        $documentTemplate->delete();

        return redirect()->route('documents.index')->with('success', 'Modelo excluído.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id'                       => 'required|exists:document_categories,id',
            'name'                               => 'required|string|max:160',
            'description'                        => 'nullable|string|max:500',
            'content_html'                       => 'required|string',
            'requires_patient_signature'         => 'boolean',
            'requires_professional_signature'    => 'boolean',
            'requires_responsible_signature'     => 'boolean',
            'requires_witness_signature'         => 'boolean',
            'signature_expiration_hours'         => 'nullable|integer|min:1|max:8760',
        ]);
    }
}
