<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TreatmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Treatment::query()->where('ativo', true);

        if ($search = $request->input('search')) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $treatments = $query->orderBy('nome')->paginate(20)->withQueryString();

        return Inertia::render('Treatments/Index', [
            'treatments' => $treatments,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Treatments/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'especialidade' => 'nullable|string|max:100',
            'duracao_padrao' => 'nullable|integer|min:5',
            'preco_base' => 'nullable|numeric|min:0',
            'descricao' => 'nullable|string',
        ]);

        Treatment::create($validated + ['ativo' => true]);

        return redirect()->route('treatments.index')->with('success', 'Tratamento cadastrado com sucesso!');
    }

    public function edit(Treatment $treatment)
    {
        return Inertia::render('Treatments/Edit', [
            'treatment' => $treatment,
        ]);
    }

    public function update(Request $request, Treatment $treatment)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'especialidade' => 'nullable|string|max:100',
            'duracao_padrao' => 'nullable|integer|min:5',
            'preco_base' => 'nullable|numeric|min:0',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $treatment->update($validated);

        return redirect()->route('treatments.index')->with('success', 'Tratamento atualizado!');
    }

    public function destroy(Treatment $treatment)
    {
        $treatment->update(['ativo' => false]); // soft delete like

        return redirect()->route('treatments.index')->with('success', 'Tratamento desativado.');
    }
}
