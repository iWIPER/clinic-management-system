<?php

use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Fase B1 — regressão do carregamento sob demanda das abas secundárias de
 * PatientController::show(). Garante que:
 *  - a aba ativa no load inicial vem completa (sem round-trip extra);
 *  - abas não-ativas NÃO disparam suas queries no load inicial;
 *  - trocar de aba client-side (PatientHubTabs.vue não muda a URL/query
 *    string) ainda consegue buscar os dados via recarga parcial do Inertia
 *    — mesmo com a URL parada em ?tab= da aba original.
 */
function setupPatientShowLazyContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-lazy-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Lazy', 'slug' => 'clinica-lazy-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Lazy', 'status' => 'ativo']);

    $category = DocumentCategory::create(['clinic_id' => $clinic->id, 'name' => 'Termos', 'slug' => 'termos-lazy-' . uniqid(), 'is_system' => false, 'is_active' => true]);
    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id, 'name' => 'Termo', 'slug' => 'termo-lazy-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Termo', '<p>x</p>', 'Criação', $user->id);
    Document::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => 'Termo de teste único 12345',
        'professional_id' => $user->id, 'status' => 'completed', 'rendered_html' => '<p>x</p>',
        'document_code' => 'DOC-LAZY-' . uniqid(), 'created_by_id' => $user->id,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient');
}

test('the active tab on initial load includes its own data eagerly', function () {
    Storage::fake('s3');
    ['user' => $user, 'patient' => $patient] = setupPatientShowLazyContext();

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=documents')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('activeTab', 'documents')
            ->has('documentHub.documents', 1)
        );
});

test('non-active secondary tabs are excluded from the initial payload, but always-visible sidebar data is not', function () {
    Storage::fake('s3');
    ['user' => $user, 'patient' => $patient] = setupPatientShowLazyContext();

    $response = $this->actingAs($user)->get(route('patients.show', $patient)); // default tab: overview
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Show')
        ->where('activeTab', 'overview')
        ->has('hub')              // sidebar sempre visível — precisa estar presente
        ->has('anamnesisAlerts')  // idem — cabeçalho sempre visível
        ->missing('documentHub')  // aba não ativa — não deveria nem ter rodado a query
        ->missing('patientTreatments')
        ->missing('patientPayments')
        ->missing('patientNotes')
    );
});

test('switching tabs client-side (url unchanged) can still fetch the new tab data via a partial reload', function () {
    Storage::fake('s3');
    ['user' => $user, 'patient' => $patient] = setupPatientShowLazyContext();

    // Primeiro load real (HTML puro, sem X-Inertia — é assim que o
    // Inertia.js real recebe a versão inicial: embutida no atributo
    // data-page do HTML, não em header, já que nenhum header ainda existe
    // nessa primeira requisição do navegador).
    $first = $this->actingAs($user)->get(route('patients.show', $patient));
    $first->assertOk();
    preg_match('/data-page="([^"]+)"/', $first->getContent(), $matches);
    $pageData = json_decode(html_entity_decode($matches[1]), true);
    $version = $pageData['version'];
    expect($pageData['props'])->not->toHaveKey('documentHub');

    // PatientHubTabs.vue troca de aba só no client (não muda a URL) e dispara
    // router.reload({ only: ['documentHub'] }) — simulado aqui exatamente
    // como o Inertia.js faria, mesma URL de antes, agora já com a versão
    // que o client teria guardado da carga inicial.
    $partial = $this->actingAs($user)->get(route('patients.show', $patient), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'Patients/Show',
        'X-Inertia-Partial-Data' => 'documentHub',
    ]);

    $partial->assertOk();
    $partialData = json_decode($partial->getContent(), true);

    expect($partialData['props']['documentHub']['documents'])->toHaveCount(1)
        ->and($partialData['props']['documentHub']['documents'][0]['template_name'])->toBe('Termo de teste único 12345');
});
