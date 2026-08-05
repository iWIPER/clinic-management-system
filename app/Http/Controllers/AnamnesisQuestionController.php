<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisQuestion;
use App\Services\Anamnesis\QuestionBankService;
use Illuminate\Http\Request;

class AnamnesisQuestionController extends Controller
{
    public function __construct(private QuestionBankService $service) {}

    public function index(Request $request)
    {
        $clinicId = session('current_clinic_id');

        return response()->json([
            'questions' => $this->service->search(
                $clinicId,
                $request->input('q'),
                $request->input('category'),
                $request->input('type'),
            ),
            'categories' => $this->service->categories($clinicId),
            'types' => collect(\App\Enums\Anamnesis\QuestionType::cases())
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuestion($request);
        $template = null;
        if ($request->filled('template_id')) {
            $template = \App\Models\AnamnesisTemplate::findOrFail($request->integer('template_id'));
        }

        $question = $this->service->store($validated, session('current_clinic_id'), $template);

        return response()->json(['question' => $this->service->serializeQuestion($question)]);
    }

    public function update(Request $request, AnamnesisQuestion $question)
    {
        $this->authorizeQuestion($question);
        $validated = $this->validateQuestion($request);
        $question = $this->service->update($question, $validated, session('current_clinic_id'));

        return response()->json(['question' => $this->service->serializeQuestion($question)]);
    }

    public function duplicate(AnamnesisQuestion $question)
    {
        $this->authorizeQuestion($question);
        $copy = $this->service->duplicate($question);

        return response()->json(['question' => $this->service->serializeQuestion($copy)]);
    }

    public function deactivate(AnamnesisQuestion $question)
    {
        $this->authorizeQuestion($question);
        $question = $this->service->toggleActive($question);

        return response()->json(['question' => $this->service->serializeQuestion($question)]);
    }

    public function toggleActive(AnamnesisQuestion $question)
    {
        $this->authorizeQuestion($question);
        $question = $this->service->toggleActive($question);

        return response()->json(['question' => $this->service->serializeQuestion($question)]);
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'text' => 'required|string|max:500',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'type' => 'required|in:text,yes_no,yes_no_text,yes_no_unknown,yes_no_unknown_text',
            'is_required' => 'boolean',
            'has_alert' => 'boolean',
            'alert_text' => 'nullable|string|max:150',
            'show_on_patient_card' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }

    private function authorizeQuestion(AnamnesisQuestion $question): void
    {
        $clinicId = session('current_clinic_id');
        if ($question->clinic_id && $question->clinic_id !== $clinicId) {
            abort(403);
        }
    }
}