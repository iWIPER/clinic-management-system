<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;

// Auditoria de segurança — 4 lacunas na trilha de AccessLog: login falho
// nunca era registrado, ACTION_PASSWORD_CHANGED existia mas nunca era
// usado, e exportação de pagamentos de paciente / logs de acesso não
// deixavam rastro. Corrigido seguindo os padrões já existentes de
// AccessLog::record() — nenhum sistema de auditoria novo foi criado.

function setupAccessLogAuditContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-audit-' . uniqid(),
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
        'name' => 'Clínica Auditoria',
        'slug' => 'clinica-auditoria-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    return compact('clinic', 'user');
}

test('a failed login attempt is recorded in AccessLog', function () {
    ['user' => $user] = setupAccessLogAuditContext();
    $user->forceFill(['password' => bcrypt('senha-correta')])->save();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-errada',
    ])->assertSessionHasErrors('email');

    $log = AccessLog::where('action', AccessLog::ACTION_LOGIN_FAILED)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['email'] ?? null)->toBe($user->email);
});

test('a successful password change is recorded in AccessLog', function () {
    ['user' => $user] = setupAccessLogAuditContext();
    $user->forceFill(['password' => bcrypt('senha-antiga')])->save();

    $this->actingAs($user)
        ->patch(route('profile.password'), [
            'current_password' => 'senha-antiga',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])
        ->assertSessionHasNoErrors();

    expect(AccessLog::where('action', AccessLog::ACTION_PASSWORD_CHANGED)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('exporting a patient payments CSV is recorded in AccessLog', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAccessLogAuditContext();
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'X', 'status' => 'ativo']);

    $this->actingAs($user)
        ->get(route('patients.payments.export', [$patient, 'format' => 'csv']))
        ->assertOk();

    $log = AccessLog::where('action', AccessLog::ACTION_PATIENT_PAYMENTS_EXPORTED)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['patient_id'] ?? null)->toBe($patient->id);
});

test('exporting the access log CSV is itself recorded in AccessLog', function () {
    ['user' => $user] = setupAccessLogAuditContext();

    $this->actingAs($user)
        ->get(route('access-logs.export'))
        ->assertOk();

    expect(AccessLog::where('action', AccessLog::ACTION_ACCESS_LOG_EXPORTED)->exists())->toBeTrue();
});
