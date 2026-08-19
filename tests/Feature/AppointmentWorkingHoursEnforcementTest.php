<?php

use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\ClinicUserPivot;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

// CORREÇÃO — AGENDAMENTOS FORA DO HORÁRIO DA GRADE. Reproduz exatamente o bug
// relatado (BenchmarkSeeder gerando appointments de madrugada, ex. 01:15,
// 03:22): um profissional que é membro da clínica (tem pivot em clinic_user)
// mas nunca configurou working_start/working_end tinha, antes desta correção,
// ZERO restrição de horário — effectiveWorkingHours() retornava null e isso
// era lido como "sem restrição" tanto em AppointmentSchedulingService quanto
// em useEffectiveSchedule.js (frontend). A correção usa o MESMO fallback que
// já existia em AppointmentSchedulingService::dayAvailability() e na própria
// grade visual (GRID_FLOOR_HOUR/GRID_CEIL_HOUR): 07:00–21:00.
//
// Suite dedicada (em vez de só estender AppointmentSchedulingServiceTest ou
// AgendaAvailabilityTest) pra mapear 1:1 os cenários A–K pedidos, com nomes
// de função e helpers exclusivos deste arquivo (Pest carrega todo teste no
// mesmo processo — nomes de função de outros arquivos de teste já existem:
// setupSchedulingServiceContext()/nextSchedMonday() e
// setupAgendaAvailabilityContext()/nextWeekday()).

function setupWorkingHoursEnforcementContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-wh-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Horário',
        'slug' => 'clinica-horario-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    // Membro da clínica (tem pivot), mas working_start/working_end nunca
    // configurados — é exatamente o estado gerado pelo BenchmarkSeeder
    // (clinic_user inserido em massa, sem esses dois campos).
    $professional = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($professional->id, ['role' => 'professional']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Teste', 'status' => 'ativo']);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'duracao_padrao' => 30, 'preco_base' => 100, 'ativo' => true,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('clinic', 'professional', 'patient', 'treatment');
}

// Próxima segunda-feira a partir de "agora" — dia da semana determinístico,
// independente de quando a suíte roda.
function nextWorkingHoursMonday(): Carbon
{
    return now()->next(Carbon::MONDAY)->startOfDay();
}

// ── A: criar no horário de abertura (permitido) ──────────────────────────

test('A: creating exactly at the default opening time (07:00) is allowed when hours are unconfigured', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday()->setTime(7, 0);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(1);
});

// ── B: criar antes da abertura (rejeitado) ────────────────────────────────

test('B: creating before the default opening time (06:45) is rejected', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday()->setTime(6, 45);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(0);
});

// ── C: criar dentro da janela permitida, com duração que cabe (permitido) ─

test('C: creating within the allowed window when the full duration still fits before closing is allowed', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    // 20:30 + 30min (duração do tratamento) termina exatamente às 21:00 — cabe.
    $monday = nextWorkingHoursMonday()->setTime(20, 30);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(1);
});

// ── D: criar terminando depois do fechamento (rejeitado) ──────────────────

test('D: creating an appointment whose duration would end after closing is rejected even though the start time alone looks valid', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    // 20:45 + 30min termina 21:15 — passa do teto de 21:00.
    $monday = nextWorkingHoursMonday()->setTime(20, 45);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(0);
});

// ── E: criar num dia sem atendimento (rejeitado) ──────────────────────────

test('E: creating on a day the professional does not work is rejected', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => false, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
    ]);
    $monday = nextWorkingHoursMonday()->setTime(10, 0);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(0);
});

// ── F: editar para antes da abertura (rejeitado) ──────────────────────────

test('F: editing an existing appointment to move it to before opening is rejected', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday();

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 0),
        'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($professional)->putJson(route('appointments.update', $appointment->id), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->copy()->setTime(5, 0)->toDateTimeString(), 'status' => 'scheduled',
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect($appointment->refresh()->start->format('H:i'))->toBe('10:00');
});

// ── G: editar excedendo o fechamento (rejeitado) ──────────────────────────

test('G: editing an existing appointment to a start time whose duration would exceed closing is rejected', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday();

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 0),
        'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($professional)->putJson(route('appointments.update', $appointment->id), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->copy()->setTime(20, 45)->toDateTimeString(), 'status' => 'scheduled',
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect($appointment->refresh()->start->format('H:i'))->toBe('10:00');
});

// ── H: arrastar/soltar fora do horário (rejeitado/prevenido) ─────────────
// Não existe endpoint separado de "mover por drag" — a única interação de
// arrastar na Agenda é "arrastar pra selecionar um novo intervalo" (Excel-
// style, useAgendaDragSelect), que só pré-preenche o modal de criação; o
// clique final em "Salvar" sempre passa por este MESMO store() (e por
// update(), quando a edição é reaberta e o horário é alterado). Não há
// nenhum outro caminho (nem PATCH parcial, nem endpoint de "move") que
// grave start/end sem passar por uma dessas duas rotas — confirmado lendo
// routes/web.php e AppointmentController por inteiro. Este teste cobre esse
// caminho na prática: o resultado de um drag-select fora da grade chega ao
// backend como uma chamada comum de store(), e é rejeitado normalmente.

test('H: the payload a drag-select outside the grid would produce (create via store) is rejected the same way as any other invalid time', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday()->setTime(2, 0); // reproduz o bug relatado (madrugada)

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(0);
});

// ── I: manipulação direta da API (rejeitado) ──────────────────────────────
// Simula um cliente que nunca passou pelo formulário/validação do frontend —
// chamada JSON direta com um horário fora da grade, sem nenhum estado de UI
// envolvido. Prova que a regra é aplicada no backend independentemente do
// que o frontend teria permitido ou bloqueado.

test('I: a raw API request crafted with an out-of-grid time, bypassing any frontend form entirely, is rejected', function () {
    ['professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday()->setTime(3, 22); // mesmo horário visto nos dados de benchmark

    $response = $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
        'duration_minutes' => 30,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['start']);
    expect($response->json('errors.start.0'))->toContain('fora do horário de atendimento');
    expect(Appointment::where('professional_id', $professional->id)->count())->toBe(0);
});

// ── J: um agendamento válido continua funcionando normalmente (regressão) ─

test('J: a normal, valid appointment continues to be created, listed and editable without any interference from the new rule', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient, 'treatment' => $treatment] = setupWorkingHoursEnforcementContext();
    $monday = nextWorkingHoursMonday()->setTime(14, 0);

    $this->actingAs($professional)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertRedirect();

    $appointment = Appointment::where('professional_id', $professional->id)->firstOrFail();

    $this->actingAs($professional)
        ->get(route('appointments.index', ['week' => $monday->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('appointments', 1)->where('appointments.0.id', $appointment->id));

    // Edição de um campo que não mexe no horário (status) segue livre.
    $this->actingAs($professional)->putJson(route('appointments.update', $appointment->id), [
        'patient_id' => $patient->id, 'professional_id' => $professional->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(), 'status' => 'confirmed',
    ])->assertRedirect();

    expect($appointment->refresh()->status)->toBe('confirmed');
});

// ── K: intervalo/pausa (não existe esse conceito no projeto) ──────────────
// Não há break_start/break_end, intervalo de almoço nem qualquer campo
// equivalente em clinic_user ou em Clinic — o único mecanismo de "buraco no
// meio do expediente" já coberto é o de CONFLITO (outro appointment
// ocupando aquele intervalo, ver AppointmentSchedulingServiceTest.php:
// "booking the exact same start/end..."/"a one-minute overlap..."). Este
// teste documenta essa ausência de forma verificável (schema real, não só
// comentário), pra não reintroduzir silenciosamente essa lacuna se alguém
// assumir que ela existe.

test('K: there is no break/interval concept in the schedule model — only start/end window plus conflict-based gaps', function () {
    expect(Schema::hasColumn('clinic_user', 'working_start'))->toBeTrue();
    expect(Schema::hasColumn('clinic_user', 'working_end'))->toBeTrue();
    expect(Schema::hasColumn('clinic_user', 'break_start'))->toBeFalse();
    expect(Schema::hasColumn('clinic_user', 'break_end'))->toBeFalse();
    expect(Schema::hasColumn('clinics', 'break_start'))->toBeFalse();
    expect(Schema::hasColumn('clinics', 'break_end'))->toBeFalse();
});
