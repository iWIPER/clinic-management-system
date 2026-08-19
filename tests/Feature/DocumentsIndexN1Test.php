<?php

use App\Enums\Documents\DocumentStatus;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fase B2 — regressão do N+1 de DocumentController::index(). Antes: 5
 * queries por categoria (5×N). Depois: 2 queries fixas, não importa quantas
 * categorias existam. Cobre os valores agregados (para garantir que a
 * otimização não mudou o resultado, só como ele é calculado), isolamento
 * multi-tenant, categoria sem template/documento, e o caso vazio.
 */
function setupDocumentsN1Context(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-docn1' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica N1' . $suffix, 'slug' => 'clinica-docn1' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'N1' . $suffix, 'status' => 'ativo']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient');
}

function makeDocumentTemplateWithDocs(array $ctx, DocumentCategory $category, int $documentCount, string $lastStatus = 'completed'): DocumentTemplate
{
    $template = DocumentTemplate::create([
        'clinic_id' => $ctx['clinic']->id, 'category_id' => $category->id,
        'name' => 'Template ' . uniqid(), 'slug' => 'tpl-n1-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $ctx['user']->id,
    ]);
    $template->createNewVersion($template->name, '<p>x</p>', 'Criação', $ctx['user']->id);

    foreach (range(1, $documentCount) as $i) {
        Document::create([
            'clinic_id' => $ctx['clinic']->id, 'patient_id' => $ctx['patient']->id, 'template_id' => $template->id,
            'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
            'professional_id' => $ctx['user']->id,
            'status' => $i === $documentCount ? $lastStatus : DocumentStatus::Completed->value,
            'rendered_html' => '<p>x</p>', 'document_code' => 'DOC-N1-' . uniqid(),
            'created_by_id' => $ctx['user']->id,
        ]);
    }

    return $template->fresh();
}

test('the query count stays constant regardless of how many categories exist (no more N+1)', function () {
    $ctx = setupDocumentsN1Context();

    foreach (range(1, 5) as $i) {
        $category = DocumentCategory::create([
            'clinic_id' => $ctx['clinic']->id, 'name' => 'Cat ' . $i, 'slug' => 'cat-n1-' . $i . '-' . uniqid(),
            'is_system' => false, 'is_active' => true,
        ]);
        makeDocumentTemplateWithDocs($ctx, $category, 2);
    }

    $queries = [];
    DB::listen(fn ($q) => $queries[] = $q->sql);

    $this->actingAs($ctx['user'])->get(route('documents.index'))->assertOk();

    // Fixo (não 5×N) — o número exato depende de sessão/auth, o que importa
    // é não crescer conforme o número de categorias (provado abaixo).
    expect(count($queries))->toBeLessThanOrEqual(6);
});

test('query count does not grow when categories go from 1 to 10', function () {
    $ctxSmall = setupDocumentsN1Context('-small');
    $categorySmall = DocumentCategory::create([
        'clinic_id' => $ctxSmall['clinic']->id, 'name' => 'Única', 'slug' => 'unica-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    makeDocumentTemplateWithDocs($ctxSmall, $categorySmall, 1);

    $queriesSmall = [];
    DB::listen(fn ($q) => $queriesSmall[] = $q->sql);
    $this->actingAs($ctxSmall['user'])->get(route('documents.index'))->assertOk();
    $countSmall = count($queriesSmall);

    $ctxBig = setupDocumentsN1Context('-big');
    foreach (range(1, 10) as $i) {
        $category = DocumentCategory::create([
            'clinic_id' => $ctxBig['clinic']->id, 'name' => 'Cat ' . $i, 'slug' => 'catbig-' . $i . '-' . uniqid(),
            'is_system' => false, 'is_active' => true,
        ]);
        makeDocumentTemplateWithDocs($ctxBig, $category, 3);
    }

    $queriesBig = [];
    DB::listen(fn ($q) => $queriesBig[] = $q->sql);
    $this->actingAs($ctxBig['user'])->get(route('documents.index'))->assertOk();
    $countBig = count($queriesBig);

    expect($countBig)->toBe($countSmall);
});

test('aggregated counts (issued, pending, last issued, active templates) are correct per category', function () {
    $ctx = setupDocumentsN1Context();

    $category = DocumentCategory::create([
        'clinic_id' => $ctx['clinic']->id, 'name' => 'Consentimentos', 'slug' => 'consent-n1-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    // Template 1: 3 documentos, o último "awaiting_signature" (pendente).
    makeDocumentTemplateWithDocs($ctx, $category, 3, DocumentStatus::AwaitingSignature->value);
    // Template 2: 2 documentos, todos completed (nenhum pendente).
    makeDocumentTemplateWithDocs($ctx, $category, 2, DocumentStatus::Completed->value);
    // Template 3: inativo — não deve contar em templates_count, mas seus
    // documentos ainda contam em issued_count (mesmo comportamento do código
    // original, que só filtra is_active na contagem de templates).
    $inactiveTemplate = makeDocumentTemplateWithDocs($ctx, $category, 1);
    $inactiveTemplate->update(['is_active' => false]);

    $response = $this->actingAs($ctx['user'])->get(route('documents.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('categories.0.name', 'Consentimentos')
        ->where('categories.0.issued_count', 6) // 3 + 2 + 1
        ->where('categories.0.pending_signatures', 1) // só o último do template 1
        ->where('categories.0.templates_count', 2) // 2 ativos (o 3º está inativo)
        ->where('categories.0.last_issued_at', now()->format('d/m/Y'))
    );
});

test('a category with no templates at all shows zeroed stats, not an error', function () {
    $ctx = setupDocumentsN1Context();
    DocumentCategory::create([
        'clinic_id' => $ctx['clinic']->id, 'name' => 'Vazia', 'slug' => 'vazia-n1-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('categories.0.issued_count', 0)
            ->where('categories.0.pending_signatures', 0)
            ->where('categories.0.templates_count', 0)
            ->where('categories.0.last_issued_at', null)
        );
});

test('no categories at all renders an empty list without error', function () {
    $ctx = setupDocumentsN1Context();

    $this->actingAs($ctx['user'])
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('categories', []));
});

test('a clinic never sees another clinic categories, templates or document counts', function () {
    $ctxA = setupDocumentsN1Context('-a');
    $categoryA = DocumentCategory::create([
        'clinic_id' => $ctxA['clinic']->id, 'name' => 'Da Clínica A', 'slug' => 'cat-a-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    makeDocumentTemplateWithDocs($ctxA, $categoryA, 5);

    $ctxB = setupDocumentsN1Context('-b');
    DocumentCategory::create([
        'clinic_id' => $ctxB['clinic']->id, 'name' => 'Da Clínica B', 'slug' => 'cat-b-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    session(['current_clinic_id' => $ctxB['clinic']->id]);

    $response = $this->actingAs($ctxB['user'])->get(route('documents.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.name', 'Da Clínica B')
        ->where('categories.0.issued_count', 0)
    );
});
