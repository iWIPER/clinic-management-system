<?php

namespace App\Http\Controllers;

use App\Services\Financial\FinancialConnectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinancialMarketplaceController extends Controller
{
    public function index(FinancialConnectionService $connections)
    {
        $clinicId = session('current_clinic_id');

        return Inertia::render('Finance/Marketplace', [
            'institutions' => $connections->listForClinic($clinicId),
        ]);
    }

    public function store(Request $request, FinancialConnectionService $connections)
    {
        $validated = $request->validate([
            'provider'      => 'required|in:' . implode(',', array_keys(config('financial.gateways', []))),
            'environment'   => 'required|in:sandbox,production',
            'client_id'     => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
        ]);

        $connections->upsert(session('current_clinic_id'), $validated['provider'], $validated);

        return back()->with('success', 'Credenciais salvas. Execute "Testar Integração" para ativar.');
    }

    public function test(string $provider, FinancialConnectionService $connections)
    {
        $report = $connections->test($provider, session('current_clinic_id'));

        return response()->json($report);
    }
}