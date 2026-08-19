<?php

use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;

// Fase C0.1, item 3 — sanity check de patients.documents.destroy e
// profile.destroy: a auditoria C0 encontrou os dois como rotas destrutivas
// vivas sem nenhum gatilho de UI. Aqui provamos empiricamente (não só por
// leitura de código) que ambas já são protegidas corretamente, sem alterar
// nenhum dos dois controllers.

function setupDestructiveEndpointContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-destr' . $suffix . '-' . uniqid(),
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
        'name' => 'Clínica Destrutivos' . $suffix,
        'slug' => 'clinica-destr' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Teste',
        'status' => 'ativo',
    ]);

    return compact('user', 'clinic', 'patient');
}

function makeDocument(Clinic $clinic, Patient $patient, User $user, string $status = 'draft'): Document
{
    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id,
        'name' => 'Categoria de Teste',
        'slug' => 'categoria-teste-' . uniqid(),
        'is_system' => false,
        'is_active' => true,
    ]);

    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id,
        'category_id' => $category->id,
        'name' => 'Modelo de Teste',
        'slug' => 'modelo-teste-' . uniqid(),
        'is_system' => false,
        'is_active' => true,
    ]);
    $template->createNewVersion('Modelo de Teste', '<p>Conteúdo</p>', 'Criação', $user->id);

    return Document::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'template_id' => $template->id,
        'template_version_id' => $template->currentVersion->id,
        'template_name' => 'Modelo de Teste',
        'status' => $status,
        'rendered_html' => '<p>conteúdo</p>',
        'document_code' => 'DOC-' . uniqid(),
    ]);
}

describe('patients.documents.destroy', function () {
    test('unauthenticated request is redirected to login, not executed', function () {
        ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = setupDestructiveEndpointContext();
        $document = makeDocument($clinic, $patient, $user);

        $this->delete(route('patients.documents.destroy', [$patient, $document]))
            ->assertRedirect(route('login'));

        expect(Document::find($document->id))->not->toBeNull();
    });

    test('owner of the clinic can delete a draft document', function () {
        ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = setupDestructiveEndpointContext();
        $document = makeDocument($clinic, $patient, $user, 'draft');

        $this->actingAs($user)
            ->delete(route('patients.documents.destroy', [$patient, $document]))
            ->assertRedirect();

        expect(Document::find($document->id))->toBeNull();
    });

    test('a non-draft (issued) document cannot be deleted, even by the owning clinic', function () {
        ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = setupDestructiveEndpointContext();
        $document = makeDocument($clinic, $patient, $user, 'issued');

        $this->actingAs($user)
            ->delete(route('patients.documents.destroy', [$patient, $document]))
            ->assertStatus(422);

        expect(Document::find($document->id))->not->toBeNull();
    });

    test('a user from another clinic gets 404, not the document, and nothing is deleted', function () {
        ['user' => $userA, 'clinic' => $clinicA, 'patient' => $patientA] = setupDestructiveEndpointContext('-a');
        $document = makeDocument($clinicA, $patientA, $userA, 'draft');

        ['user' => $userB] = setupDestructiveEndpointContext('-b');

        $this->actingAs($userB)
            ->delete(route('patients.documents.destroy', [$patientA, $document]))
            ->assertStatus(404);

        expect(Document::find($document->id))->not->toBeNull();
    });

    test('passing a document that belongs to a different patient than the route patient is rejected', function () {
        ['user' => $owner, 'clinic' => $clinic, 'patient' => $patientA] = setupDestructiveEndpointContext();

        $patientB = Patient::create([
            'clinic_id' => $clinic->id,
            'nome' => 'Outro',
            'sobrenome' => 'Paciente',
            'status' => 'ativo',
        ]);
        $documentOfB = makeDocument($clinic, $patientB, $owner, 'draft');

        $this->actingAs($owner)
            ->delete(route('patients.documents.destroy', [$patientA, $documentOfB]))
            ->assertStatus(404);

        expect(Document::find($documentOfB->id))->not->toBeNull();
    });
});

describe('profile.destroy', function () {
    test('unauthenticated request is redirected to login, not executed', function () {
        $user = User::factory()->create();

        $this->delete(route('profile.destroy'))
            ->assertRedirect(route('login'));

        expect(User::find($user->id))->not->toBeNull();
    });

    test('requires current password confirmation before deleting the account', function () {
        ['user' => $user, 'clinic' => $clinic] = setupDestructiveEndpointContext('-pwd');
        $user->forceFill(['password' => bcrypt('correct-password')])->save();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        expect(User::find($user->id))->not->toBeNull();
    });

    test('an authenticated user can delete only their own account with the correct password', function () {
        ['user' => $user, 'clinic' => $clinic] = setupDestructiveEndpointContext('-self');
        $user->forceFill(['password' => bcrypt('correct-password')])->save();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->delete(route('profile.destroy'), ['password' => 'correct-password'])
            ->assertRedirect('/');

        expect(User::find($user->id))->toBeNull()
            ->and(User::find($otherUser->id))->not->toBeNull();
    });
});
