<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;

/**
 * Busca leve de pacientes por nome, pra combobox pesquisável (ex.: vincular
 * paciente a uma tarefa) — não carrega a clínica inteira de uma vez, só os
 * resultados da busca. Patient::query() já vem escopado pela clínica atual
 * via BelongsToClinic, sem precisar de where manual.
 */
class PatientSearchController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $patients = Patient::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('sobrenome', 'like', "%{$search}%"));
            })
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'sobrenome']);

        return response()->json($patients);
    }
}
