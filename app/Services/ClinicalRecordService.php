<?php

namespace App\Services;

use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalRecord;
use App\Models\Consultation;
use Illuminate\Support\Carbon;

class ClinicalRecordService
{
    public function createFromConsultation(Consultation $consultation): ClinicalRecord
    {
        $existing = ClinicalRecord::where('consultation_id', $consultation->id)->first();
        if ($existing) {
            return $existing;
        }

        $consultation->loadMissing([
            'appointment.treatment',
            'procedureExecutions.treatment',
        ]);

        $executions = $consultation->procedureExecutions;
        $appointment = $consultation->appointment;
        $treatment = $appointment?->treatment;

        if ($executions->isNotEmpty()) {
            $procedureName = $executions
                ->map(fn ($e) => $e->treatment?->nome)
                ->filter()
                ->unique()
                ->implode(', ');
            $procedureCategory = $executions->first()?->treatment?->especialidade;
            $price = $executions->sum('price_charged');
        } else {
            $procedureName = $treatment?->nome ?? 'Atendimento';
            $procedureCategory = $treatment?->especialidade;
            $price = $treatment?->preco_base ?? 0;
        }

        $startedAt = $consultation->started_at ?? $consultation->check_in_at ?? $appointment?->start;
        $finishedAt = $consultation->finished_at ?? now();

        $durationMinutes = null;
        if ($startedAt && $finishedAt) {
            $durationMinutes = max(1, Carbon::parse($startedAt)->diffInMinutes(Carbon::parse($finishedAt)));
        }

        return ClinicalRecord::create([
            'clinic_id' => $consultation->clinic_id,
            'patient_id' => $consultation->patient_id,
            'professional_id' => $consultation->professional_id,
            'appointment_id' => $consultation->appointment_id,
            'consultation_id' => $consultation->id,
            'procedure_name' => $procedureName,
            'procedure_category' => $procedureCategory,
            'status' => ClinicalRecordStatus::Concluido,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'notes' => $consultation->notes,
        ]);
    }
}