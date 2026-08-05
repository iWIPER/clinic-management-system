<?php

namespace App\Http\Controllers;

use App\Models\Convenio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConvenioController extends Controller
{
    public function index()
    {
        return Inertia::render('ClinicSettings/Convenios', [
            'convenios' => Convenio::orderBy('ordem')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome'  => 'required|string|max:255',
            'ordem' => 'nullable|integer|min:0',
        ]);

        Convenio::create([
            'nome'  => $validated['nome'],
            'ordem' => $validated['ordem'] ?? 0,
            'ativo' => true,
        ]);

        return back()->with('success', 'Convênio cadastrado.');
    }

    public function update(Request $request, Convenio $convenio)
    {
        $validated = $request->validate([
            'nome'  => 'required|string|max:255',
            'ordem' => 'nullable|integer|min:0',
        ]);

        $convenio->update($validated);

        return back()->with('success', 'Convênio atualizado.');
    }

    public function toggle(Convenio $convenio)
    {
        $convenio->update(['ativo' => ! $convenio->ativo]);

        return back()->with('success', $convenio->ativo ? 'Convênio reativado.' : 'Convênio desativado.');
    }
}
