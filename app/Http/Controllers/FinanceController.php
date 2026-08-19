<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\PricingConfig;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        $clinicId = session('current_clinic_id');

        $budgets = Budget::where('clinic_id', $clinicId)->with('patient')->latest()->take(5)->get();
        $transactions = Transaction::where('clinic_id', $clinicId)->latest()->take(10)->get();
        $totalReceita = Transaction::where('clinic_id', $clinicId)->where('tipo', 'receita')->where('status', 'pago')->sum('valor');
        $totalDespesa = Transaction::where('clinic_id', $clinicId)->where('tipo', 'despesa')->where('status', 'pago')->sum('valor');

        $pricing = PricingConfig::firstOrCreate(['clinic_id' => $clinicId]);

        return Inertia::render('Finance/Index', [
            'budgets' => $budgets->map(fn ($b) => [
                ...$b->toArray(),
                'patient' => $b->patient ? [
                    ...$b->patient->toArray(),
                    'cpf' => $b->patient->doc_tipo === 'cpf' ? $b->patient->doc_numero : null,
                    'full_name' => trim("{$b->patient->nome} {$b->patient->sobrenome}"),
                ] : null,
            ]),
            'transactions' => $transactions,
            'totalReceita' => $totalReceita,
            'totalDespesa' => $totalDespesa,
            'pricing' => $pricing,
        ]);
    }

    public function storeTransaction(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'tipo' => 'required|in:receita,despesa',
            'valor' => 'required|numeric|min:0',
            'categoria' => 'required|string',
            'descricao' => 'nullable|string',
            'patient_id' => ['nullable', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'vencimento' => 'nullable|date',
        ]);

        Transaction::create($validated + ['clinic_id' => $clinicId]);

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
        $clinicId = session('current_clinic_id');

        // Simplified for MVP
        $validated = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'total' => 'required|numeric',
        ]);

        Budget::create([
            'clinic_id' => $clinicId,
            'patient_id' => $validated['patient_id'],
            'total' => $validated['total'],
            'status' => 'rascunho',
        ]);

        return back()->with('success', 'Orçamento criado.');
    }
}
