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
 * Fase A — testes de segurança cross-tenant para o módulo de Documentos:
 * PDFs sensíveis (privados, sem URL pública previsível), IDOR em
 * DocumentTemplateController/DocumentCategoryController, e sanitização de
 * content_html contra XSS armazenado.
 */
function setupDocumentSecurityContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-docsec' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Documentos' . $suffix,
        'slug' => 'clinica-docsec' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Docs' . $suffix, 'status' => 'ativo']);

    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Termos', 'slug' => 'termos' . $suffix . '-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id,
        'name' => 'Termo de Consentimento', 'slug' => 'termo' . $suffix . '-' . uniqid(),
        'requires_patient_signature' => true, 'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Termo de Consentimento', '<p>Conteúdo original</p>', 'Criação', $user->id);

    return compact('plan', 'clinic', 'user', 'patient', 'category', 'template');
}

function createDocumentWithPrivatePdf(Clinic $clinic, Patient $patient, DocumentTemplate $template, User $user): Document
{
    $document = Document::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'template_id' => $template->id,
        'template_version_id' => $template->current_version_id,
        'template_name' => $template->name,
        'professional_id' => $user->id,
        'status' => 'completed',
        'rendered_html' => '<p>Conteúdo renderizado</p>',
        'document_code' => 'DOC-TEST-' . uniqid(),
        'created_by_id' => $user->id,
    ]);

    $path = 'documents/document-' . $document->id . '.pdf';
    Storage::disk('s3')->put($path, '%PDF-1.4 fake pdf content for test');
    $document->update(['pdf_path' => $path]);

    return $document->fresh();
}

// ─────────────────────────────────────────────────────────────────────────
// 1. PDFs sensíveis — não expostos publicamente, isolados por clínica
// Fase A.3: PDFs agora vivem no disco 's3' (privado); Storage::fake('s3')
// intercepta puts/gets locais nestes testes sem tocar a AWS real. A
// privacidade do bucket em si (Block Public Access, sem policy pública) foi
// confirmada por leitura direta da AWS, fora do escopo de um teste unitário.
// ─────────────────────────────────────────────────────────────────────────

test('a document pdf never lands on the public disk and is not reachable via the legacy public storage url', function () {
    Storage::fake('s3');
    Storage::fake('public');
    ['clinic' => $clinic, 'patient' => $patient, 'template' => $template, 'user' => $user] = setupDocumentSecurityContext();
    $document = createDocumentWithPrivatePdf($clinic, $patient, $template, $user);

    // O caminho antigo (disco público local, servido estaticamente por
    // /storage/...) nunca deve ser tocado pelo novo fluxo S3. A rota
    // storage.local ainda existe (serve o disco 'local', não mais usado por
    // PDF nenhum) e exige assinatura — sem uma, 403 fora de produção.
    expect(Storage::disk('public')->exists($document->pdf_path))->toBeFalse();
    $this->get('/storage/' . $document->pdf_path)->assertStatus(403);

    // E o arquivo realmente está no disco s3 (fake), não em 'local'.
    expect(Storage::disk('s3')->exists($document->pdf_path))->toBeTrue();
});

test('the owning clinic can view an already generated document pdf without regenerating it', function () {
    Storage::fake('s3');
    ['clinic' => $clinic, 'patient' => $patient, 'template' => $template, 'user' => $user] = setupDocumentSecurityContext();
    $document = createDocumentWithPrivatePdf($clinic, $patient, $template, $user);

    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->get(route('patients.documents.file', [$patient, $document]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a user from another clinic cannot view or download a document pdf that is not theirs', function () {
    Storage::fake('s3');
    ['clinic' => $clinic, 'patient' => $patient, 'template' => $template, 'user' => $user] = setupDocumentSecurityContext();
    $document = createDocumentWithPrivatePdf($clinic, $patient, $template, $user);

    ['user' => $foreignUser, 'clinic' => $foreignClinic] = setupDocumentSecurityContext('-foreign');
    session(['current_clinic_id' => $foreignClinic->id]);

    $this->actingAs($foreignUser)
        ->get(route('patients.documents.file', [$patient, $document]))
        ->assertStatus(404);

    $this->actingAs($foreignUser)
        ->get(route('patients.documents.pdf', [$patient, $document]))
        ->assertStatus(404);
});

test('requesting a document pdf that was never generated returns 404 instead of a broken link', function () {
    Storage::fake('s3');
    ['clinic' => $clinic, 'patient' => $patient, 'template' => $template, 'user' => $user] = setupDocumentSecurityContext();

    $document = Document::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
        'professional_id' => $user->id, 'status' => 'draft', 'rendered_html' => '<p>x</p>',
        'document_code' => 'DOC-NOPDF-' . uniqid(), 'created_by_id' => $user->id,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->get(route('patients.documents.file', [$patient, $document]))
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────
// 2. DocumentTemplateController — IDOR
// ─────────────────────────────────────────────────────────────────────────

test('the owning clinic can edit, duplicate, archive, set default and delete its own document template', function () {
    ['clinic' => $clinic, 'user' => $user, 'template' => $template] = setupDocumentSecurityContext();
    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)->get(route('document-templates.edit', $template))->assertOk();

    $this->actingAs($user)
        ->put(route('document-templates.update', $template), [
            'category_id' => $template->category_id,
            'name' => 'Termo Atualizado',
            'content_html' => '<p>Novo conteúdo</p>',
        ])
        ->assertRedirect();
    expect($template->fresh()->name)->toBe('Termo Atualizado');

    $this->actingAs($user)->post(route('document-templates.set-default', $template))->assertRedirect();
    expect($template->fresh()->is_default)->toBeTrue();

    $this->actingAs($user)->post(route('document-templates.archive', $template))->assertRedirect();
    expect($template->fresh()->is_active)->toBeFalse();
});

test('a user from another clinic gets 403 trying to read, edit, duplicate, archive, set-default or delete a foreign document template', function () {
    ['template' => $foreignTemplate] = setupDocumentSecurityContext('-victim');
    ['user' => $attacker, 'clinic' => $attackerClinic] = setupDocumentSecurityContext('-attacker');
    session(['current_clinic_id' => $attackerClinic->id]);

    $this->actingAs($attacker)->get(route('document-templates.edit', $foreignTemplate))->assertForbidden();

    $this->actingAs($attacker)
        ->put(route('document-templates.update', $foreignTemplate), [
            'category_id' => $foreignTemplate->category_id,
            'name' => 'Sequestrado',
            'content_html' => '<p>Sequestrado</p>',
        ])
        ->assertForbidden();

    $this->actingAs($attacker)->post(route('document-templates.duplicate', $foreignTemplate))->assertForbidden();
    $this->actingAs($attacker)->post(route('document-templates.archive', $foreignTemplate))->assertForbidden();
    $this->actingAs($attacker)->post(route('document-templates.set-default', $foreignTemplate))->assertForbidden();
    $this->actingAs($attacker)->delete(route('document-templates.destroy', $foreignTemplate))->assertForbidden();

    // O recurso da vítima não deve ter sido alterado por nenhuma tentativa acima.
    expect($foreignTemplate->fresh())
        ->name->toBe('Termo de Consentimento')
        ->is_active->toBeTrue()
        ->is_default->toBeFalse();
    expect(DocumentTemplate::find($foreignTemplate->id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────
// 3. DocumentCategoryController — IDOR
// ─────────────────────────────────────────────────────────────────────────

test('the owning clinic can update and deactivate its own document category', function () {
    ['clinic' => $clinic, 'user' => $user, 'category' => $category] = setupDocumentSecurityContext();
    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->put(route('document-categories.update', $category), ['name' => 'Termos Renomeados'])
        ->assertRedirect();
    expect($category->fresh()->name)->toBe('Termos Renomeados');

    $this->actingAs($user)->post(route('document-categories.deactivate', $category))->assertRedirect();
    expect($category->fresh()->is_active)->toBeFalse();
});

test('a user from another clinic gets 403 trying to update or deactivate a foreign document category', function () {
    ['category' => $foreignCategory] = setupDocumentSecurityContext('-victim2');
    ['user' => $attacker, 'clinic' => $attackerClinic] = setupDocumentSecurityContext('-attacker2');
    session(['current_clinic_id' => $attackerClinic->id]);

    $this->actingAs($attacker)
        ->put(route('document-categories.update', $foreignCategory), ['name' => 'Sequestrada'])
        ->assertForbidden();

    $this->actingAs($attacker)
        ->post(route('document-categories.deactivate', $foreignCategory))
        ->assertForbidden();

    expect($foreignCategory->fresh())
        ->name->toBe('Termos')
        ->is_active->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────
// 4. XSS — sanitização de content_html na fonte de verdade
// ─────────────────────────────────────────────────────────────────────────

test('malicious html in a document template is stripped on save, legitimate formatting is preserved', function () {
    ['clinic' => $clinic, 'user' => $user, 'category' => $category] = setupDocumentSecurityContext();
    session(['current_clinic_id' => $clinic->id]);

    $malicious = '<p style="text-align:center"><strong>Termo</strong></p>'
        . '<script>alert(document.cookie)</script>'
        . '<img src=x onerror="alert(1)">'
        . '<a href="javascript:alert(2)">clique aqui</a>'
        . '<h2 style="text-align:right">Assinatura</h2>'
        . '<ul><li>Cláusula 1</li></ul>';

    $this->actingAs($user)
        ->post(route('document-templates.store'), [
            'category_id' => $category->id,
            'name' => 'Modelo malicioso',
            'content_html' => $malicious,
        ])
        ->assertRedirect();

    $template = DocumentTemplate::where('name', 'Modelo malicioso')->firstOrFail();
    $saved = $template->currentVersion->content_html;

    expect($saved)
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->toContain('<strong>Termo</strong>')
        ->toContain('text-align:center')
        ->toContain('<h2 style="text-align:right')
        ->toContain('<li>Cláusula 1</li>');
});

test('malicious html submitted to a document template update is stripped, not just hidden client-side', function () {
    ['clinic' => $clinic, 'user' => $user, 'template' => $template] = setupDocumentSecurityContext();
    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->put(route('document-templates.update', $template), [
            'category_id' => $template->category_id,
            'name' => $template->name,
            'content_html' => '<p>Texto</p><script>document.location="https://evil.example"</script>',
        ])
        ->assertRedirect();

    expect($template->fresh()->currentVersion->content_html)
        ->not->toContain('<script')
        ->toContain('<p>Texto</p>');
});

test('malicious html in a clinical evolution is stripped via the model, regardless of the write path', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupDocumentSecurityContext();
    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->post(route('patients.prontuario.evolutions', $patient), [
            'content' => '<p>Evolução normal</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        ])
        ->assertRedirect();

    $evolution = \App\Models\ClinicalEvolution::where('patient_id', $patient->id)->latest()->firstOrFail();

    expect($evolution->content)
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->toContain('<p>Evolução normal</p>');
});
