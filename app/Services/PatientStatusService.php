<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PatientStatusService
{
    /**
     * Return auto-status calculation data for a patient.
     * Returns null if no qualifying concluded procedure exists.
     */
    public function getAutoStatusData(Patient $patient): ?array
    {
        $row = DB::table('procedure_executions as pe')
            ->join('consultations as c', 'pe.consultation_id', '=', 'c.id')
            ->join('clinical_records as cr', 'c.id', '=', 'cr.consultation_id')
            ->join('treatments as t', 'pe.treatment_id', '=', 't.id')
            ->where('c.patient_id', $patient->id)
            ->where('cr.status', 'concluido')
            ->whereNotNull('t.inatividade_meses')
            ->whereNotNull('cr.finished_at')
            ->orderByDesc('cr.finished_at')
            ->orderByDesc('t.inatividade_meses')
            ->select([
                'cr.finished_at',
                'cr.procedure_name',
                't.nome as treatment_nome',
                't.inatividade_meses',
            ])
            ->first();

        if (!$row) {
            return null;
        }

        $lastDate  = Carbon::parse($row->finished_at);
        $inativoEm = $lastDate->copy()->addMonths((int) $row->inatividade_meses);
        $hoje      = Carbon::today();

        return [
            'procedure_nome'    => $row->treatment_nome ?: $row->procedure_name,
            'last_date'         => $lastDate->toDateString(),
            'inatividade_meses' => (int) $row->inatividade_meses,
            'inativo_em'        => $inativoEm->toDateString(),
            'is_inativo'        => $inativoEm->isPast(),
            'dias_restantes'    => (int) $hoje->diffInDays($inativoEm, false),
        ];
    }

    /**
     * Recalculate and persist the auto-status for one patient.
     * No-op when auto mode is off or patient is deceased.
     */
    public function recalculate(Patient $patient): void
    {
        if (!$patient->status_automatico || $patient->status === 'falecido') {
            return;
        }

        $data = $this->getAutoStatusData($patient);

        if (!$data) {
            return;
        }

        $newStatus = $data['is_inativo'] ? 'inativo' : 'ativo';

        if ($patient->status !== $newStatus) {
            $patient->update(['status' => $newStatus]);
        }
    }

    /**
     * Recalculate all auto-mode patients belonging to a specific clinic.
     */
    public function recalculateForClinic(int $clinicId): int
    {
        $patients = Patient::where('clinic_id', $clinicId)
            ->where('status_automatico', true)
            ->where('status', '!=', 'falecido')
            ->get();

        return $this->recalculateBatch($patients);
    }

    /**
     * Recalculate all auto-mode patients across all clinics (used by the scheduler).
     */
    public function recalculateAll(): int
    {
        $patients = Patient::where('status_automatico', true)
            ->where('status', '!=', 'falecido')
            ->get();

        return $this->recalculateBatch($patients);
    }

    private function recalculateBatch(\Illuminate\Support\Collection $patients): int
    {
        $updated = 0;

        foreach ($patients as $patient) {
            $data = $this->getAutoStatusData($patient);

            if (!$data) {
                continue;
            }

            $newStatus = $data['is_inativo'] ? 'inativo' : 'ativo';

            if ($patient->status !== $newStatus) {
                $patient->update(['status' => $newStatus]);
                $updated++;
            }
        }

        return $updated;
    }
}
