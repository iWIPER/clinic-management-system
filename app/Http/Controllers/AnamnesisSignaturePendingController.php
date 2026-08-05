<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisInstance;
use App\Models\ClinicalEvolution;
use Illuminate\Http\JsonResponse;

class AnamnesisSignaturePendingController extends Controller
{
    public function counts(): JsonResponse
    {
        $userId   = auth()->id();
        $clinicId = session('current_clinic_id');

        if (! $userId || ! $clinicId) {
            return response()->json(['count' => 0, 'items' => []]);
        }

        $pendingAnamneses = AnamnesisInstance::query()
            ->where('clinic_id', $clinicId)
            ->where('professional_id', $userId)
            ->where('status', 'signed') // paciente assinou, dentista não assinou ainda
            ->with(['patient:id,nome,sobrenome'])
            ->latest('signed_at')
            ->take(10)
            ->get();

        $anamnesisItems = $pendingAnamneses->map(fn (AnamnesisInstance $i) => [
            'type'           => 'anamnesis',
            'id'             => $i->id,
            'patient_id'     => $i->patient_id,
            'patient_name'   => trim(($i->patient?->nome ?? '') . ' ' . ($i->patient?->sobrenome ?? '')),
            'label'          => $i->displayName(),
            'badge'          => 'Paciente assinou',
            'occurred_at'    => $i->signed_at,
            'occurred_label' => 'Assinado em ' . $i->signed_at?->format('d/m/Y H:i'),
            'show_url'       => route('patients.anamneses.show', [$i->patient_id, $i->id]),
        ]);

        // Evoluções clínicas marcadas com "exigir assinatura" e ainda sem
        // assinatura registrada (ver ClinicalEvolutionSignature/
        // LocalEvolutionSignatureProvider — assinatura presencial via canvas,
        // paciente/responsável).
        $pendingEvolutions = ClinicalEvolution::query()
            ->where('clinic_id', $clinicId)
            ->where('professional_id', $userId)
            ->where('signature_required', true)
            ->whereDoesntHave('signature')
            ->with(['patient:id,nome,sobrenome'])
            ->latest('recorded_at')
            ->take(10)
            ->get();

        $evolutionItems = $pendingEvolutions->map(fn (ClinicalEvolution $e) => [
            'type'           => 'evolution',
            'id'             => $e->id,
            'patient_id'     => $e->patient_id,
            'patient_name'   => trim(($e->patient?->nome ?? '') . ' ' . ($e->patient?->sobrenome ?? '')),
            'label'          => 'Evolução clínica',
            'badge'          => 'Assinatura pendente',
            'occurred_at'    => $e->recorded_at,
            'occurred_label' => 'Registrada em ' . $e->recorded_at?->format('d/m/Y'),
            'show_url'       => route('patients.show', $e->patient_id),
        ]);

        $items = $anamnesisItems->concat($evolutionItems)
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ]);
    }
}
