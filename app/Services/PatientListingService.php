<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte única da consulta filtrada de pacientes — usada pela listagem
 * (PatientController::index) e pela exportação (PatientController::export).
 * Quando filtros avançados forem implementados, adicionar aqui: passam a
 * valer automaticamente para listagem, exportação e um futuro módulo de
 * Relatórios, sem duplicar a lógica de filtro em cada consumidor.
 */
class PatientListingService
{
    public function filteredQuery(array $filters): Builder
    {
        $query = Patient::query();

        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('sobrenome', 'like', "%{$search}%")
                    ->orWhere('doc_numero', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%")
                    ->orWhere('rg', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        if ($markerId = $filters['marker'] ?? null) {
            $query->whereHas('markers', fn (Builder $q) => $q->where('patient_tags.id', $markerId));
        }

        return $query->latest();
    }
}
