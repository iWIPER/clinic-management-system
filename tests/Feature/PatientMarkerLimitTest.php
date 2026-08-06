<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\Plan;
use App\Models\User;
use App\Services\PatientMarkerService;

function setupMarkerLimitContext(int $markerCount = 8): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-marker-' . uniqid(),
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
        'slug' => 'clinica-marker-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Teste',
        'status' => 'ativo',
    ]);

    $markers = collect(range(1, $markerCount))->map(fn (int $i) => PatientTag::create([
        'clinic_id' => $clinic->id,
        'name' => "Marcador {$i}",
        'slug' => "marcador-{$i}-" . uniqid(),
        'color' => PatientMarkerService::PALETTE[0],
        'is_patient_marker' => true,
    ]));

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'clinic', 'patient', 'markers');
}

test('patient can be assigned exactly the maximum number of markers', function () {
    ['owner' => $owner, 'patient' => $patient, 'markers' => $markers] = setupMarkerLimitContext();

    $ids = $markers->take(PatientMarkerService::MAX_MARKERS_PER_PATIENT)->pluck('id')->all();

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $patient->clinic_id])
        ->put(route('patients.markers.sync', $patient), ['marker_ids' => $ids]);

    $response->assertRedirect();
    expect($patient->markers()->count())->toBe(PatientMarkerService::MAX_MARKERS_PER_PATIENT);
});

test('assigning more than the maximum number of markers is rejected', function () {
    ['owner' => $owner, 'patient' => $patient, 'markers' => $markers] = setupMarkerLimitContext();

    $ids = $markers->pluck('id')->all(); // 8 markers, limit is 6

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $patient->clinic_id])
        ->put(route('patients.markers.sync', $patient), ['marker_ids' => $ids]);

    $response->assertSessionHasErrors('marker_ids');
    expect($patient->markers()->count())->toBe(0);
});

test('removing a marker while at the limit keeps the patient below the limit', function () {
    ['owner' => $owner, 'patient' => $patient, 'markers' => $markers] = setupMarkerLimitContext();

    $atLimit = $markers->take(PatientMarkerService::MAX_MARKERS_PER_PATIENT)->pluck('id')->all();
    $patient->markers()->sync($atLimit);

    $afterRemoval = $markers->take(PatientMarkerService::MAX_MARKERS_PER_PATIENT - 1)->pluck('id')->all();

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $patient->clinic_id])
        ->put(route('patients.markers.sync', $patient), ['marker_ids' => $afterRemoval]);

    $response->assertRedirect();
    expect($patient->markers()->count())->toBe(PatientMarkerService::MAX_MARKERS_PER_PATIENT - 1);
});

test('patient show page exposes the marker limit to the frontend', function () {
    ['owner' => $owner, 'patient' => $patient] = setupMarkerLimitContext();

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $patient->clinic_id])
        ->get(route('patients.show', $patient));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Show')
        ->where('markerLimit', PatientMarkerService::MAX_MARKERS_PER_PATIENT)
    );
});
