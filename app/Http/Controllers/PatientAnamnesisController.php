<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisActivityLog;
use App\Models\AnamnesisInstance;
use App\Models\AnamnesisQuestion;
use App\Models\Patient;
use App\Services\Anamnesis\AnamnesisPdfService;
use App\Services\Anamnesis\AnamnesisService;
use App\Services\Anamnesis\QuestionBankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PatientAnamnesisController extends Controller
{
    public function __construct(
        private AnamnesisService $service,
        private AnamnesisPdfService $pdfService,
    ) {}

    public function create(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:anamnesis_templates,id',
        ]);

        $instance = $this->service->createInstance(
            $patient,
            (int) $validated['template_id'],
            (int) auth()->id(),
            $request
        );

        return redirect()->route('patients.anamneses.edit', [$patient, $instance]);
    }

    public function edit(Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        return Inertia::render('Anamneses/Edit', [
            'patient' => $patient->only(['id', 'nome', 'sobrenome']),
            'editor' => $this->service->loadEditorData($anamnesis),
        ]);
    }

    public function show(Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        return Inertia::render('Anamneses/Show', [
            'patient' => $patient->only(['id', 'nome', 'sobrenome']),
            'editor' => $this->service->loadEditorData($anamnesis),
        ]);
    }

    public function saveAnswers(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Esta anamnese já foi assinada e não pode ser editada.'], 422);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            // Cobre tanto pergunta do banco (clinic_id nulo ou da própria
            // clínica) quanto pergunta específica desta instância — nunca
            // exclui por instance_id, senão rejeitaria respostas legítimas
            // de perguntas adicionadas via addInstanceQuestion().
            'answers.*.question_id' => ['required', \Illuminate\Validation\Rule::exists('anamnesis_questions', 'id')->where(
                fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $anamnesis->clinic_id)
            )],
            'answers.*.value' => 'nullable|string',
            'answers.*.supplementary_text' => 'nullable|string',
            'answers.*.file_path' => 'nullable|string',
            'complete' => 'boolean',
        ]);

        $instance = $this->service->saveAnswers(
            $anamnesis,
            $validated['answers'],
            (int) auth()->id(),
            $request
        );

        if ($request->boolean('complete')) {
            $instance = $this->service->complete($instance, (int) auth()->id(), $request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'instance' => [
                    'id' => $instance->id,
                    'status' => $instance->status,
                    'progress' => $instance->progress,
                ],
            ]);
        }

        return back()->with('success', 'Anamnese salva.');
    }

    public function toggleQuestion(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Esta anamnese já foi assinada.'], 422);
        }

        $request->validate(['question_id' => 'required|integer']);
        $questionId = $request->integer('question_id');

        $disabled = $anamnesis->disabled_question_ids ?? [];

        if (in_array($questionId, $disabled)) {
            $disabled = array_values(array_filter($disabled, fn ($id) => $id !== $questionId));
        } else {
            $disabled[] = $questionId;
        }

        $anamnesis->update(['disabled_question_ids' => $disabled]);

        return response()->json(['disabled_question_ids' => $disabled]);
    }

    public function addInstanceQuestion(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Esta anamnese já foi assinada.'], 422);
        }

        $validated = $request->validate([
            'text'        => 'required|string|max:500',
            'category'    => 'required|string|max:100',
            'type'        => 'required|in:text,yes_no,yes_no_text,yes_no_unknown,yes_no_unknown_text',
            'is_required' => 'boolean',
            'is_active'   => 'boolean',
        ]);

        $question = AnamnesisQuestion::create([
            'clinic_id'    => $anamnesis->clinic_id,
            'instance_id'  => $anamnesis->id,
            'category'     => strtoupper($validated['category']),
            'text'         => $validated['text'],
            'type'         => $validated['type'],
            'is_required'  => $validated['is_required'] ?? false,
            'is_active'    => $validated['is_active'] ?? true,
            'has_alert'    => false,
            'show_on_patient_card' => false,
            'question_hash' => hash('sha256', mb_strtolower(trim($validated['text'])) . '_inst_' . $anamnesis->id . '_' . time()),
        ]);

        $qb = app(QuestionBankService::class);

        return response()->json([
            'question' => array_merge($qb->serializeForFill($question), [
                'value' => null,
                'supplementary_text' => null,
                'file_path' => null,
                'is_disabled' => false,
                'is_instance_question' => true,
            ]),
        ]);
    }

    public function renameInstance(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Esta anamnese já foi assinada.'], 422);
        }

        $validated = $request->validate(['name' => 'required|string|max:120']);
        $instance = $this->service->rename($anamnesis, $validated['name'], (int) auth()->id(), $request);

        return response()->json([
            'custom_name' => $instance->custom_name,
            'display_name' => $instance->displayName(),
        ]);
    }

    public function updateDate(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Esta anamnese já foi assinada.'], 422);
        }

        $validated = $request->validate(['anamnesis_date' => 'required|date']);
        $instance = $this->service->updateDate($anamnesis, $validated['anamnesis_date'], (int) auth()->id(), $request);

        return response()->json([
            'anamnesis_date' => $instance->effectiveDate()->toIso8601String(),
        ]);
    }

    public function duplicate(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        $copy = $this->service->duplicateInstance($anamnesis, (int) auth()->id(), $request);

        return redirect()->route('patients.anamneses.edit', [$patient, $copy])
            ->with('success', 'Anamnese duplicada.');
    }

    public function pdf(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        $this->authorize('view', $patient);
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        $path = $this->pdfService->generate($anamnesis, (int) auth()->id(), $request);

        return Storage::disk('public')->download(
            $path,
            'anamnese-' . $patient->id . '-' . $anamnesis->id . '.pdf'
        );
    }

    public function destroy(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        $anamnesis->load(['patientSignature', 'dentistSignature']);
        $hasPatient = $anamnesis->patientSignature !== null;
        $hasDentist = $anamnesis->dentistSignature !== null;

        // Paciente assinou → bloqueado
        if ($hasPatient) {
            return response()->json([
                'error' => 'Esta anamnese já foi assinada pelo paciente. Por segurança jurídica, ela não pode ser excluída.',
            ], 422);
        }

        AnamnesisActivityLog::create([
            'clinic_id'   => $anamnesis->clinic_id,
            'instance_id' => $anamnesis->id,
            'patient_id'  => $anamnesis->patient_id,
            'template_id' => $anamnesis->template_id,
            'action'      => 'deleted',
            'user_id'     => auth()->id(),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'had_dentist_signature' => $hasDentist,
                'display_name'          => $anamnesis->displayName(),
            ],
        ]);

        // Apaga arquivos de assinatura do dentista, se houver
        if ($hasDentist && $anamnesis->dentistSignature->signature_path) {
            Storage::disk('public')->delete($anamnesis->dentistSignature->signature_path);
        }

        $anamnesis->delete();

        return response()->json(['success' => true]);
    }
}