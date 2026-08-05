<?php

namespace App\Http\Controllers;

use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\PatientTreatmentAuditLog;
use App\Models\Transaction;
use App\Models\Treatment;
use App\Models\User;
use App\Services\TreatmentMaterialConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientTreatmentController extends Controller
{
    public function store(Request $request, Patient $patient)
    {
        $validated = $this->validateTreatment($request, false);

        $treatment = $validated['treatment_id'] ? Treatment::find($validated['treatment_id']) : null;

        // Múltiplos dentes marcados no seletor viram uma linha por dente —
        // mesmo profissional/procedimento/valor/custo/faces/data em todas,
        // cada uma com seu próprio budget_code (ver PatientTreatment::nextBudgetCode(),
        // que já soma corretamente dentro da mesma transação). Nenhum dente
        // marcado (ou o campo antigo `tooth` sozinho, sem `teeth`) continua
        // criando exatamente uma linha, igual ao comportamento anterior.
        $teeth = $validated['teeth'] ?? ($validated['tooth'] ? [$validated['tooth']] : []);
        if (empty($teeth)) {
            $teeth = [null];
        }

        $created = DB::transaction(function () use ($teeth, $validated, $treatment, $patient) {
            $rows = [];
            foreach ($teeth as $tooth) {
                $rows[] = PatientTreatment::create([
                    'clinic_id'       => $patient->clinic_id,
                    'patient_id'      => $patient->id,
                    'treatment_id'    => $treatment?->id,
                    'procedure_name'  => $treatment?->nome ?? $validated['procedure_name'],
                    'professional_id' => $validated['professional_id'] ?? null,
                    'convenio_id'     => $validated['convenio_id'] ?? null,
                    'budget_code'     => PatientTreatment::nextBudgetCode($patient->clinic_id, new \DateTimeImmutable($validated['treatment_date'])),
                    'tooth'           => $tooth,
                    'faces'           => $validated['faces'] ?? null,
                    'value_charged'   => $validated['value_charged'],
                    'cost'            => $validated['cost'],
                    'status'          => $validated['status'],
                    'treatment_date'  => $validated['treatment_date'],
                    'notes'           => $validated['notes'] ?? null,
                    'created_by_id'   => Auth::id(),
                    'updated_by_id'   => Auth::id(),
                ]);
            }
            return $rows;
        });

        foreach ($created as $row) {
            $this->logAudit($row, 'created', ['procedure_name' => $row->procedure_name]);
        }

        $message = count($created) > 1
            ? count($created) . ' tratamentos adicionados (' . $created[0]->budget_code . '–' . $created[count($created) - 1]->budget_code . ').'
            : "Tratamento {$created[0]->budget_code} adicionado.";

        return back()->with('success', $message);
    }

    public function update(Request $request, Patient $patient, PatientTreatment $patientTreatment)
    {
        if ($patientTreatment->isFinalized()) {
            return back()->with('error', 'Tratamento concluído não pode ser editado.');
        }

        $validated = $this->validateTreatment($request, false);
        $treatment = $validated['treatment_id'] ? Treatment::find($validated['treatment_id']) : null;

        $newValues = [
            'treatment_id'    => $treatment?->id,
            'procedure_name'  => $treatment?->nome ?? $validated['procedure_name'],
            'professional_id' => $validated['professional_id'] ?? null,
            'convenio_id'     => $validated['convenio_id'] ?? null,
            'tooth'           => $validated['tooth'] ?? null,
            'faces'           => $validated['faces'] ?? null,
            'value_charged'   => $validated['value_charged'],
            'cost'            => $validated['cost'],
            'status'          => $validated['status'],
            'treatment_date'  => $validated['treatment_date'],
            'notes'           => $validated['notes'] ?? null,
            'updated_by_id'   => Auth::id(),
        ];

        $changes = [];
        foreach ($newValues as $key => $value) {
            $old = $patientTreatment->{$key};
            $oldComparable = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;
            if (is_array($value) || is_array($oldComparable)) {
                if (json_encode($value) !== json_encode($oldComparable)) {
                    $changes[$key] = ['from' => $oldComparable, 'to' => $value];
                }
                continue;
            }
            if ((string) $oldComparable !== (string) $value) {
                $changes[$key] = ['from' => $oldComparable, 'to' => $value];
            }
        }

        $patientTreatment->update($newValues);

        if (! empty($changes)) {
            $this->logAudit($patientTreatment, 'updated', ['changes' => $changes]);
        }

        return back()->with('success', 'Tratamento atualizado.');
    }

    public function updateCost(Request $request, Patient $patient, PatientTreatment $patientTreatment)
    {
        if ($patientTreatment->isFinalized()) {
            return back()->with('error', 'Tratamento concluído não pode ter o custo alterado.');
        }

        $validated = $request->validate([
            'value_charged'   => 'required|numeric|min:0',
            'cost'            => 'required|numeric|min:0|lte:value_charged',
            'save_as_default' => 'sometimes|boolean',
        ], [
            'cost.lte' => 'O custo não pode ser maior que o valor cobrado.',
        ]);

        $from = ['value_charged' => (float) $patientTreatment->value_charged, 'cost' => (float) $patientTreatment->cost];

        $patientTreatment->update([
            'value_charged' => $validated['value_charged'],
            'cost'          => $validated['cost'],
            'updated_by_id' => Auth::id(),
        ]);

        // Só o Custo vira padrão do procedimento — Valor não é afetado por
        // essa checkbox (é o preço sugerido do catálogo, editável livremente
        // por fora, nunca sobrescrito por aqui — ver onProcedureSelect).
        if (! empty($validated['save_as_default']) && $patientTreatment->treatment_id) {
            Treatment::where('id', $patientTreatment->treatment_id)->update([
                'custo_padrao' => $validated['cost'],
            ]);
        }

        $this->logAudit($patientTreatment, 'cost_changed', [
            'from' => $from,
            'to'   => ['value_charged' => (float) $validated['value_charged'], 'cost' => (float) $validated['cost']],
        ]);

        return back()->with('success', 'Custo atualizado.');
    }

    public function finalize(Request $request, Patient $patient, PatientTreatment $patientTreatment, TreatmentMaterialConsumptionService $stockService)
    {
        if ($patientTreatment->isFinalized()) {
            return back()->with('error', 'Este tratamento já está concluído.');
        }

        $validated = $request->validate([
            'professional_id' => 'required|exists:users,id',
            'completed_at'    => 'required|date',
            'evolution'       => 'nullable|string',
            'update_stock'    => 'sometimes|boolean',
        ]);

        $completedAt = \Illuminate\Support\Carbon::parse($validated['completed_at']);

        DB::transaction(function () use ($validated, $patient, $patientTreatment, $completedAt) {
            $patientTreatment->update([
                'professional_id' => $validated['professional_id'],
                'status'          => PatientTreatment::STATUS_CONCLUIDO,
                'completed_at'    => $completedAt,
                'updated_by_id'   => Auth::id(),
            ]);

            if (! empty($validated['evolution'])) {
                ClinicalEvolution::create([
                    'clinic_id'            => $patient->clinic_id,
                    'patient_id'           => $patient->id,
                    'professional_id'      => $validated['professional_id'],
                    'patient_treatment_id' => $patientTreatment->id,
                    'content'              => $validated['evolution'],
                    'recorded_at'          => $completedAt,
                ]);
            }

            Transaction::create([
                'clinic_id'    => $patient->clinic_id,
                'patient_id'   => $patient->id,
                'tipo'         => 'receita',
                'valor'        => $patientTreatment->value_charged,
                'categoria'    => 'Tratamento',
                'descricao'    => $patientTreatment->procedure_name,
                'origem_type'  => PatientTreatment::class,
                'origem_id'    => $patientTreatment->id,
                'status'       => 'pendente',
            ]);
        });

        if (! empty($validated['update_stock']) && ! $patientTreatment->stock_updated_at && $patientTreatment->treatment_id) {
            $treatment = Treatment::find($patientTreatment->treatment_id);
            if ($treatment) {
                $stockService->consume($treatment);
                $patientTreatment->update(['stock_updated_at' => now()]);
            }
        }

        $this->logAudit($patientTreatment, 'completed', [
            'completed_at'      => $completedAt->toIso8601String(),
            // Profissional selecionado no modal de finalizar — pode ser
            // diferente de quem está logado (Auth::id(), gravado em
            // user_id) e de quem criou o tratamento. A linha do tempo do
            // histórico (TreatmentHistoryModal.vue) usa este snapshot pro
            // evento "Concluído", não o autor da ação.
            'professional_id'   => $validated['professional_id'],
            'professional_name' => User::find($validated['professional_id'])?->name,
        ]);

        return back()->with('success', "Tratamento {$patientTreatment->budget_code} concluído.");
    }

    public function duplicate(Patient $patient, PatientTreatment $patientTreatment)
    {
        $copy = PatientTreatment::create([
            'clinic_id'       => $patient->clinic_id,
            'patient_id'      => $patient->id,
            'treatment_id'    => $patientTreatment->treatment_id,
            'procedure_name'  => $patientTreatment->procedure_name,
            'professional_id' => $patientTreatment->professional_id,
            'convenio_id'     => $patientTreatment->convenio_id,
            'budget_code'     => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
            'tooth'           => $patientTreatment->tooth,
            'faces'           => $patientTreatment->faces,
            'value_charged'   => $patientTreatment->value_charged,
            'cost'            => $patientTreatment->cost,
            'status'          => PatientTreatment::STATUS_FUTURO,
            'treatment_date'  => now()->toDateString(),
            'notes'           => null,
            'created_by_id'   => Auth::id(),
            'updated_by_id'   => Auth::id(),
        ]);

        $this->logAudit($copy, 'duplicated', ['source_id' => $patientTreatment->id, 'source_budget_code' => $patientTreatment->budget_code]);

        return back()->with('success', "Tratamento duplicado como {$copy->budget_code}.");
    }

    public function destroy(Patient $patient, PatientTreatment $patientTreatment)
    {
        if ($patientTreatment->isFinalized()) {
            return back()->with('error', 'Tratamento concluído não pode ser excluído.');
        }

        $budgetCode = $patientTreatment->budget_code;
        $patientTreatment->delete();

        return back()->with('success', "Tratamento {$budgetCode} excluído.");
    }

    private function validateTreatment(Request $request, bool $allowCompleted): array
    {
        $statuses = $allowCompleted
            ? array_keys(PatientTreatment::STATUSES)
            : array_diff(array_keys(PatientTreatment::STATUSES), [PatientTreatment::STATUS_CONCLUIDO]);

        return $request->validate([
            'treatment_id'    => 'nullable|exists:treatments,id',
            'procedure_name'  => 'required_without:treatment_id|nullable|string|max:255',
            'professional_id' => 'required|exists:users,id',
            'convenio_id'     => 'nullable|exists:convenios,id',
            'treatment_date'  => 'required|date',
            'tooth'           => 'nullable|string|max:10',
            // Só usado por store() (criação) — um dente por linha ao editar
            // continua vindo pelo campo `tooth` singular acima.
            'teeth'           => 'nullable|array',
            'teeth.*'         => 'nullable|string|max:10',
            'faces'           => 'nullable|array',
            'faces.*'         => 'string|in:M,D,V,L,O',
            'value_charged'   => 'required|numeric|min:0',
            'cost'            => 'required|numeric|min:0|lte:value_charged',
            'status'          => ['required', 'in:' . implode(',', $statuses)],
            'notes'           => 'nullable|string',
        ], [
            'cost.lte' => 'O custo não pode ser maior que o valor cobrado.',
        ]);
    }

    private function eligibleProfessionals(int $clinicId)
    {
        return User::query()
            ->join('clinic_user', 'clinic_user.user_id', '=', 'users.id')
            ->where('clinic_user.clinic_id', $clinicId)
            ->where('users.job_title', 'Dentista')
            ->where('users.status', 'ativo')
            ->select('users.id', 'users.name', 'users.job_title')
            ->orderBy('users.name')
            ->get();
    }

    private function logAudit(PatientTreatment $patientTreatment, string $action, array $metadata = []): void
    {
        PatientTreatmentAuditLog::create([
            'clinic_id'             => $patientTreatment->clinic_id,
            'patient_treatment_id'  => $patientTreatment->id,
            'user_id'               => Auth::id(),
            'action'                => $action,
            'metadata'              => $metadata ?: null,
            'created_at'            => now(),
        ]);
    }
}
