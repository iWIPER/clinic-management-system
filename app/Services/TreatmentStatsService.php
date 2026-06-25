<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ProcedureExecution;
use App\Models\Treatment;

class TreatmentStatsService
{
    public function forTreatment(Treatment $treatment): array
    {
        $executions = ProcedureExecution::where('treatment_id', $treatment->id)
            ->with('consultation:id,started_at,finished_at');

        $executionCount = (clone $executions)->count();
        $executionRevenue = (float) (clone $executions)->sum('price_charged');
        $lastExecution = (clone $executions)->max('executed_at');

        $completedAppointments = Appointment::where('treatment_id', $treatment->id)
            ->where('status', 'completed');

        $completedCount = (clone $completedAppointments)->count();

        $activeAppointments = Appointment::where('treatment_id', $treatment->id)
            ->whereIn('status', ['completed', 'in_attendance']);

        $appointmentCount = (clone $activeAppointments)->count();
        $lastAppointment = (clone $activeAppointments)->max('start');

        $usageCount = $executionCount + $appointmentCount;
        $totalRevenue = $executionRevenue;
        $avgPracticed = $executionCount > 0 ? $executionRevenue / $executionCount : 0;

        $durations = ProcedureExecution::where('treatment_id', $treatment->id)
            ->with('consultation:id,started_at,finished_at')
            ->get()
            ->map(function ($exec) {
                $c = $exec->consultation;
                if ($c?->started_at && $c?->finished_at) {
                    return max(1, $c->started_at->diffInMinutes($c->finished_at));
                }

                return null;
            })
            ->filter();

        $avgDuration = $durations->isNotEmpty() ? round($durations->avg()) : null;

        $lastUsed = collect([$lastExecution, $lastAppointment])
            ->filter()
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))
            ->max();

        return [
            'usage_count' => $usageCount,
            'execution_count' => $executionCount,
            'appointment_count' => $appointmentCount,
            'completed_appointments_count' => $completedCount,
            'total_revenue' => round($totalRevenue, 2),
            'avg_practiced_price' => round($avgPracticed, 2),
            'avg_duration_minutes' => $avgDuration,
            'last_used_at' => $lastUsed?->toIso8601String(),
        ];
    }

    public function hasLinkedAttendances(Treatment $treatment): bool
    {
        if (ProcedureExecution::where('treatment_id', $treatment->id)->exists()) {
            return true;
        }

        if (Appointment::where('treatment_id', $treatment->id)->exists()) {
            return true;
        }

        if (\App\Models\BudgetItem::where('treatment_id', $treatment->id)->exists()) {
            return true;
        }

        return false;
    }
}