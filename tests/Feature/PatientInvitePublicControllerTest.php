<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\PatientInviteActivityLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\PatientInviteService;

function setupPublicWizardContext(array $inviteOverrides = []): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-public-wizard-' . uniqid(),
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
        'name' => 'Clínica Teste',
        'slug' => 'clinica-public-wizard-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $invite = app(PatientInviteService::class)->create(array_merge([
        'nome' => 'Paciente',
        'sobrenome' => 'Convite',
        'telefone' => '11987650000',
        'kind' => 'cadastro',
        'channel' => 'link_only',
        'expires_in_days' => 7,
    ], $inviteOverrides), $clinic->id, $user->id);

    return compact('clinic', 'user', 'invite');
}

test('a valid invite renders the public wizard with patient data', function () {
    ['invite' => $invite] = setupPublicWizardContext();

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/PublicWizard')
        ->where('token', $invite->token)
        ->where('patient.nome', 'Paciente')
        ->where('invite.current_step', null)
    );
});

test('first open marks opened_at and transitions to visualizado, second open does not repeat it', function () {
    ['invite' => $invite] = setupPublicWizardContext();

    expect($invite->opened_at)->toBeNull();

    $this->get(route('patient-invites.public.show', $invite->token))->assertOk();
    $invite->refresh();
    $firstOpenedAt = $invite->opened_at;

    expect($invite->status)->toBe('visualizado');
    expect($firstOpenedAt)->not->toBeNull();
    expect(PatientInviteActivityLog::where('patient_invite_id', $invite->id)->where('action', 'opened')->count())->toBe(1);

    $this->get(route('patient-invites.public.show', $invite->token))->assertOk();
    $invite->refresh();

    expect($invite->opened_at->eq($firstOpenedAt))->toBeTrue();
    expect(PatientInviteActivityLog::where('patient_invite_id', $invite->id)->where('action', 'opened')->count())->toBe(1);
});

test('an invite expired by timestamp renders Invalid with reason expired, and flips status', function () {
    ['invite' => $invite] = setupPublicWizardContext(['expires_in_days' => 7]);
    $invite->update(['expires_at' => now()->subDay()]);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/Invalid')
        ->where('reason', 'expired')
    );

    expect($invite->fresh()->status)->toBe('expirado');
});

test('a cancelled invite renders Invalid with reason cancelled', function () {
    ['invite' => $invite, 'user' => $user] = setupPublicWizardContext();
    app(PatientInviteService::class)->cancel($invite, cancelledById: $user->id);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/Invalid')
        ->where('reason', 'cancelled')
    );
});

test('a nonexistent token renders Invalid instead of a 404', function () {
    $response = $this->get(route('patient-invites.public.show', 'token-que-nao-existe'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/Invalid')
        ->where('reason', 'expired')
    );
});

test('autosave writes only allowlisted fields and ignores anything else in the payload', function () {
    ['invite' => $invite] = setupPublicWizardContext();

    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), [
        'nome' => 'Novo Nome',
        'cep' => '01310-100',
        'status' => 'inativo',
        'convenio_id' => 999,
        'origem' => 'manual',
        'current_step' => 'endereco',
    ]);

    $response->assertOk();
    $response->assertJsonPath('invite.current_step', 'endereco');

    $patient = $invite->patient->fresh();
    expect($patient->nome)->toBe('Novo Nome');
    expect($patient->cep)->toBe('01310-100');
    expect($patient->status)->toBe('ativo'); // não deve ter mudado
    expect($patient->convenio_id)->toBeNull();
    expect($patient->origem)->toBe('convite'); // não deve ter mudado

    $invite->refresh();
    expect($invite->status)->toBe('em_preenchimento');
    expect($invite->progress)->toBeGreaterThan(0);
});

test('autosave transitions current_step and logs personal_data_completed when leaving dados_pessoais', function () {
    ['invite' => $invite] = setupPublicWizardContext();

    $this->patchJson(route('patient-invites.public.update', $invite->token), [
        'nome' => 'Ana',
        'current_step' => 'dados_pessoais',
    ])->assertOk();

    $this->patchJson(route('patient-invites.public.update', $invite->token), [
        'cep' => '01310-100',
        'current_step' => 'endereco',
    ])->assertOk();

    expect(
        PatientInviteActivityLog::where('patient_invite_id', $invite->id)
            ->where('action', 'personal_data_completed')
            ->exists()
    )->toBeTrue();
});

test('autosave on an expired or cancelled invite is rejected with 410', function () {
    ['invite' => $invite, 'user' => $user] = setupPublicWizardContext();
    app(PatientInviteService::class)->cancel($invite, cancelledById: $user->id);

    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), ['nome' => 'X']);

    $response->assertStatus(410);
});

test('concluir marks the invite as concluido and returns null when there is no upcoming appointment', function () {
    ['invite' => $invite] = setupPublicWizardContext();

    $response = $this->postJson(route('patient-invites.public.complete', $invite->token));

    $response->assertOk();
    $response->assertJsonPath('next_appointment', null);

    $invite->refresh();
    expect($invite->status)->toBe('concluido');
    expect($invite->completed_at)->not->toBeNull();
    expect($invite->progress)->toBe(100);
});

test('concluir returns the next scheduled appointment when one exists', function () {
    ['invite' => $invite, 'clinic' => $clinic, 'user' => $user] = setupPublicWizardContext();

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $invite->patient_id,
        'professional_id' => $user->id,
        'start' => now()->addDays(3),
        'end' => now()->addDays(3)->addHour(),
        'status' => 'scheduled',
    ]);

    $response = $this->postJson(route('patient-invites.public.complete', $invite->token));

    $response->assertOk();
    $response->assertJsonPath('next_appointment.start', $appointment->start->toIso8601String());
});

test('concluir completes the invite even when allow_anamnesis is true (decision confirmed this round)', function () {
    ['invite' => $invite] = setupPublicWizardContext(['allow_anamnesis' => true]);

    $response = $this->postJson(route('patient-invites.public.complete', $invite->token));

    $response->assertOk();
    expect($invite->fresh()->status)->toBe('concluido');
});

// ── Auditoria funcional: convite já concluído (segunda aba, outro
// aparelho, F5 depois de terminar) — achado desta rodada de auditoria.

test('autosave on an already completed invite is rejected with 410 and does not modify the patient', function () {
    ['invite' => $invite] = setupPublicWizardContext();
    app(\App\Services\PatientInviteService::class)->complete($invite);
    $originalNome = $invite->patient->fresh()->nome;

    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), ['nome' => 'Tentativa Tardia']);

    $response->assertStatus(410);
    expect($invite->patient->fresh()->nome)->toBe($originalNome);
});

test('concluir on an already completed invite is rejected with 410', function () {
    ['invite' => $invite] = setupPublicWizardContext();
    app(\App\Services\PatientInviteService::class)->complete($invite);

    $response = $this->postJson(route('patient-invites.public.complete', $invite->token));

    $response->assertStatus(410);
});

test('reopening an already completed invite shows the conclusion screen again, not the editable wizard', function () {
    ['invite' => $invite, 'clinic' => $clinic, 'user' => $user] = setupPublicWizardContext();

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $invite->patient_id,
        'professional_id' => $user->id,
        'start' => now()->addDays(2),
        'end' => now()->addDays(2)->addHour(),
        'status' => 'confirmed',
    ]);

    app(\App\Services\PatientInviteService::class)->complete($invite);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/PublicWizard')
        ->where('conclusion.next_appointment.start', $appointment->start->toIso8601String())
    );
});

// ── Fase 3: etapa de Convênio, condicionada a allow_insurance ──────────────

test('show exposes convenios and convenio fields only when allow_insurance is true', function () {
    ['clinic' => $clinic, 'invite' => $invite] = setupPublicWizardContext(['allow_insurance' => true]);

    $convenio = \App\Models\Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Unimed', 'ativo' => true, 'ordem' => 1]);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/PublicWizard')
        ->where('invite.allow_insurance', true)
        ->where('convenios.0.id', $convenio->id)
        ->has('patient.tipo_atendimento')
        ->has('patient.convenio_id')
    );
});

test('show hides convenios and convenio fields when allow_insurance is false', function () {
    ['clinic' => $clinic, 'invite' => $invite] = setupPublicWizardContext(['allow_insurance' => false]);
    \App\Models\Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Unimed', 'ativo' => true, 'ordem' => 1]);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/PublicWizard')
        ->where('invite.allow_insurance', false)
        ->where('convenios', [])
        ->missing('patient.tipo_atendimento')
        ->missing('patient.convenio_id')
    );
});

test('convenios list is scoped to the invite clinic, not other clinics', function () {
    ['invite' => $invite] = setupPublicWizardContext(['allow_insurance' => true]);

    $otherClinic = Clinic::create([
        'name' => 'Outra Clínica', 'slug' => 'outra-clinica-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active',
        'plan_id' => \App\Models\Plan::first()->id ?? Plan::create([
            'name' => 'Plan2', 'slug' => 'plan2-' . uniqid(), 'is_free' => true,
            'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
            'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
        ])->id,
    ]);
    \App\Models\Convenio::create(['clinic_id' => $otherClinic->id, 'nome' => 'Convênio de outra clínica', 'ativo' => true, 'ordem' => 1]);

    $response = $this->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('convenios', []));
});

test('update rejects current_step convenio and ignores convenio fields when allow_insurance is false', function () {
    ['invite' => $invite] = setupPublicWizardContext(['allow_insurance' => false]);

    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), [
        'convenio_id' => 999,
        'tipo_atendimento' => 'convenio',
        'current_step' => 'convenio',
    ]);

    $response->assertStatus(422);
    expect($invite->patient->fresh()->convenio_id)->toBeNull();
});

test('update saves convenio fields and accepts current_step convenio when allow_insurance is true', function () {
    ['clinic' => $clinic, 'invite' => $invite] = setupPublicWizardContext(['allow_insurance' => true]);
    $convenio = \App\Models\Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Unimed', 'ativo' => true, 'ordem' => 1]);

    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), [
        'tipo_atendimento' => 'convenio',
        'convenio_id' => $convenio->id,
        'convenio_numero_carteirinha' => '123456',
        'current_step' => 'convenio',
    ]);

    $response->assertOk();
    $response->assertJsonPath('invite.current_step', 'convenio');

    $patient = $invite->patient->fresh();
    expect($patient->tipo_atendimento)->toBe('convenio');
    expect($patient->convenio_id)->toBe($convenio->id);
    expect($patient->convenio_numero_carteirinha)->toBe('123456');
});

test('progress denominator reflects whether the invite has the convenio step or not', function () {
    ['invite' => $withInsurance] = setupPublicWizardContext(['allow_insurance' => true]);
    ['invite' => $withoutInsurance] = setupPublicWizardContext(['allow_insurance' => false]);

    // Última etapa antes de concluir, em cada caso.
    $this->patchJson(route('patient-invites.public.update', $withInsurance->token), [
        'current_step' => 'convenio',
    ])->assertOk();

    $this->patchJson(route('patient-invites.public.update', $withoutInsurance->token), [
        'current_step' => 'responsavel_legal',
    ])->assertOk();

    // 4 etapas (convenio = índice 3): round(3/4*100) = 75.
    expect($withInsurance->fresh()->progress)->toBe(75);
    // 3 etapas (responsavel_legal = índice 2): round(2/3*100) = 67.
    expect($withoutInsurance->fresh()->progress)->toBe(67);
});

test('progress does not regress when the patient navigates back to a previous step', function () {
    ['invite' => $invite] = setupPublicWizardContext(['allow_insurance' => true]);

    $this->patchJson(route('patient-invites.public.update', $invite->token), ['current_step' => 'endereco'])->assertOk();
    $this->patchJson(route('patient-invites.public.update', $invite->token), ['current_step' => 'responsavel_legal'])->assertOk();
    $this->patchJson(route('patient-invites.public.update', $invite->token), ['current_step' => 'convenio'])->assertOk();
    expect($invite->fresh()->progress)->toBe(75);

    // Paciente clica "Voltar" para revisar uma etapa já preenchida — os
    // dados das etapas seguintes continuam salvos, então o percentual
    // mostrado à recepção não pode cair como se o trabalho tivesse sido desfeito.
    $response = $this->patchJson(route('patient-invites.public.update', $invite->token), ['current_step' => 'responsavel_legal']);

    $response->assertOk();
    $response->assertJsonPath('invite.current_step', 'responsavel_legal');
    $response->assertJsonPath('invite.progress', 75);
    expect($invite->fresh()->progress)->toBe(75);
});

test('concluir logs insurance_step_completed only when allow_insurance is true', function () {
    ['invite' => $withInsurance] = setupPublicWizardContext(['allow_insurance' => true]);
    ['invite' => $withoutInsurance] = setupPublicWizardContext(['allow_insurance' => false]);

    app(PatientInviteService::class)->complete($withInsurance);
    app(PatientInviteService::class)->complete($withoutInsurance);

    expect(PatientInviteActivityLog::where('patient_invite_id', $withInsurance->id)->where('action', 'insurance_step_completed')->exists())->toBeTrue();
    expect(PatientInviteActivityLog::where('patient_invite_id', $withoutInsurance->id)->where('action', 'insurance_step_completed')->exists())->toBeFalse();
});
