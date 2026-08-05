<?php

namespace App\Services\Anamnesis;

use App\Enums\Anamnesis\InstanceStatus;
use App\Models\AnamnesisActivityLog;
use App\Models\AnamnesisAlert;
use App\Models\AnamnesisAnswer;
use App\Models\AnamnesisInstance;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Services\Anamnesis\CategoryDefinitionService;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnamnesisService
{
    public function __construct(
        private QuestionBankService $questionBank,
        private CategoryDefinitionService $categoryDefinitions,
    ) {}

    public function listForPatient(Patient $patient, int $perPage = 3, int $page = 1): array
    {
        $paginator = AnamnesisInstance::query()
            ->where('patient_id', $patient->id)
            ->with(['professional:id,name', 'patientSignature', 'dentistSignature'])
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(function (AnamnesisInstance $i) {
            $hasPatient = $i->patientSignature !== null;
            $hasDentist = $i->dentistSignature !== null;

            return [
                'id'                      => $i->id,
                'template_name'           => $i->template_name,
                'display_name'            => $i->displayName(),
                'professional'            => $i->professional?->name,
                'date'                    => $i->effectiveDate()->format('d/m/Y'),
                'time'                    => $i->effectiveDate()->format('H:i'),
                'status'                  => $i->status,
                'status_label'            => $i->statusEnum()->label(),
                'status_icon'             => $i->statusEnum()->icon(),
                'status_color'            => $i->statusEnum()->color(),
                'progress'                => $i->progress,
                'is_signed'               => $i->isSigned(),
                'is_fully_signed'         => $i->isFullySigned(),
                'has_patient_signature'   => $hasPatient,
                'has_dentist_signature'   => $hasDentist,
                'patient_signed_at'       => $i->patientSignature?->signed_at?->format('d/m/Y H:i'),
                'dentist_signed_at'       => $i->dentistSignature?->signed_at?->format('d/m/Y H:i'),
                'signed_at'               => $i->signed_at?->format('d/m/Y H:i'),
                'completed_at'            => $i->completed_at?->toIso8601String(),
            ];
        })->values()->all();

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
        return AnamnesisTemplate::query()
            ->active()
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
                'questions_count' => $t->template_questions_count,
            ])
            ->all();
    }

    public function createInstance(Patient $patient, int $templateId, int $professionalId, ?Request $request = null): AnamnesisInstance
    {
        $template = AnamnesisTemplate::query()
            ->active()
            ->forClinic($patient->clinic_id)
            ->findOrFail($templateId);

        $instance = AnamnesisInstance::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_version' => $template->version,
            'professional_id' => $professionalId,
            'status' => InstanceStatus::Draft->value,
            'progress' => 0,
            'started_at' => now(),
            'anamnesis_date' => now(),
            'validation_token' => bin2hex(random_bytes(32)),
        ]);

        $this->log($instance, 'created', $professionalId, $request);

        return $instance;
    }

    public function duplicateInstance(AnamnesisInstance $source, int $professionalId, ?Request $request = null): AnamnesisInstance
    {
        return DB::transaction(function () use ($source, $professionalId, $request) {
            $copy = $this->createInstance(
                $source->patient,
                $source->template_id,
                $professionalId,
                $request
            );

            $source->load('answers');

            foreach ($source->answers as $answer) {
                AnamnesisAnswer::create([
                    'instance_id' => $copy->id,
                    'question_id' => $answer->question_id,
                    'question_text' => $answer->question_text,
                    'question_type' => $answer->question_type,
                    'value' => $answer->value,
                    'supplementary_text' => $answer->supplementary_text,
                    'file_path' => $answer->file_path,
                ]);
            }

            $this->syncAlertsAndProgress($copy);
            $this->log($copy, 'duplicated', $professionalId, $request, ['source_id' => $source->id]);

            return $copy->fresh(['answers', 'professional']);
        });
    }

    public function loadEditorData(AnamnesisInstance $instance): array
    {
        $instance->load([
            'answers',
            'professional:id,name',
            'template.templateQuestions.question',
            'patientSignature',
            'dentistSignature',
        ]);

        if (! $instance->template) {
            return [
                'instance'          => $this->serializeInstanceMeta($instance),
                'signature'         => null,
                'dentist_signature' => null,
                'categories'        => [],
            ];
        }

        $disabledIds = $instance->disabled_question_ids ?? [];

        $answeredIds = $instance->answers->pluck('question_id');
        $templateQuestions = $this->resolveQuestionsForInstance($instance, $answeredIds);

        $instanceQuestions = AnamnesisQuestion::query()
            ->where('instance_id', $instance->id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get();

        $questions = $templateQuestions->concat($instanceQuestions)->unique('id');

        $answers = $instance->answers->keyBy('question_id');

        $defs = collect($this->categoryDefinitions->listForClinic($instance->clinic_id, true))
            ->keyBy('name');

        $categories = $questions
            ->groupBy(fn (AnamnesisQuestion $q) => $q->category ?: 'GERAL')
            ->map(function (Collection $items, string $categoryName) use ($answers, $defs, $disabledIds) {
                $def = $defs->get($categoryName);

                return [
                    'name' => $categoryName,
                    'icon' => $def['icon'] ?? '📄',
                    'icon_color' => $def['icon_color'] ?? '#64748b',
                    'description' => $def['description'] ?? null,
                    'sort_order' => $def['sort_order'] ?? 999,
                    'questions' => $items->map(function (AnamnesisQuestion $q) use ($answers, $disabledIds) {
                        $answer = $answers->get($q->id);

                        return array_merge($this->questionBank->serializeForFill($q), [
                            'value' => $answer?->value,
                            'supplementary_text' => $answer?->supplementary_text,
                            'file_path' => $answer?->file_path,
                            'is_disabled' => in_array($q->id, $disabledIds),
                            'is_instance_question' => $q->instance_id !== null,
                        ]);
                    })->values(),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();

        $patSig  = $instance->patientSignature;
        $dentSig = $instance->dentistSignature;

        return [
            'instance'          => $this->serializeInstanceMeta($instance, $disabledIds),
            'signature'         => $patSig  ? $this->serializePatientSignature($patSig)  : null,
            'dentist_signature' => $dentSig ? $this->serializeDentistSignature($dentSig) : null,
            'categories'        => $categories,
        ];
    }

    private function resolveQuestionsForInstance(AnamnesisInstance $instance, Collection $answeredIds): Collection
    {
        $active = $instance->template->templateQuestions()
            ->whereHas('question', fn ($q) => $q->where('is_active', true))
            ->with('question')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($link) => $link->question)
            ->filter();

        $historical = AnamnesisQuestion::query()
            ->whereIn('id', $answeredIds)
            ->where('is_active', false)
            ->get();

        return $active
            ->concat($historical)
            ->unique('id')
            ->filter(fn (?AnamnesisQuestion $q) => $q && $q->isRenderable())
            ->values();
    }

    public function saveAnswers(AnamnesisInstance $instance, array $answers, int $userId, ?Request $request = null): AnamnesisInstance
    {
        return DB::transaction(function () use ($instance, $answers, $userId, $request) {
            $questions = AnamnesisQuestion::query()
                ->whereIn('id', collect($answers)->pluck('question_id'))
                ->get()
                ->keyBy('id');

            foreach ($answers as $payload) {
                $question = $questions->get($payload['question_id'] ?? null);
                if (! $question) {
                    continue;
                }

                AnamnesisAnswer::updateOrCreate(
                    [
                        'instance_id' => $instance->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'question_text' => $question->text,
                        'question_type' => $question->type,
                        'value' => $payload['value'] ?? null,
                        'supplementary_text' => $payload['supplementary_text'] ?? null,
                        'file_path' => $payload['file_path'] ?? null,
                    ]
                );
            }

            if ($instance->status === InstanceStatus::Draft->value) {
                $instance->update(['status' => InstanceStatus::InProgress->value]);
            }

            $this->syncAlertsAndProgress($instance->fresh(['answers']));
            $this->log($instance, 'updated', $userId, $request);

            return $instance->fresh(['answers', 'professional']);
        });
    }

    public function complete(AnamnesisInstance $instance, int $userId, ?Request $request = null): AnamnesisInstance
    {
        $instance->update([
            'status' => InstanceStatus::Completed->value,
            'progress' => 100,
            'completed_at' => now(),
            'signed_at' => now(),
        ]);

        $this->log($instance, 'completed', $userId, $request);

        return $instance->fresh(['answers', 'professional']);
    }

    public function rename(AnamnesisInstance $instance, string $customName, int $userId, ?Request $request = null): AnamnesisInstance
    {
        $previous = $instance->custom_name ?? $instance->template_name;
        $instance->update(['custom_name' => $customName ?: null]);
        $this->log($instance, 'renamed', $userId, $request, [
            'from' => $previous,
            'to' => $customName ?: $instance->template_name,
        ]);

        return $instance->fresh();
    }

    public function updateDate(AnamnesisInstance $instance, string $dateIso, int $userId, ?Request $request = null): AnamnesisInstance
    {
        $previous = $instance->effectiveDate()->toIso8601String();
        $instance->update(['anamnesis_date' => $dateIso]);
        $this->log($instance, 'date_updated', $userId, $request, [
            'from' => $previous,
            'to' => $dateIso,
        ]);

        return $instance->fresh();
    }

    public function patientCardAlerts(Patient $patient): array
    {
        return AnamnesisAlert::query()
            ->where('patient_id', $patient->id)
            ->where('is_active', true)
            ->with(['professional:id,name', 'instance:id,template_name'])
            ->latest('triggered_at')
            ->get()
            ->map(fn (AnamnesisAlert $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'origin' => $a->instance?->template_name ?? 'Anamnese',
                'question' => $a->question_text,
                'answer' => $a->answer_value,
                'professional' => $a->professional?->name,
                'date' => $a->triggered_at->format('d/m/Y'),
                'time' => $a->triggered_at->format('H:i'),
            ])
            ->values()
            ->all();
    }

    public function syncAlertsAndProgress(AnamnesisInstance $instance): void
    {
        $instance->loadMissing(['answers', 'patient']);

        $answeredIds = $instance->answers->pluck('question_id');
        $questions = $this->resolveQuestionsForInstance($instance, $answeredIds)->keyBy('id');

        AnamnesisAlert::query()
            ->where('instance_id', $instance->id)
            ->delete();

        $answered = 0;
        $total = $questions->count();

        foreach ($instance->answers as $answer) {
            $question = $questions->get($answer->question_id);
            if (! $question) {
                continue;
            }

            if (filled($answer->value) || filled($answer->supplementary_text)) {
                $answered++;
            }

            if ($question->shouldTriggerAlert($answer->value, $answer->supplementary_text)) {
                AnamnesisAlert::create([
                    'clinic_id' => $instance->clinic_id,
                    'patient_id' => $instance->patient_id,
                    'instance_id' => $instance->id,
                    'answer_id' => $answer->id,
                    'question_id' => $question->id,
                    'label' => $question->resolvedAlertLabel($answer->supplementary_text),
                    'detail' => $answer->supplementary_text,
                    'question_text' => $question->text,
                    'answer_value' => trim(($answer->value ?? '') . ($answer->supplementary_text ? ' — ' . $answer->supplementary_text : '')),
                    'professional_id' => $instance->professional_id,
                    'is_active' => true,
                    'triggered_at' => now(),
                ]);
            }
        }

        $progress = $total > 0 ? (int) round(($answered / $total) * 100) : 0;
        if ($instance->status !== InstanceStatus::Completed->value) {
            $instance->update(['progress' => min(99, $progress)]);
        }
    }

    private function log(AnamnesisInstance $instance, string $action, int $userId, ?Request $request = null, array $metadata = []): void
    {
        AnamnesisActivityLog::create([
            'clinic_id' => $instance->clinic_id,
            'instance_id' => $instance->id,
            'patient_id' => $instance->patient_id,
            'template_id' => $instance->template_id,
            'action' => $action,
            'user_id' => $userId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function serializeInstanceMeta(AnamnesisInstance $instance, array $disabledIds = []): array
    {
        return [
            'id'                    => $instance->id,
            'template_name'         => $instance->template_name,
            'custom_name'           => $instance->custom_name,
            'display_name'          => $instance->displayName(),
            'status'                => $instance->status,
            'status_label'          => $instance->statusEnum()->label(),
            'progress'              => $instance->progress,
            'professional'          => $instance->professional?->name,
            'professional_id'       => $instance->professional_id,
            'created_at'            => $instance->created_at->toIso8601String(),
            'anamnesis_date'        => $instance->effectiveDate()->toIso8601String(),
            'validation_token'      => $instance->validation_token,
            'disabled_question_ids' => $disabledIds ?: ($instance->disabled_question_ids ?? []),
            'is_signed'             => $instance->isSigned(),
            'is_fully_signed'       => $instance->isFullySigned(),
            'signed_at'             => $instance->signed_at?->toIso8601String(),
        ];
    }

    public function serializePatientSignature(\App\Models\AnamnesisSignature $sig): array
    {
        return [
            'id'             => $sig->id,
            'patient_name'   => $sig->patient_name,
            'patient_cpf'    => $sig->patient_cpf,
            'patient_email'  => $sig->patient_email,
            'method'         => $sig->method(),
            'signature_url'  => $sig->signatureUrl(),
            'signature_hash' => $sig->signature_hash,
            'timezone'       => $sig->timezone,
            'signed_at'      => $sig->signed_at->toIso8601String(),
        ];
    }

    public function serializeDentistSignature(\App\Models\AnamnesisSignature $sig): array
    {
        return [
            'id'               => $sig->id,
            'professional_name' => $sig->patient_name, // armazenado em patient_name
            'professional_cro' => $sig->professional_cro,
            'method'           => 'Presencial',
            'signature_url'    => $sig->signatureUrl(),
            'signature_hash'   => $sig->signature_hash,
            'timezone'         => $sig->timezone,
            'signed_at'        => $sig->signed_at->toIso8601String(),
        ];
    }
}