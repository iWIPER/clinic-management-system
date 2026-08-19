<?php

namespace App\Http\Controllers;

use App\DTO\Financial\FinancingSimulationRequest;
use App\Exceptions\Financial\FinancialGatewayException;
use App\Models\Budget;
use App\Services\Financial\FinancingSimulationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancingSimulationController extends Controller
{
    public function simulate(Budget $budget, Request $request, FinancingSimulationService $simulations)
    {
        $this->authorize('view', $budget);

        $validated = $request->validate([
            'cpf'          => 'required|string|min:11|max:14',
            'installments' => 'required|integer|min:1|max:48',
        ]);

        $budget->load('patient');

        try {
            $result = $simulations->simulateForBudget(
                $budget->clinic_id,
                new FinancingSimulationRequest(
                    cpf: $validated['cpf'],
                    amount: (float) $budget->total,
                    installments: (int) $validated['installments'],
                    budgetId: $budget->id,
                    patientId: $budget->patient_id,
                ),
                Auth::user(),
            );

            return response()->json($result);
        } catch (FinancialGatewayException $e) {
            return response()->json([
                'message'     => $e->forUser(),
                'simulations' => [],
                'failures'    => [],
            ], 422);
        }
    }
}