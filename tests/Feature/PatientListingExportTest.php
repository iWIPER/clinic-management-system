<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\Plan;
use App\Models\User;
use App\Http\Controllers\PatientController;
use Maatwebsite\Excel\Facades\Excel;

function setupPatientListingContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-listing-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 1000,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Teste',
        'slug' => 'clinica-listing-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'clinic');
}

test('patient listing defaults to 10 per page', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    foreach (range(1, 12) as $i) {
        Patient::create(['clinic_id' => $clinic->id, 'nome' => "Paciente{$i}", 'sobrenome' => 'Teste', 'status' => 'ativo']);
    }

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->where('patients.pagination.per_page', 10)
        ->where('patients.pagination.total', 12)
        ->where('patients.pagination.last_page', 2)
        ->has('patients.data', 10)
        ->where('perPageOptions', PatientController::PER_PAGE_OPTIONS)
    );
});

test('per_page can be changed to any allowed option and updates pagination correctly', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    foreach (range(1, 30) as $i) {
        Patient::create(['clinic_id' => $clinic->id, 'nome' => "Paciente{$i}", 'sobrenome' => 'Teste', 'status' => 'ativo']);
    }

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.index', ['per_page' => 25]));

    $response->assertInertia(fn ($page) => $page
        ->where('patients.pagination.per_page', 25)
        ->where('patients.pagination.total', 30)
        ->where('patients.pagination.last_page', 2)
        ->has('patients.data', 25)
    );
});

test('an invalid per_page value falls back to the default of 10', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Unico', 'status' => 'ativo']);

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.index', ['per_page' => 999]));

    $response->assertInertia(fn ($page) => $page->where('patients.pagination.per_page', 10));
});

test('search filter still works together with per_page', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Joana', 'sobrenome' => 'Silva', 'status' => 'ativo']);
    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Marcos', 'sobrenome' => 'Souza', 'status' => 'ativo']);

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.index', ['search' => 'Joana', 'per_page' => 25]));

    $response->assertInertia(fn ($page) => $page
        ->where('patients.pagination.total', 1)
        ->where('patients.pagination.per_page', 25)
        ->where('patients.data.0.nome', 'Joana')
    );
});

test('csv export includes patients without a document as "Sem documento" and formats categories', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    $withDoc = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Com', 'sobrenome' => 'Documento',
        'status' => 'ativo', 'cpf' => '051.458.257-29',
    ]);
    $withoutDoc = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Sem', 'sobrenome' => 'Documento', 'status' => 'ativo',
    ]);

    $tag1 = PatientTag::create(['clinic_id' => $clinic->id, 'name' => 'Ortodontia', 'slug' => 'ortodontia-' . uniqid(), 'color' => '#ef4444', 'is_patient_marker' => true]);
    $tag2 = PatientTag::create(['clinic_id' => $clinic->id, 'name' => 'Implante', 'slug' => 'implante-' . uniqid(), 'color' => '#22c55e', 'is_patient_marker' => true]);
    $withDoc->markers()->sync([$tag1->id, $tag2->id]);

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.export', ['format' => 'csv']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('CPF');
    expect($content)->toContain('051.458.257-29');
    expect($content)->toContain('Sem documento');
    expect($content)->toContain('Ortodontia, Implante');
    expect($content)->toContain('Sem categorias');
});

test('csv export respects the search filter, matching what the listing would show', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Ana', 'sobrenome' => 'Filtrada', 'status' => 'ativo']);
    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Bruno', 'sobrenome' => 'ForaDoFiltro', 'status' => 'ativo']);

    $response = $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.export', ['format' => 'csv', 'search' => 'Ana']));

    $content = $response->streamedContent();

    expect($content)->toContain('Ana Filtrada');
    expect($content)->not->toContain('Bruno ForaDoFiltro');
});

test('excel export downloads a file with the expected headings', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Excel', 'status' => 'ativo']);

    Excel::fake();

    $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.export', ['format' => 'excel']))
        ->assertOk();

    Excel::assertDownloaded('pacientes-' . now()->format('Y-m-d') . '.xlsx', function ($export) {
        return $export->headings()[0] === 'ID' && in_array('Categorias', $export->headings(), true);
    });
});

test('an invalid export format is rejected', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupPatientListingContext();

    $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('patients.export', ['format' => 'pdf']))
        ->assertStatus(422);
});
