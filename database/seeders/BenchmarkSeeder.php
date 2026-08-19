<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fase B6.0 — dataset sintético SÓ para medir planos de execução reais do
 * PostgreSQL local (EXPLAIN/EXPLAIN ANALYZE) antes de propor índices na B6.
 * Nunca usar dados reais/PII. Nunca chamado por DatabaseSeeder — só via:
 *
 *   php artisan db:seed --class=BenchmarkSeeder
 *
 * Requer um banco Postgres vazio (ver bloco de instruções no fim do
 * arquivo). Usa DB::table()->insert() em lote (sem Eloquent) porque o
 * volume é grande — não é o caminho normal de escrita da aplicação, é só
 * para gerar cardinalidade suficiente pro otimizador do Postgres escolher
 * planos representativos.
 */
class BenchmarkSeeder extends Seeder
{
    private \Faker\Generator $faker;

    private array $nextId = [];

    private Carbon $now;

    public function run(): void
    {
        // Volume alto (centenas de milhares de linhas, arrays intermediários
        // com dezenas/centenas de milhares de entradas) — só para este
        // comando local, não afeta o limite de memória da aplicação em si.
        ini_set('memory_limit', '3G');

        if (! app()->environment('local')) {
            $this->command?->error('BenchmarkSeeder só roda em ambiente local. Abortando.');

            return;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->command?->error('BenchmarkSeeder é só para o Postgres local da B6 (conexão atual: ' . DB::connection()->getDriverName() . '). Abortando.');

            return;
        }

        if (DB::table('patients')->exists()) {
            $this->command?->error('A tabela patients já tem dados. Rode isto só num banco vazio (ver instruções no topo do arquivo). Abortando.');

            return;
        }

        $this->faker = \Faker\Factory::create('pt_BR');
        $this->now = Carbon::now();
        $started = microtime(true);

        $planIds = $this->seedPlans();
        $this->command?->info('Plans: ' . count($planIds));

        $clinics = $this->seedClinicsAndCatalogs($planIds);
        $this->command?->info('Clinics: ' . count($clinics) . ' (com usuários, treatments, convênios, cadeiras, categorias/templates de documento)');

        $patientsByClinic = $this->seedPatients($clinics);
        $totalPatients = array_sum(array_map('count', $patientsByClinic));
        $this->command?->info('Patients: ' . $totalPatients);

        $appointments = $this->seedAppointments($clinics, $patientsByClinic);
        $this->command?->info('Appointments: ' . count($appointments));

        $consultations = $this->seedConsultations($clinics, $appointments);
        $this->command?->info('Consultations: ' . count($consultations));
        unset($appointments); // ~200k entradas — só consumido por seedConsultations, sem isso fica preso em memória até o fim do run()

        $procedureExecutions = $this->seedProcedureExecutions($clinics, $consultations);
        $this->command?->info('Procedure executions: ' . $procedureExecutions);

        $clinicalRecords = $this->seedClinicalRecords($clinics, $consultations);
        $this->command?->info('Clinical records: ' . $clinicalRecords);
        unset($consultations); // idem — só usado pelas duas chamadas acima

        $documents = $this->seedDocuments($clinics, $patientsByClinic);
        $this->command?->info('Documents: ' . $documents);

        $patientTreatments = $this->seedPatientTreatments($clinics, $patientsByClinic);
        $this->command?->info('Patient treatments: ' . $patientTreatments);

        [$budgets, $budgetItems] = $this->seedBudgets($clinics, $patientsByClinic);
        $this->command?->info("Budgets: {$budgets} / Budget items: {$budgetItems}");

        $transactions = $this->seedTransactions($clinics, $patientsByClinic);
        $this->command?->info('Transactions: ' . $transactions);

        $tasks = $this->seedTasks($clinics);
        $this->command?->info('Tasks: ' . $tasks);

        $inventoryItems = $this->seedInventoryItems($clinics);
        $this->command?->info('Inventory items: ' . $inventoryItems);

        $elapsed = round(microtime(true) - $started, 1);
        $this->command?->info("Concluído em {$elapsed}s.");
    }

    // ── Infra de IDs (single-writer, banco garantidamente vazio nestas tabelas) ──

    private function id(string $table): int
    {
        if (! isset($this->nextId[$table])) {
            $this->nextId[$table] = (int) (DB::table($table)->max('id') ?? 0) + 1;
        }

        return $this->nextId[$table]++;
    }

    private function chunkInsert(string $table, array $rows, int $size = 3000): void
    {
        foreach (array_chunk($rows, $size) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    // ── Distribuições auxiliares ──

    /** Enviesado pra baixo (mais valores pequenos que grandes), útil pra "poucos pacientes com muito, muitos com pouco". */
    private function skewedInt(int $min, int $max): int
    {
        $u = mt_rand() / mt_getrandmax();

        return $min + (int) round(($u ** 2.2) * ($max - $min));
    }

    private function pick(array $weighted)
    {
        $total = array_sum($weighted);
        $r = mt_rand(1, $total);
        $acc = 0;
        foreach ($weighted as $key => $weight) {
            $acc += $weight;
            if ($r <= $acc) {
                return $key;
            }
        }

        return array_key_first($weighted);
    }

    /** Data enviesada pra recente: a maioria dos registros nos últimos meses, cauda longa até $maxDaysAgo. */
    private function skewedPastDate(int $maxDaysAgo): Carbon
    {
        $u = mt_rand() / mt_getrandmax();
        $daysAgo = (int) round(($u ** 2) * $maxDaysAgo);

        return $this->now->copy()->subDays($daysAgo)->subMinutes(mt_rand(0, 1439));
    }

    private function futureDate(int $maxDaysAhead): Carbon
    {
        return $this->now->copy()->addDays(mt_rand(1, max(1, $maxDaysAhead)))->addMinutes(mt_rand(0, 1439));
    }

    // skewedPastDate()/futureDate() espalham a hora-do-dia pelas 24h (só o
    // dia importa pra elas) — appointments precisam do teto real da agenda
    // (mesmo fallback de AppointmentSchedulingService::DEFAULT_HOURS,
    // 07:00–21:00), senão o seeder gera horários fora da grade que o backend
    // agora rejeita na criação/edição real. Só usado em seedAppointments();
    // não mexe nos helpers genéricos acima (created_at etc. continuam livres).
    private function clampToBusinessHours(Carbon $date): Carbon
    {
        $hour = mt_rand(7, 19);
        $minute = $this->faker->randomElement([0, 15, 30, 45]);

        return $date->copy()->setTime($hour, $minute);
    }

    // ── Plans ──

    private function seedPlans(): array
    {
        $rows = [];
        $ids = [];
        foreach (['Starter', 'Pro', 'Enterprise'] as $i => $name) {
            $id = $this->id('plans');
            $ids[] = $id;
            $rows[] = [
                'id' => $id, 'name' => $name, 'slug' => 'benchmark-' . Str::slug($name) . '-' . $id,
                'price_monthly_cents' => ($i + 1) * 9900, 'price_yearly_cents' => ($i + 1) * 99000,
                'features' => json_encode([]), 'max_clinics' => 1, 'max_patients' => 100000,
                'max_users' => 50, 'storage_gb' => 50, 'is_free' => $i === 0,
                'is_active' => true, 'is_featured' => false, 'sort_order' => $i,
                'created_at' => $this->now, 'updated_at' => $this->now,
            ];
        }
        $this->chunkInsert('plans', $rows);

        return $ids;
    }

    // ── Clinics + catálogos por clínica (users, treatments, convenios, chairs, document categories/templates) ──

    private function seedClinicsAndCatalogs(array $planIds): array
    {
        // 50 pequenas, 25 médias, 10 grandes ≈ 85 clínicas — distribuição
        // não uniforme de propósito (B6.0 item 4).
        $tiers = array_merge(
            array_fill(0, 50, ['name' => 'small', 'patients' => [15, 80], 'users' => [1, 2], 'chairs' => [1, 2]]),
            array_fill(0, 25, ['name' => 'medium', 'patients' => [120, 320], 'users' => [2, 4], 'chairs' => [2, 4]]),
            array_fill(0, 10, ['name' => 'large', 'patients' => [700, 1500], 'users' => [4, 8], 'chairs' => [4, 8]]),
        );
        shuffle($tiers);

        $docCategoryNames = ['Receitas', 'Atestados', 'Declarações', 'Consentimentos', 'Contratos', 'Documentos personalizados'];
        $clinics = [];

        $clinicRows = [];
        foreach ($tiers as $i => $tier) {
            $clinicId = $this->id('clinics');
            $name = 'Clínica Benchmark ' . ($i + 1);
            $clinicRows[] = [
                'id' => $clinicId, 'name' => $name, 'slug' => Str::slug($name) . '-' . $clinicId,
                'type' => 'odontologia', 'status' => 'active', 'plan_id' => $this->faker->randomElement($planIds),
                'settings' => json_encode([]), 'created_at' => $this->now, 'updated_at' => $this->now,
            ];
            $clinics[] = ['id' => $clinicId, 'tier' => $tier];
        }
        $this->chunkInsert('clinics', $clinicRows);

        $userRows = [];
        $clinicUserRows = [];
        $treatmentRows = [];
        $convenioRows = [];
        $chairRows = [];
        $categoryRows = [];
        $templateRows = [];
        $versionRows = [];

        foreach ($clinics as &$clinic) {
            $tier = $clinic['tier'];
            $clinicId = $clinic['id'];

            $userCount = mt_rand($tier['users'][0], $tier['users'][1]);
            $userIds = [];
            for ($u = 0; $u < $userCount; $u++) {
                $userId = $this->id('users');
                $userIds[] = $userId;
                $userRows[] = [
                    'id' => $userId, 'name' => $this->faker->name(),
                    'email' => 'bench-' . $userId . '@example.test',
                    'email_verified_at' => $this->now, 'password' => Hash::make('benchmark'),
                    'job_title' => $u === 0 ? 'Dentista' : $this->faker->randomElement(['Dentista', 'Recepcionista', 'Auxiliar']),
                    'status' => 'ativo', 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
                $clinicUserRows[] = [
                    'id' => $this->id('clinic_user'), 'clinic_id' => $clinicId, 'user_id' => $userId,
                    'role' => $u === 0 ? 'owner' : 'member', 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            $clinic['userIds'] = $userIds;
            $clinic['professionalIds'] = $userIds; // simplificação: qualquer user da clínica pode ser "profissional" nas FKs

            $treatmentCount = mt_rand(10, 20);
            $treatmentIds = [];
            for ($t = 0; $t < $treatmentCount; $t++) {
                $treatmentId = $this->id('treatments');
                $treatmentIds[] = $treatmentId;
                $preco = mt_rand(80, 3000);
                $treatmentRows[] = [
                    'id' => $treatmentId, 'clinic_id' => $clinicId,
                    'nome' => $this->faker->randomElement(['Restauração', 'Limpeza', 'Canal', 'Extração', 'Clareamento', 'Implante', 'Aparelho', 'Consulta']) . ' ' . $t,
                    'categoria' => $this->faker->randomElement(['Dentística', 'Ortodontia', 'Endodontia', 'Cirurgia', 'Periodontia', 'Estética']),
                    'tipo' => 'procedimento', 'duracao_padrao' => $this->faker->randomElement([30, 45, 60, 90]),
                    'preco_base' => $preco, 'custo_padrao' => (int) round($preco * 0.4),
                    'ativo' => true, 'ordem' => $t, 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            $clinic['treatmentIds'] = $treatmentIds;

            $convenioIds = [];
            foreach (range(1, mt_rand(2, 4)) as $c) {
                $convenioId = $this->id('convenios');
                $convenioIds[] = $convenioId;
                $convenioRows[] = [
                    'id' => $convenioId, 'clinic_id' => $clinicId,
                    'nome' => $this->faker->randomElement(['Particular', 'Amil Dental', 'OdontoPrev', 'SulAmérica', 'Bradesco Dental']),
                    'ativo' => true, 'ordem' => $c, 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            $clinic['convenioIds'] = $convenioIds;

            $chairCount = mt_rand($tier['chairs'][0], $tier['chairs'][1]);
            $chairIds = [];
            for ($c = 0; $c < $chairCount; $c++) {
                $chairId = $this->id('chairs');
                $chairIds[] = $chairId;
                $chairRows[] = [
                    'id' => $chairId, 'clinic_id' => $clinicId, 'name' => 'Cadeira ' . ($c + 1),
                    'color' => $this->faker->hexColor(), 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            $clinic['chairIds'] = $chairIds;

            $categoryIds = [];
            foreach ($docCategoryNames as $ci => $catName) {
                $categoryId = $this->id('document_categories');
                $categoryIds[] = $categoryId;
                $categoryRows[] = [
                    'id' => $categoryId, 'clinic_id' => $clinicId, 'name' => $catName,
                    'slug' => Str::slug($catName) . '-' . $categoryId, 'is_system' => false, 'is_active' => true,
                    'sort_order' => $ci, 'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }

            $templates = [];
            foreach (range(1, mt_rand(6, 10)) as $t) {
                $templateId = $this->id('document_templates');
                $versionId = $this->id('document_template_versions');
                $templates[] = ['templateId' => $templateId, 'versionId' => $versionId];
                $templateRows[] = [
                    'id' => $templateId, 'clinic_id' => $clinicId, 'category_id' => $this->faker->randomElement($categoryIds),
                    'name' => 'Modelo ' . $t, 'slug' => 'modelo-' . $t . '-' . $templateId,
                    'current_version_id' => null, 'requires_patient_signature' => (bool) mt_rand(0, 1),
                    'requires_professional_signature' => false, 'requires_responsible_signature' => false,
                    'requires_witness_signature' => false, 'is_system' => false, 'is_active' => true,
                    'is_default' => false, 'sort_order' => $t, 'created_by_id' => $userIds[0],
                    'created_at' => $this->now, 'updated_at' => $this->now,
                ];
                $versionRows[] = [
                    'id' => $versionId, 'template_id' => $templateId, 'version' => 1,
                    'title' => 'Modelo ' . $t, 'content_html' => '<p>Conteúdo de benchmark.</p>',
                    'created_by_id' => $userIds[0], 'is_archived' => false,
                    'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            $clinic['templates'] = $templates;
        }
        unset($clinic);

        $this->chunkInsert('users', $userRows);
        $this->chunkInsert('clinic_user', $clinicUserRows);
        $this->chunkInsert('treatments', $treatmentRows);
        $this->chunkInsert('convenios', $convenioRows);
        $this->chunkInsert('chairs', $chairRows);
        $this->chunkInsert('document_categories', $categoryRows);
        // document_templates.current_version_id <-> document_template_versions.template_id
        // é uma referência circular — insere templates sem current_version_id,
        // depois as versions (que já podem referenciar o template_id), depois
        // faz o backfill do current_version_id.
        $this->chunkInsert('document_templates', $templateRows);
        $this->chunkInsert('document_template_versions', $versionRows);
        foreach ($clinics as $clinic) {
            foreach ($clinic['templates'] ?? [] as $tpl) {
                DB::table('document_templates')->where('id', $tpl['templateId'])->update(['current_version_id' => $tpl['versionId']]);
            }
        }

        return $clinics;
    }

    // ── Patients ──

    private function seedPatients(array $clinics): array
    {
        $rows = [];
        $patientsByClinic = [];

        foreach ($clinics as $clinic) {
            $count = mt_rand($clinic['tier']['patients'][0], $clinic['tier']['patients'][1]);
            $ids = [];
            for ($p = 0; $p < $count; $p++) {
                $id = $this->id('patients');
                $ids[] = $id;
                $rows[] = [
                    'id' => $id, 'clinic_id' => $clinic['id'],
                    'nome' => $this->faker->firstName(), 'sobrenome' => $this->faker->lastName(),
                    'nascimento' => $this->faker->dateTimeBetween('-90 years', '-2 years')->format('Y-m-d'),
                    'status' => $this->pick(['ativo' => 85, 'inativo' => 15]),
                    'doc_tipo' => 'cpf', 'doc_numero' => $this->faker->numerify('###.###.###-##'),
                    'telefone' => $this->faker->numerify('119########'),
                    'email' => 'paciente-bench-' . $id . '@example.test',
                    'responsible_professional_id' => mt_rand(0, 100) < 60 ? $this->faker->randomElement($clinic['professionalIds']) : null,
                    'convenio_id' => (! empty($clinic['convenioIds']) && mt_rand(0, 100) < 50) ? $this->faker->randomElement($clinic['convenioIds']) : null,
                    'created_at' => $this->skewedPastDate(730), 'updated_at' => $this->now,
                ];
            }
            $patientsByClinic[$clinic['id']] = $ids;
        }

        $this->chunkInsert('patients', $rows);

        return $patientsByClinic;
    }

    // ── Appointments ──

    private function seedAppointments(array $clinics, array $patientsByClinic): array
    {
        $statusWeights = ['completed' => 55, 'scheduled' => 15, 'confirmed' => 10, 'cancelled' => 12, 'no_show' => 8];
        $rows = [];
        $summary = []; // id => ['clinic_id','patient_id','professional_id','status','start']

        foreach ($clinics as $clinic) {
            foreach ($patientsByClinic[$clinic['id']] as $patientId) {
                // Enviesado: muitos pacientes com poucos agendamentos, poucos com muitos.
                $count = $this->skewedInt(0, 35);
                for ($a = 0; $a < $count; $a++) {
                    $id = $this->id('appointments');
                    $status = $this->pick($statusWeights);
                    $start = $status === 'scheduled' || $status === 'confirmed'
                        ? $this->futureDate(60)
                        : $this->skewedPastDate(730);
                    $start = $this->clampToBusinessHours($start);
                    $end = $start->copy()->addMinutes($this->faker->randomElement([30, 45, 60]));
                    $professionalId = $this->faker->randomElement($clinic['professionalIds']);

                    $rows[] = [
                        'id' => $id, 'clinic_id' => $clinic['id'], 'patient_id' => $patientId,
                        'professional_id' => $professionalId,
                        'treatment_id' => $this->faker->randomElement($clinic['treatmentIds']),
                        'chair_id' => $this->faker->randomElement($clinic['chairIds']),
                        'start' => $start, 'end' => $end, 'status' => $status,
                        'reschedule_count' => mt_rand(0, 100) < 10 ? mt_rand(1, 3) : 0,
                        'confirmation_requested' => (bool) mt_rand(0, 1),
                        'created_at' => $start->copy()->subDays(mt_rand(1, 30)), 'updated_at' => $this->now,
                    ];
                    // Guarda a data como string (não o objeto Carbon) — com
                    // ~200k linhas em memória ao mesmo tempo, os objetos
                    // Carbon sozinhos estouravam o limite de memória do PHP.
                    $summary[$id] = [
                        'clinic_id' => $clinic['id'], 'patient_id' => $patientId,
                        'professional_id' => $professionalId, 'status' => $status,
                        'start' => $start->toDateTimeString(),
                    ];

                    if (count($rows) >= 5000) {
                        $this->chunkInsert('appointments', $rows);
                        $rows = [];
                    }
                }
            }
        }
        $this->chunkInsert('appointments', $rows);

        return $summary;
    }

    // ── Consultations (a partir de appointments completed/confirmed) ──

    private function seedConsultations(array $clinics, array $appointments): array
    {
        $rows = [];
        $summary = []; // id => ['clinic_id','patient_id','professional_id','status','finished_at']
        $statusWeights = ['finalizado' => 70, 'aguardando' => 10, 'em_atendimento' => 5, 'cancelado' => 15];

        foreach ($appointments as $apptId => $appt) {
            if (! in_array($appt['status'], ['completed', 'confirmed'], true)) {
                continue;
            }
            // Nem todo appointment completed/confirmed vira consultation.
            if (mt_rand(0, 100) >= 55) {
                continue;
            }

            $id = $this->id('consultations');
            $status = $appt['status'] === 'completed' ? $this->pick($statusWeights) : 'aguardando';
            $checkIn = Carbon::parse($appt['start'])->addMinutes(mt_rand(-10, 10));
            $started = $status !== 'aguardando' ? $checkIn->copy()->addMinutes(mt_rand(1, 15)) : null;
            $finished = $status === 'finalizado' ? $started->copy()->addMinutes(mt_rand(15, 90)) : null;

            $rows[] = [
                'id' => $id, 'clinic_id' => $appt['clinic_id'], 'patient_id' => $appt['patient_id'],
                'professional_id' => $appt['professional_id'], 'appointment_id' => $apptId,
                'status' => $status, 'check_in_at' => $checkIn, 'started_at' => $started, 'finished_at' => $finished,
                'created_at' => $checkIn, 'updated_at' => $this->now,
            ];
            // Mesma razão da appointments: string, não Carbon, pra não
            // acumular centenas de milhares de objetos em memória.
            $summary[$id] = [
                'clinic_id' => $appt['clinic_id'], 'patient_id' => $appt['patient_id'],
                'professional_id' => $appt['professional_id'], 'status' => $status,
                'finished_at' => $finished?->toDateTimeString(),
            ];

            if (count($rows) >= 5000) {
                $this->chunkInsert('consultations', $rows);
                $rows = [];
            }
        }
        $this->chunkInsert('consultations', $rows);

        return $summary;
    }

    // ── Procedure executions (1-3 por consultation finalizado) ──

    private function seedProcedureExecutions(array $clinics, array $consultations): int
    {
        $treatmentsByClinic = [];
        foreach ($clinics as $c) {
            $treatmentsByClinic[$c['id']] = $c['treatmentIds'];
        }

        $rows = [];
        $total = 0;
        foreach ($consultations as $consultationId => $c) {
            if ($c['status'] !== 'finalizado') {
                continue;
            }
            foreach (range(1, mt_rand(1, 3)) as $_) {
                $rows[] = [
                    'id' => $this->id('procedure_executions'), 'clinic_id' => $c['clinic_id'],
                    'consultation_id' => $consultationId,
                    'treatment_id' => $this->faker->randomElement($treatmentsByClinic[$c['clinic_id']]),
                    'executed_at' => $c['finished_at'], 'price_charged' => mt_rand(80, 3000),
                    'created_at' => $c['finished_at'], 'updated_at' => $this->now,
                ];
                $total++;
                if (count($rows) >= 5000) {
                    $this->chunkInsert('procedure_executions', $rows);
                    $rows = [];
                }
            }
        }
        $this->chunkInsert('procedure_executions', $rows);

        return $total;
    }

    // ── Clinical records (a partir de consultations finalizado) ──

    private function seedClinicalRecords(array $clinics, array $consultations): int
    {
        $rows = [];
        $total = 0;
        foreach ($consultations as $consultationId => $c) {
            if ($c['status'] !== 'finalizado' || mt_rand(0, 100) >= 85) {
                continue;
            }
            $finishedAt = Carbon::parse($c['finished_at']);
            $started = $finishedAt->copy()->subMinutes(mt_rand(15, 60));
            $rows[] = [
                'id' => $this->id('clinical_records'), 'clinic_id' => $c['clinic_id'], 'patient_id' => $c['patient_id'],
                'professional_id' => $c['professional_id'], 'consultation_id' => $consultationId,
                'procedure_name' => $this->faker->randomElement(['Restauração', 'Limpeza', 'Canal', 'Extração', 'Avaliação']),
                'procedure_category' => $this->faker->randomElement(['Dentística', 'Ortodontia', 'Endodontia', 'Cirurgia']),
                'status' => $this->pick(['concluido' => 90, 'cancelado' => 10]),
                'started_at' => $started, 'finished_at' => $finishedAt,
                'duration_minutes' => $finishedAt->diffInMinutes($started),
                'price' => mt_rand(80, 3000),
                'created_at' => $finishedAt, 'updated_at' => $this->now,
            ];
            $total++;
            if (count($rows) >= 5000) {
                $this->chunkInsert('clinical_records', $rows);
                $rows = [];
            }
        }
        $this->chunkInsert('clinical_records', $rows);

        return $total;
    }

    // ── Documents ──

    private function seedDocuments(array $clinics, array $patientsByClinic): int
    {
        $statusWeights = ['completed' => 60, 'issued' => 15, 'awaiting_signature' => 15, 'cancelled' => 10];
        $rows = [];
        $total = 0;
        $counter = 0;

        foreach ($clinics as $clinic) {
            if (empty($clinic['templates'])) {
                continue;
            }
            foreach ($patientsByClinic[$clinic['id']] as $patientId) {
                $count = mt_rand(0, 100) < 40 ? 0 : mt_rand(1, 6); // ~40% dos pacientes sem documento
                for ($d = 0; $d < $count; $d++) {
                    $template = $this->faker->randomElement($clinic['templates']);
                    $createdAt = $this->skewedPastDate(730);
                    $counter++;
                    $rows[] = [
                        'id' => $this->id('documents'), 'clinic_id' => $clinic['id'], 'patient_id' => $patientId,
                        'template_id' => $template['templateId'], 'template_version_id' => $template['versionId'],
                        'template_name' => 'Modelo Benchmark', 'professional_id' => $this->faker->randomElement($clinic['professionalIds']),
                        'status' => $this->pick($statusWeights), 'rendered_html' => '<p>Conteúdo de benchmark.</p>',
                        'document_code' => 'BENCH-' . $counter . '-' . Str::random(6),
                        'created_by_id' => $this->faker->randomElement($clinic['professionalIds']),
                        'created_at' => $createdAt, 'updated_at' => $createdAt,
                    ];
                    $total++;
                    if (count($rows) >= 3000) {
                        $this->chunkInsert('documents', $rows);
                        $rows = [];
                    }
                }
            }
        }
        $this->chunkInsert('documents', $rows);

        return $total;
    }

    // ── Patient treatments ──

    private function seedPatientTreatments(array $clinics, array $patientsByClinic): int
    {
        $statusWeights = ['concluido' => 55, 'futuro' => 25, 'em_andamento' => 20];
        $rows = [];
        $total = 0;
        $counter = 0;

        foreach ($clinics as $clinic) {
            foreach ($patientsByClinic[$clinic['id']] as $patientId) {
                $count = $this->skewedInt(0, 25);
                for ($t = 0; $t < $count; $t++) {
                    $counter++;
                    $date = $this->skewedPastDate(730);
                    $status = $this->pick($statusWeights);
                    $value = mt_rand(80, 3000);
                    $rows[] = [
                        'id' => $this->id('patient_treatments'), 'clinic_id' => $clinic['id'], 'patient_id' => $patientId,
                        'treatment_id' => $this->faker->randomElement($clinic['treatmentIds']),
                        'procedure_name' => $this->faker->randomElement(['Restauração', 'Canal', 'Extração', 'Limpeza', 'Clareamento']),
                        'professional_id' => $this->faker->randomElement($clinic['professionalIds']),
                        'convenio_id' => (! empty($clinic['convenioIds']) && mt_rand(0, 1)) ? $this->faker->randomElement($clinic['convenioIds']) : null,
                        'budget_code' => 'TRT-' . $date->format('ymd') . '-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                        'tooth' => mt_rand(0, 100) < 70 ? (string) mt_rand(11, 48) : null,
                        'value_charged' => $value, 'cost' => (int) round($value * 0.4), 'status' => $status,
                        'treatment_date' => $date->format('Y-m-d'),
                        'completed_at' => $status === 'concluido' ? $date : null,
                        'created_at' => $date, 'updated_at' => $this->now,
                    ];
                    $total++;
                    if (count($rows) >= 5000) {
                        $this->chunkInsert('patient_treatments', $rows);
                        $rows = [];
                    }
                }
            }
        }
        $this->chunkInsert('patient_treatments', $rows);

        return $total;
    }

    // ── Budgets + items ──

    private function seedBudgets(array $clinics, array $patientsByClinic): array
    {
        $statusWeights = ['aprovado' => 35, 'rascunho' => 25, 'rejeitado' => 15, 'convertido' => 25];
        $budgetRows = [];
        $itemRows = [];
        $totalBudgets = 0;
        $totalItems = 0;

        foreach ($clinics as $clinic) {
            foreach ($patientsByClinic[$clinic['id']] as $patientId) {
                $count = mt_rand(0, 100) < 70 ? 0 : mt_rand(1, 3);
                for ($b = 0; $b < $count; $b++) {
                    $budgetId = $this->id('budgets');
                    $createdAt = $this->skewedPastDate(730);
                    $itemCount = mt_rand(1, 4);
                    $total = 0;
                    for ($i = 0; $i < $itemCount; $i++) {
                        $qty = mt_rand(1, 3);
                        $unit = mt_rand(80, 1500);
                        $lineTotal = $qty * $unit;
                        $total += $lineTotal;
                        $itemRows[] = [
                            'id' => $this->id('budget_items'), 'budget_id' => $budgetId,
                            'treatment_id' => $this->faker->randomElement($clinic['treatmentIds']),
                            'descricao' => 'Item de orçamento benchmark', 'quantidade' => $qty,
                            'preco_unitario' => $unit, 'total' => $lineTotal,
                            'created_at' => $createdAt, 'updated_at' => $createdAt,
                        ];
                        $totalItems++;
                    }
                    $budgetRows[] = [
                        'id' => $budgetId, 'clinic_id' => $clinic['id'], 'patient_id' => $patientId,
                        'status' => $this->pick($statusWeights), 'total' => $total,
                        'valid_until' => $createdAt->copy()->addDays(30)->format('Y-m-d'),
                        'created_at' => $createdAt, 'updated_at' => $createdAt,
                    ];
                    $totalBudgets++;

                    // budget_items.budget_id referencia budgets — sempre
                    // grava os budgets pendentes ANTES de gravar items que
                    // possam apontar pra eles (items acumulam mais rápido
                    // que budgets, então o gatilho de 3000 batia neles
                    // primeiro e violava a FK).
                    if (count($itemRows) >= 3000) {
                        if (! empty($budgetRows)) {
                            $this->chunkInsert('budgets', $budgetRows);
                            $budgetRows = [];
                        }
                        $this->chunkInsert('budget_items', $itemRows);
                        $itemRows = [];
                    }
                    if (count($budgetRows) >= 3000) {
                        $this->chunkInsert('budgets', $budgetRows);
                        $budgetRows = [];
                    }
                }
            }
        }
        $this->chunkInsert('budgets', $budgetRows);
        $this->chunkInsert('budget_items', $itemRows);

        return [$totalBudgets, $totalItems];
    }

    // ── Transactions (financeiro) — ~100k, ponderado pelo tamanho da clínica ──

    private function seedTransactions(array $clinics, array $patientsByClinic): int
    {
        $tipoWeights = ['receita' => 70, 'despesa' => 30];
        $statusWeights = ['pago' => 65, 'pendente' => 25, 'cancelado' => 10];
        $categorias = ['Consulta', 'Procedimento', 'Material', 'Salário', 'Aluguel', 'Convênio', 'Outros'];
        $rows = [];
        $total = 0;

        foreach ($clinics as $clinic) {
            $patientPool = $patientsByClinic[$clinic['id']];
            if (empty($patientPool)) {
                continue;
            }
            // Clínicas maiores (mais pacientes) geram proporcionalmente mais
            // transações — não uniforme entre clínicas.
            $count = (int) round(count($patientPool) * 10 * (0.7 + (mt_rand(0, 60) / 100)));
            for ($t = 0; $t < $count; $t++) {
                $date = $this->skewedPastDate(730);
                $tipo = $this->pick($tipoWeights);
                $status = $this->pick($statusWeights);
                $linkToPatient = mt_rand(0, 100) < 80;
                $rows[] = [
                    'id' => $this->id('transactions'), 'clinic_id' => $clinic['id'],
                    'patient_id' => $linkToPatient ? $this->faker->randomElement($patientPool) : null,
                    'tipo' => $tipo, 'valor' => mt_rand(50, 5000),
                    'categoria' => $this->faker->randomElement($categorias),
                    'descricao' => 'Lançamento benchmark',
                    'caixa' => 'principal', 'status' => $status,
                    'vencimento' => $date->format('Y-m-d'),
                    'pago_em' => $status === 'pago' ? $date : null,
                    'created_at' => $date, 'updated_at' => $this->now,
                ];
                $total++;
                if (count($rows) >= 5000) {
                    $this->chunkInsert('transactions', $rows);
                    $rows = [];
                }
            }
        }
        $this->chunkInsert('transactions', $rows);

        return $total;
    }

    // ── Tasks — clínicas maiores tendem a ter mais tarefas, mas não estritamente proporcional ──

    private function seedTasks(array $clinics): int
    {
        $statusWeights = ['todo' => 30, 'doing' => 20, 'done' => 45, 'waiting' => 5];
        $priorityWeights = ['baixa' => 30, 'media' => 40, 'alta' => 20, 'urgente' => 10];
        $rows = [];
        $total = 0;

        foreach ($clinics as $clinic) {
            $base = match ($clinic['tier']['name']) {
                'small' => mt_rand(20, 80),
                'medium' => mt_rand(100, 300),
                'large' => mt_rand(300, 700),
            };
            for ($t = 0; $t < $base; $t++) {
                $status = $this->pick($statusWeights);
                $createdAt = $this->skewedPastDate(365);
                $completedAt = $status === 'done' ? $createdAt->copy()->addDays(mt_rand(0, 20)) : null;
                $rows[] = [
                    'id' => $this->id('tasks'), 'clinic_id' => $clinic['id'],
                    'title' => 'Tarefa benchmark ' . ($t + 1),
                    'status' => $status, 'priority' => $this->pick($priorityWeights),
                    'assigned_to' => mt_rand(0, 100) < 85 ? $this->faker->randomElement($clinic['userIds']) : null,
                    'created_by' => $this->faker->randomElement($clinic['userIds']),
                    'due_date' => mt_rand(0, 100) < 50 ? $this->faker->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d') : null,
                    'position' => $t, 'completed_at' => $completedAt,
                    'created_at' => $createdAt, 'updated_at' => $completedAt ?? $createdAt,
                ];
                $total++;
                if (count($rows) >= 5000) {
                    $this->chunkInsert('tasks', $rows);
                    $rows = [];
                }
            }
        }
        $this->chunkInsert('tasks', $rows);

        return $total;
    }

    // ── Inventory items ──

    private function seedInventoryItems(array $clinics): int
    {
        $condicaoWeights = ['bom' => 75, 'vencendo' => 15, 'vencido' => 10];
        $rows = [];
        $total = 0;

        foreach ($clinics as $clinic) {
            $base = match ($clinic['tier']['name']) {
                'small' => mt_rand(20, 60),
                'medium' => mt_rand(60, 150),
                'large' => mt_rand(150, 400),
            };
            for ($i = 0; $i < $base; $i++) {
                $qty = mt_rand(0, 200);
                $rows[] = [
                    'id' => $this->id('inventory_items'), 'clinic_id' => $clinic['id'],
                    'nome' => $this->faker->randomElement(['Luva', 'Máscara', 'Anestésico', 'Resina', 'Sugador', 'Broca', 'Algodão', 'Seringa']) . ' ' . $i,
                    'marca' => $this->faker->company(),
                    'validade' => $this->faker->dateTimeBetween('-1 year', '+2 years')->format('Y-m-d'),
                    'custo_unitario' => mt_rand(1, 500),
                    'quantidade' => $qty, 'quantidade_minima' => mt_rand(5, 30),
                    'condicao' => $this->pick($condicaoWeights),
                    'created_at' => $this->skewedPastDate(730), 'updated_at' => $this->now,
                ];
                $total++;
                if (count($rows) >= 5000) {
                    $this->chunkInsert('inventory_items', $rows);
                    $rows = [];
                }
            }
        }
        $this->chunkInsert('inventory_items', $rows);

        return $total;
    }
}

/*
 * ── Reprodutibilidade ───────────────────────────────────────────────────
 *
 * Gerar do zero (destrói qualquer dado no Postgres local):
 *
 *   docker compose down -v postgres   # remove o volume — TODO dado local some
 *   docker compose up -d postgres
 *   php artisan migrate --force
 *   php artisan db:seed --class=BenchmarkSeeder
 *
 * Recriar só o dataset (mantendo o container/volume, banco já migrado):
 *
 *   php artisan migrate:fresh --force
 *   php artisan db:seed --class=BenchmarkSeeder
 *
 * Nenhum dos dois é executado automaticamente por este seeder — o
 * comando "down -v" em especial é destrutivo e deve ser rodado manualmente
 * só quando o dataset precisar ser regenerado.
 */
