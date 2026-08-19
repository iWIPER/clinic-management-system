<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B6.1.1 — comprovado com EXPLAIN ANALYZE no dataset de benchmark
 * (Postgres 16, ~197k appointments / ~70k consultations / ~38k documents):
 * appointments e consultations não tinham NENHUM índice além da PK — toda
 * query "deste paciente" (usada por PatientHubService, o card de Evoluções,
 * a equipe responsável e a aba Tratamentos) fazia Seq Scan/Parallel Seq Scan
 * na tabela inteira. documents tinha só um índice em status, nada em
 * patient_id — a aba Documentos (filtro + ORDER BY created_at DESC) também
 * escaneava tudo e ainda ordenava à parte.
 *
 * Medido (paciente representativo, 35 appointments/12 consultations/1-6
 * documents em tabelas de ~197k/70k/38k linhas):
 *   appointments.patient_id:            26.8ms Parallel Seq Scan → 0.33ms Index Scan (-98,8%)
 *   consultations.patient_id:            5.7ms Seq Scan          → 0.45ms Index Scan (-92%)
 *   documents (patient_id, created_at):  3.7ms Seq Scan + Sort   → 0.49ms Index Scan, sem Sort (-87%)
 *     (a variante count(*) da paginação virou Index Only Scan, 0 Heap Fetches)
 *
 * patient_treatments, clinical_records e clinical_evolutions já tinham
 * índice cobrindo patient_id (ver migrations anteriores) e já usavam Index
 * Scan — nenhuma mudança neles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('patient_id');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->index('patient_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            // Composto (não só patient_id): a query real do Patient Show
            // sempre filtra por patient_id E ordena por created_at desc —
            // medido que o composto elimina o Sort, o índice simples não.
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'created_at']);
        });
    }
};
