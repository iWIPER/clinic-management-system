<?php

namespace App\Services\Anamnesis;

use App\Data\AnamnesisImportParser;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\AnamnesisTemplateQuestion;
use Illuminate\Support\Facades\DB;

class QuestionBankService
{
    public function __construct(private CategoryDefinitionService $categories) {}

    public function search(?int $clinicId, ?string $term = null, ?string $category = null, ?string $type = null, int $limit = 50): array
    {
        return AnamnesisQuestion::query()
            ->forClinic($clinicId)
            ->when($term, fn ($q) => $q->whereRaw('LOWER(text) LIKE ?', ['%' . mb_strtolower($term) . '%']))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('category')
            ->orderBy('text')
            ->limit($limit)
            ->get()
            ->filter(fn (AnamnesisQuestion $q) => $q->isRenderable())
            ->map(fn (AnamnesisQuestion $q) => $this->serializeQuestion($q))
            ->values()
            ->all();
    }

    public function categories(?int $clinicId): array
    {
        $defs = $this->categories->listForClinic($clinicId, false);

        if (! empty($defs)) {
            return collect($defs)->pluck('name')->all();
        }

        return AnamnesisQuestion::query()
            ->forClinic($clinicId)
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }

    public function store(array $data, ?int $clinicId = null, ?AnamnesisTemplate $attachTo = null): AnamnesisQuestion
    {
        $categoryName = strtoupper($data['category'] ?? 'GERAL');
        $categoryId = $this->categories->resolveId($clinicId, $categoryName);
        $hash = (new AnamnesisImportParser())->hash($data['text']);

        $question = AnamnesisQuestion::create([
            'clinic_id' => $clinicId,
            'category_id' => $categoryId,
            'category' => $categoryName,
            'text' => $data['text'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'has_alert' => (bool) ($data['has_alert'] ?? false),
            'alert_text' => $data['alert_text'] ?? null,
            'alert_trigger_values' => ($data['has_alert'] ?? false) ? ['sim'] : null,
            'show_on_patient_card' => (bool) ($data['show_on_patient_card'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'question_hash' => $hash,
        ]);

        if ($attachTo) {
            $this->attachToTemplate(
                $attachTo,
                $question->id,
                null,
                (bool) ($data['pivot_is_required'] ?? $data['is_required'] ?? false)
            );
        }

        return $question;
    }

    public function update(AnamnesisQuestion $question, array $data, ?int $clinicId = null): AnamnesisQuestion
    {
        $categoryName = strtoupper($data['category'] ?? $question->category);

        $question->update([
            'category_id' => $this->categories->resolveId($clinicId ?? $question->clinic_id, $categoryName),
            'category' => $categoryName,
            'text' => $data['text'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'has_alert' => (bool) ($data['has_alert'] ?? false),
            'alert_text' => $data['alert_text'] ?? null,
            'alert_trigger_values' => ($data['has_alert'] ?? false) ? ['sim'] : null,
            'show_on_patient_card' => (bool) ($data['show_on_patient_card'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'question_hash' => (new AnamnesisImportParser())->hash($data['text']),
        ]);

        return $question->fresh();
    }

    public function toggleActive(AnamnesisQuestion $question): AnamnesisQuestion
    {
        $question->update(['is_active' => ! $question->is_active]);

        return $question->fresh();
    }

    public function duplicate(AnamnesisQuestion $question): AnamnesisQuestion
    {
        $copy = $question->replicate();
        $copy->text = $question->text . ' (cópia)';
        $copy->question_hash = (new AnamnesisImportParser())->hash($copy->text);
        $copy->save();

        return $copy;
    }

    public function attachToTemplate(AnamnesisTemplate $template, int $questionId, ?int $sortOrder = null, bool $isRequired = false): void
    {
        $sortOrder ??= ((int) $template->templateQuestions()->max('sort_order')) + 1;

        AnamnesisTemplateQuestion::updateOrCreate(
            ['template_id' => $template->id, 'question_id' => $questionId],
            ['sort_order' => $sortOrder, 'is_required' => $isRequired]
        );
    }

    public function detachFromTemplate(AnamnesisTemplate $template, int $questionId): void
    {
        AnamnesisTemplateQuestion::where('template_id', $template->id)
            ->where('question_id', $questionId)
            ->delete();
    }

    public function reorderTemplateQuestions(AnamnesisTemplate $template, array $orderedQuestionIds): void
    {
        DB::transaction(function () use ($template, $orderedQuestionIds) {
            foreach ($orderedQuestionIds as $index => $questionId) {
                AnamnesisTemplateQuestion::where('template_id', $template->id)
                    ->where('question_id', $questionId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function moveQuestion(AnamnesisTemplate $template, int $questionId, string $direction): void
    {
        $links = $template->templateQuestions()->orderBy('sort_order')->get();
        $index = $links->search(fn ($l) => $l->question_id === $questionId);

        if ($index === false) {
            return;
        }

        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swap < 0 || $swap >= $links->count()) {
            return;
        }

        $current = $links[$index];
        $target = $links[$swap];

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $target->sort_order]);
        $target->update(['sort_order' => $currentOrder]);
    }

    public function templateEditorPayload(AnamnesisTemplate $template, ?int $clinicId = null): array
    {
        $links = $template->templateQuestions()
            ->with('question.categoryDefinition')
            ->orderBy('sort_order')
            ->get();

        $questions = $links->map(function (AnamnesisTemplateQuestion $link) {
            $q = $link->question;
            if (! $q || ! $q->isRenderable()) {
                return null;
            }

            return array_merge($this->serializeQuestion($q), [
                'pivot_sort_order' => $link->sort_order,
                'pivot_is_required' => $link->is_required,
            ]);
        })->filter()->values();

        $categoryDefs = collect($this->categories->listForClinic($clinicId ?? $template->clinic_id, false))
            ->keyBy('name');

        $grouped = $questions
            ->groupBy('category')
            ->map(function ($items, $categoryName) use ($categoryDefs) {
                $def = $categoryDefs->get($categoryName);

                return [
                    'name' => $categoryName,
                    'icon' => $def['icon'] ?? '📄',
                    'icon_color' => $def['icon_color'] ?? '#64748b',
                    'description' => $def['description'] ?? null,
                    'sort_order' => $def['sort_order'] ?? 999,
                    'questions' => $items->sortBy('pivot_sort_order')->values()->all(),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();

        return [
            'template' => $template->only(['id', 'name', 'description', 'is_active', 'is_system', 'is_default', 'version']),
            'category_groups' => $grouped,
            'questions' => $questions->all(),
        ];
    }

    public function serializeQuestion(AnamnesisQuestion $q): array
    {
        return [
            'id' => $q->id,
            'text' => $q->text,
            'description' => $q->description,
            'category' => $q->category,
            'category_id' => $q->category_id,
            'type' => $q->type,
            'type_label' => $q->typeEnum()->label(),
            'is_required' => $q->is_required,
            'has_alert' => $q->has_alert,
            'alert_text' => $q->alert_text,
            'show_on_patient_card' => $q->show_on_patient_card,
            'is_active' => $q->is_active,
        ];
    }

    public function serializeForFill(AnamnesisQuestion $q): array
    {
        return [
            'id' => $q->id,
            'text' => $q->text,
            'description' => $q->description,
            'supplementary_placeholder' => $q->supplementary_placeholder,
            'type' => $q->type,
            'has_alert' => $q->has_alert,
            'is_required' => $q->is_required,
        ];
    }
}