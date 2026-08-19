<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B6.1.2 — comprovado com EXPLAIN ANALYZE no dataset de benchmark
 * (Postgres 16, ~197k appointments / ~70k consultations, 85 clínicas de
 * tamanhos variados). appointments não tinha nenhum índice em clinic_id,
 * start, end, professional_id ou chair_id; consultations não tinha índice
 * em appointment_id.
 *
 * 1) appointments(clinic_id, start) — a query da agenda semanal/diária
 *    (AppointmentController::index()/fullscreen(): clinic_id + start
 *    BETWEEN + ORDER BY start) fazia Parallel Seq Scan + Sort explícito.
 *    Medido, clínica grande (16k agendamentos, semana cheia — 1021
 *    resultados): 24,9ms → 5,6ms (-78%), Sort eliminado. Clínica pequena
 *    (139 agendamentos no total, semana comum — 1 resultado): 21,0ms →
 *    0,28ms (-98,7%) — a mesma tabela inteira era varrida mesmo pra achar
 *    1 linha de uma clínica pequena; o índice remove esse custo fixo
 *    independente do tamanho da clínica. Testado também filtrando por
 *    profissional e por cadeira (variantes reais do mesmo endpoint) — o
 *    índice já cobre bem os dois, sem precisar de índice adicional.
 *
 * 2) appointments((start::date)) — índice funcional, casa exatamente com o
 *    SQL que Eloquent gera pra whereDate('start', ...) no grammar Postgres
 *    (confirmado via ->toSql(): "start"::date = ?), usado por
 *    AppointmentController::dayAvailability()/nextAvailableSlot() (recurso
 *    "Encontrar horário"). Medido: 32,8ms Seq Scan → 5,0ms Bitmap Heap
 *    Scan (-85%), reproduzido com profissional/cadeira/data diferentes.
 *
 * 3) consultations(appointment_id) — usado por
 *    AppointmentController::checkIn() (firstOrCreate por appointment_id, a
 *    cada clique de check-in). Medido: 6,2ms Seq Scan → 1,4ms Index Scan
 *    (-78%).
 *
 * NÃO incluído (testado e descartado com evidência):
 * - Índices simples em professional_id/chair_id: testados para a checagem
 *   de conflito de horário (assertNoConflict — WHERE professional_id = ? OR
 *   chair_id = ? AND status... AND start < ? AND end > ?). O Postgres
 *   ignorou os índices e manteve Seq Scan (14,9ms); forçar o uso deles via
 *   enable_seqscan=off piorou para 280ms. Registrado, não criado.
 * - appointment_returns / appointment_tag_assignments: sem volume nenhum
 *   no dataset de benchmark (0 linhas) — sem evidência pra propor
 *   qualquer índice; appointment_tag_assignments já tem PK composta
 *   (appointment_id, patient_tag_id) que cobre consultas por appointment_id
 *   de qualquer forma.
 *
 * Observação registrada, não decidida aqui (é regra de negócio, não
 * performance): consultations não tem UNIQUE em appointment_id, mas
 * checkIn() usa firstOrCreate(['appointment_id' => ...]) — implica uma
 * relação 1:1 que hoje não é garantida no banco. Avaliar em fase própria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['clinic_id', 'start']);
        });

        // Índice funcional — sintaxe específica do Postgres (a suíte de
        // testes roda em SQLite, que não suporta expression indexes assim;
        // não afeta os testes, só o plano de query real em produção/local).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX appointments_start_date_index ON appointments ((("start")::date))');
        }

        Schema::table('consultations', function (Blueprint $table) {
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'start']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS appointments_start_date_index');
        }

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['appointment_id']);
        });
    }
};
