<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B6.1.4 — comprovado com EXPLAIN ANALYZE no dataset de benchmark
 * (Postgres 16, ~70k consultations / ~83k procedure_executions).
 * consultations já tinha patient_id (B6.1.1) e appointment_id (B6.1.2) —
 * nada em clinic_id/status/check_in_at. procedure_executions não tinha
 * nenhum índice além da PK.
 *
 * 1) consultations(clinic_id, status, check_in_at) — a query real de
 *    ConsultationController::index() (tela de atendimentos — "sala de
 *    espera") filtra por clinic_id + status (lista padrão: IN
 *    ('aguardando','em_atendimento'); filtro explícito: status = 'x') e
 *    ordena por check_in_at desc, tanto para o count() da paginação quanto
 *    para a listagem. Sem índice, Seq Scan na tabela inteira nos dois
 *    casos. Medido (via EXPLAIN ANALYZE direto — a primeira medição via
 *    Eloquent numa conexão fria mostrou 435ms/127ms, artefato de
 *    warmup de conexão, não custo real de banco; descartado):
 *      count() multi-status:  10,7ms Seq Scan  → 3,3ms Index Only Scan (-69%)
 *      lista multi-status:    10,5ms Seq Scan+Sort → 1,2ms (Sort restante é
 *        sobre o resultado já filtrado, praticamente grátis) (-89%)
 *      lista status único:     — → 0,38ms, Index Scan Backward, SEM Sort
 *        (um único valor de status deixa o índice já entregar a ordem certa)
 *    Generalizado: clínica grande (52), clínica pequena (57) — mesmo padrão.
 *
 * 2) procedure_executions(consultation_id) — usado por
 *    ClinicalRecordController ao montar o registro/PDF do atendimento
 *    (ProcedureExecution::where('consultation_id', ...)). Medido: 8,98ms
 *    Seq Scan → 1,09ms Index Scan (-88%). Testado também o caso "não
 *    encontrado" (consultation_id inexistente): 0,40ms, sem regressão.
 *
 * VALIDADO, SEM ALTERAÇÃO (já adequados — não mexido):
 * - consultations(patient_id), consultations(appointment_id): confirmados
 *   em uso (Index Scan) nas B6.1.1/B6.1.2, sem necessidade de revisão.
 * - clinical_records(patient_id, finished_at), (consultation_id) unique,
 *   (clinic_id, finished_at): os três continuam em uso via Index
 *   Scan/Index Scan Backward, 0,25ms–9,7ms — nenhum precisa de mudança.
 *
 * SEM DADO NO BENCHMARK — registrado, não fabricado:
 * - clinical_evolutions: 0 linhas no dataset (não populada na B6.0). O
 *   índice (patient_id, recorded_at) já existente parece compatível com o
 *   formato de queries usado, mas isso não foi comprovado por medição
 *   aqui.
 *
 * SEM QUERY REAL — nada testado (rule: não inventar):
 * - Nenhum filtro real por professional_id em consultations foi encontrado
 *   no código (grep em ConsultationController e services) — não há
 *   hipótese pra testar.
 *
 * DÉBITO DE ARQUITETURA/INTEGRIDADE — registrado, NÃO corrigido nesta fase
 * (é regra de negócio, B6.1 é performance): consultations.appointment_id
 * não tem UNIQUE, mas AppointmentController::checkIn()/
 * ConsultationController::checkIn() usam firstOrCreate(['appointment_id' =>
 * ...]), implicando uma relação 1:1 não garantida no banco. Já anotado na
 * migration da B6.1.2; reafirmado aqui por aparecer de novo no fluxo de
 * check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->index(['clinic_id', 'status', 'check_in_at']);
        });

        Schema::table('procedure_executions', function (Blueprint $table) {
            $table->index('consultation_id');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'status', 'check_in_at']);
        });

        Schema::table('procedure_executions', function (Blueprint $table) {
            $table->dropIndex(['consultation_id']);
        });
    }
};
