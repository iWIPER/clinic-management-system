<?php

namespace App\Http\Controllers;

use App\DTO\Financial\FinancingProposalRequest;
use App\Models\Budget;
use App\Services\Financial\FinancingProposalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancingProposalController extends Controller
{
    public function store(Budget $budget, Request $request, FinancingProposalService $proposals)
    {
        abort_unless($budget->clinic_id === (int) session('current_clinic_id'), 403);

        $validated = $request->validate([
            'provider'               => 'required|string',
            'name'                   => 'required|string|max:255',
            'cpf'                    => 'required|string|min:11|max:14',
            'phone'                  => 'required|string|max:20',
            'email'                  => 'required|email|max:255',
            'installments'           => 'required|integer|min:1|max:48',
            'simulation_external_id' => 'nullable|string',
        ]);

        $proposal = $proposals->submit(
            new FinancingProposalRequest(
                provider: $validated['provider'],
                budgetId: $budget->id,
                patientId: $budget->patient_id,
                name: $validated['name'],
                cpf: $validated['cpf'],
                phone: $validated['phone'],
                email: $validated['email'],
                amount: (float) $budget->total,
                installments: (int) $validated['installments'],
                simulationExternalId: $validated['simulation_external_id'] ?? null,
            ),
            Auth::user(),
        );

        return response()->json([
            'proposal_id' => $proposal->id,
            'status'      => $proposal->status,
            'message'     => 'Proposta enviada. Aguardando retorno da instituição financeira.',
        ]);
    }
}