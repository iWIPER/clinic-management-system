<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisTemplate;
use App\Services\Anamnesis\QuestionBankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AnamnesisTemplateController extends Controller
{
    public function __construct(private QuestionBankService $questionBank) {}

    public function index()
    {
        $clinicId = session('current_clinic_id');

        $templates = AnamnesisTemplate::query()
            ->forClinic($clinicId)
            ->withCount('templateQuestions')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AnamnesisTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'is_system' => $t->is_system,
                'is_active' => $t->is_active,
                'sort_order' => $t->sort_order,
                'questions_count' => $t->template_questions_count,
                'version' => $t->version,
                'is_default' => $t->is_default,
            ]);

        return Inertia::render('Anamneses/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Anamneses/Templates/Form', [
            'editor' => null,
            'bankCategories' => $this->questionBank->categories(session('current_clinic_id')),
            'types' => collect(\App\Enums\Anamnesis\QuestionType::cases())
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $clinicId = session('current_clinic_id');

        $template = AnamnesisTemplate::create([
            'clinic_id' => $clinicId,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (AnamnesisTemplate::forClinic($clinicId)->max('sort_order') ?? 0) + 1,
            'created_by_id' => auth()->id(),
        ]);

        return redirect()->route('anamnesis-templates.edit', $template)
            ->with('success', 'Modelo criado.');
    }

    public function edit(AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        $clinicId = session('current_clinic_id');

        return Inertia::render('Anamneses/Templates/Form', [
            'editor' => $this->questionBank->templateEditorPayload($anamnesisTemplate, $clinicId),
            'bankCategories' => app(\App\Services\Anamnesis\CategoryDefinitionService::class)->listForClinic($clinicId, false),
            'types' => collect(\App\Enums\Anamnesis\QuestionType::cases())
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
        ]);
    }

    public function update(Request $request, AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'question_order' => 'array',
            'question_order.*' => ['integer', $this->questionExistsRule()],
        ]);

        $anamnesisTemplate->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? $anamnesisTemplate->is_active,
            'version' => $anamnesisTemplate->version + 1,
        ]);

        if (! empty($validated['question_order'])) {
            $this->questionBank->reorderTemplateQuestions($anamnesisTemplate, $validated['question_order']);
        }

        return back()->with('success', 'Modelo atualizado.');
    }

    public function attachQuestion(Request $request, AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        $validated = $request->validate([
            'question_id' => ['required', $this->questionExistsRule()],
            'is_required' => 'boolean',
        ]);

        $this->questionBank->attachToTemplate(
            $anamnesisTemplate,
            (int) $validated['question_id'],
            null,
            (bool) ($validated['is_required'] ?? false)
        );

        return back()->with('success', 'Pergunta adicionada ao modelo.');
    }

    public function detachQuestion(AnamnesisTemplate $anamnesisTemplate, int $questionId)
    {
        $this->authorizeTemplate($anamnesisTemplate);
        $this->questionBank->detachFromTemplate($anamnesisTemplate, $questionId);

        return back()->with('success', 'Pergunta removida do modelo.');
    }

    public function duplicate(AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        $clinicId = session('current_clinic_id');

        $copy = DB::transaction(function () use ($anamnesisTemplate, $clinicId) {
            $new = $anamnesisTemplate->replicate(['slug']);
            $new->clinic_id = $clinicId;
            $new->name = $anamnesisTemplate->name . ' (cópia)';
            $new->slug = Str::slug($new->name) . '-' . Str::random(4);
            $new->is_system = false;
            $new->created_by_id = auth()->id();
            $new->save();

            foreach ($anamnesisTemplate->templateQuestions as $link) {
                $this->questionBank->attachToTemplate(
                    $new,
                    $link->question_id,
                    $link->sort_order,
                    $link->is_required
                );
            }

            return $new;
        });

        return redirect()->route('anamnesis-templates.edit', $copy)
            ->with('success', 'Modelo duplicado.');
    }

    public function destroy(AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        if ($anamnesisTemplate->is_system) {
            return back()->with('error', 'Modelos do sistema não podem ser excluídos.');
        }

        $anamnesisTemplate->delete();

        return redirect()->route('anamnesis-templates.index')
            ->with('success', 'Modelo excluído.');
    }

    public function deactivate(AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);
        $anamnesisTemplate->update(['is_active' => false]);

        return back()->with('success', 'Modelo desativado.');
    }

    public function setDefault(AnamnesisTemplate $anamnesisTemplate)
    {
        $this->authorizeTemplate($anamnesisTemplate);
        $clinicId = session('current_clinic_id');

        AnamnesisTemplate::query()
            ->forClinic($clinicId)
            ->update(['is_default' => false]);

        $anamnesisTemplate->update(['is_default' => true, 'is_active' => true]);

        return back()->with('success', 'Modelo definido como padrão.');
    }

    public function moveQuestion(Request $request, AnamnesisTemplate $anamnesisTemplate, int $questionId)
    {
        $this->authorizeTemplate($anamnesisTemplate);

        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $this->questionBank->moveQuestion($anamnesisTemplate, $questionId, $validated['direction']);

        return back();
    }

    private function authorizeTemplate(AnamnesisTemplate $template): void
    {
        $clinicId = session('current_clinic_id');

        if ($template->clinic_id && $template->clinic_id !== $clinicId) {
            abort(403);
        }
    }

    // Mesmo filtro de AnamnesisQuestion::scopeForClinic() — sem isto, uma
    // pergunta PRIVADA de outra clínica poderia ser anexada a um modelo
    // desta clínica e exibida pros pacientes dela.
    private function questionExistsRule(): \Illuminate\Validation\Rules\Exists
    {
        $clinicId = session('current_clinic_id');

        return \Illuminate\Validation\Rule::exists('anamnesis_questions', 'id')->where(
            fn ($q) => $q->whereNull('instance_id')->where(
                fn ($q2) => $q2->whereNull('clinic_id')->orWhere('clinic_id', $clinicId)
            )
        );
    }
}