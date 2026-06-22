<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\PricingConfig;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        $clinicId = session('current_clinic_id');

        $budgets = Budget::with('patient')->latest()->take(5)->get();
        $transactions = Transaction::latest()->take(10)->get();
        $totalReceita = Transaction::where('tipo', 'receita')->where('status', 'pago')->sum('valor');
        $totalDespesa = Transaction::where('tipo', 'despesa')->where('status', 'pago')->sum('valor');

        $pricing = PricingConfig::firstOrCreate(['clinic_id' => $clinicId]);

        return Inertia::render('Finance/Index', [
            'budgets' => $budgets,
            'transactions' => $transactions,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'pricing' => $pricing,
        ]);
    }

    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'tipo' => 'required|in:receita,despesa',
            'valor' => 'required|numeric|min:0',
            'categoria' => 'required|string',
            'descricao' => 'nullable|string',
            'patient_id' => 'nullable|exists:patients,id',
            'vencimento' => 'nullable|date',
        ]);

        Transaction::create($validated + ['clinic_id' => session('current_clinic_id')]);

        return back()->with('success', 'Lançamento criado.');
    }

    public function updatePricing(Request $request)
    {
        $validated = $request->validate([
            'salario_desejado' => 'nullable|numeric',
            'horas_trabalhadas' => 'nullable|numeric',
            'custos_fixos' => 'nullable|numeric',
            'margem_lucro' => 'nullable|numeric',
        ]);

        $config = PricingConfig::firstOrCreate(['clinic_id' => session('current_clinic_id')]);
        $config->update($validated);

        return back()->with('success', 'Precificação atualizada.');
    }

    // Basic: create budget from procedure
    public function createBudgetFromExecution(Request $request)
    {
        // Simplified for MVP
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'total' => 'required|numeric',
        ]);

        Budget::create([
            'clinic_id' => session('current_clinic_id'),
            'patient_id' => $validated['patient_id'],
            'total' => $validated['total'],
            'status' => 'rascunho',
        ]);

        return back()->with('success', 'Orçamento criado.');
    }
}
