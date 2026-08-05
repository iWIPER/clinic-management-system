<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;

function setupResponsibleProfessionalContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-resp-' . uniqid(),
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
        'slug' => 'clinica-resp-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    // Dono da clínica — cadastro direto, sem job_title definido (caso real e comum).
    $owner = User::factory()->create(['email_verified_at' => now(), 'job_title' => null]);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    // Dentista convidado — job_title definido via convite.
    $dentist = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($dentist->id, ['role' => 'professional']);

    // Secretário(a) — não deve aparecer como elegível a responsável clínico.
    $receptionist = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Secretário(a)']);
    $clinic->users()->attach($receptionist->id, ['role' => 'staff']);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Teste',
        'status' => 'ativo',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'dentist', 'receptionist', 'clinic', 'patient');
}

test('eligible professionals list only includes users whose job_title is Dentista', function () {
    // Política atual: elegibilidade a responsável clínico depende exclusivamente
    // do cargo (job_title === 'Dentista'), não do role na clínica. O owner deste
    // teste não tem job_title definido e por isso não deve aparecer na lista,
    // mesmo sendo owner.
    ['owner' => $owner, 'dentist' => $dentist, 'receptionist' => $receptionist, 'patient' => $patient] = setupResponsibleProfessionalContext();

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $patient->clinic_id])
        ->get(route('patients.show', $patient));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Show')
        ->where('eligibleProfessionals', fn ($list) => ! collect($list)->pluck('id')->contains($owner->id)
            && collect($list)->pluck('id')->contains($dentist->id)
            && ! collect($list)->pluck('id')->contains($receptionist->id)
        )
    );
});

test('patient creation auto-assigns the responsible professional when the creator is a Dentista', function () {
    ['dentist' => $dentist, 'clinic' => $clinic] = setupResponsibleProfessionalContext();

    $response = $this->actingAs($dentist)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->post(route('patients.store'), [
            'nome' => 'Novo',
            'sobrenome' => 'Paciente',
        ]);

    $response->assertRedirect();

    $patient = \App\Models\Patient::where('nome', 'Novo')->where('sobrenome', 'Paciente')->firstOrFail();
    expect($patient->responsible_professional_id)->toBe($dentist->id);
});

test('patient creation leaves the responsible professional empty when the creator is not a Dentista', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupResponsibleProfessionalContext();

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->post(route('patients.store'), [
            'nome' => 'Outro',
            'sobrenome' => 'Paciente',
        ]);

    $response->assertRedirect();

    $patient = \App\Models\Patient::where('nome', 'Outro')->where('sobrenome', 'Paciente')->firstOrFail();
    expect($patient->responsible_professional_id)->toBeNull();
});
