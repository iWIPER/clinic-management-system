<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientInvite;
use App\Models\Plan;
use App\Models\User;
use App\Services\PatientInviteService;

function setupPatientInviteHttpContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-invite-http-' . uniqid(),
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
        'slug' => 'clinica-invite-http-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    return compact('clinic', 'user');
}

test('store creates a patient invite and returns share data', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->postJson(route('patient-invites.store'), [
            'nome' => 'Maria',
            'sobrenome' => 'Silva',
            'telefone' => '11987654321',
            'kind' => 'cadastro',
            'channel' => 'link_only',
            'expires_in_days' => 7,
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'invite' => ['id', 'kind', 'status', 'channel'],
        'share'  => ['url', 'qrcode_url', 'whatsapp_url'],
    ]);
    $response->assertJsonPath('invite.status', 'gerado');
    $response->assertJsonPath('invite.kind', 'cadastro');

    $patient = Patient::where('nome', 'Maria')->where('sobrenome', 'Silva')->firstOrFail();
    expect($patient->origem)->toBe('convite');
    expect(PatientInvite::where('patient_id', $patient->id)->count())->toBe(1);
});

test('store rejects the email channel when the patient has no email', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->postJson(route('patient-invites.store'), [
            'nome' => 'Joao',
            'sobrenome' => 'Souza',
            'telefone' => '11911112222',
            'kind' => 'cadastro',
            'channel' => 'email',
            'expires_in_days' => 7,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

test('check-phone finds an existing patient by phone within the same clinic', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Ana',
        'sobrenome' => 'Costa',
        'telefone' => '11999990000',
        'status' => 'ativo',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->getJson(route('patient-invites.check-phone', ['telefone' => '11999990000']));

    $response->assertOk();
    $response->assertJsonPath('patient.id', $patient->id);
    $response->assertJsonPath('active_invite', null);
});

test('check-phone returns null when no patient matches the phone', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->getJson(route('patient-invites.check-phone', ['telefone' => '11900000000']));

    $response->assertOk();
    $response->assertJsonPath('patient', null);
});

test('qrcode endpoint returns an svg image for a valid invite', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $invite = app(PatientInviteService::class)->create([
        'nome' => 'Carla',
        'sobrenome' => 'Nunes',
        'telefone' => '11988887777',
        'kind' => 'cadastro',
        'channel' => 'link_only',
        'expires_in_days' => 7,
    ], $clinic->id, $user->id);

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patient-invites.qrcode', $invite));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->getContent())->toContain('<?xml');
});

test('regenerate cancels the previous invite and creates a new one', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $invite = app(PatientInviteService::class)->create([
        'nome' => 'Bruno',
        'sobrenome' => 'Alves',
        'telefone' => '11977776666',
        'kind' => 'cadastro',
        'channel' => 'link_only',
        'expires_in_days' => 7,
    ], $clinic->id, $user->id);

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->postJson(route('patient-invites.regenerate', $invite));

    $response->assertOk();
    $newId = $response->json('invite.id');

    expect($newId)->not->toBe($invite->id);
    expect($invite->fresh()->status)->toBe('cancelado');

    $log = $invite->activityLogs()->latest('id')->first();
    expect($log->action)->toBe('auto_cancelled_by_new_invite');
});

test('cancel marks the invite as cancelado', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $invite = app(PatientInviteService::class)->create([
        'nome' => 'Diego',
        'sobrenome' => 'Pires',
        'telefone' => '11966665555',
        'kind' => 'cadastro',
        'channel' => 'link_only',
        'expires_in_days' => 7,
    ], $clinic->id, $user->id);

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->postJson(route('patient-invites.cancel', $invite));

    $response->assertOk();
    expect($invite->fresh()->status)->toBe('cancelado');
});

test('log-event marks a whatsapp-channel invite as enviado', function () {
    ['clinic' => $clinic, 'user' => $user] = setupPatientInviteHttpContext();

    $invite = app(PatientInviteService::class)->create([
        'nome' => 'Elaine',
        'sobrenome' => 'Rocha',
        'telefone' => '11955554444',
        'kind' => 'cadastro',
        'channel' => 'whatsapp',
        'expires_in_days' => 7,
    ], $clinic->id, $user->id);

    expect($invite->status)->toBe('gerado');

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->postJson(route('patient-invites.log-event', $invite), ['action' => 'whatsapp_link_generated']);

    $response->assertOk();
    expect($invite->fresh()->status)->toBe('enviado');
});
