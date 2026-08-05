<?php

use App\Data\LegacyAnamneseTxtParser;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\Patient;
use App\Models\User;
use App\Services\Anamnesis\AnamnesisService;

beforeEach(function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = setupPatientContext();
    $this->user = $user;
    $this->clinic = $clinic;
    $this->patient = $patient;
});

function setupPatientContext(): array
{
    $plan = \App\Models\Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-anamnesis-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = \App\Models\Clinic::create([
        'name' => 'Clínica Anamnese',
        'slug' => 'clinica-anamnese-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Gloria',
        'sobrenome' => 'Lelis',
        'status' => 'ativo',
    ]);

    return compact('user', 'clinic', 'patient');
}

it('parses legacy document without metadata as questions', function () {
    $path = database_path('seeders/data/anamnese.txt');
    expect(file_exists($path))->toBeTrue();

    $catalog = (new LegacyAnamneseTxtParser())->buildCatalog(file_get_contents($path));

    expect($catalog['templates'])->toHaveCount(7);
    expect(count($catalog['questions']))->toBeGreaterThan(50);

    $texts = array_column(array_values($catalog['questions']), 'text');
    expect($texts)->not->toContain('Sem alerta');
    expect($texts)->not->toContain('- Alerta: Sem alerta');
    expect(collect($texts)->contains(fn ($t) => str_starts_with($t, 'Pergunta ')))->toBeFalse();
    expect(collect($texts)->contains(fn ($t) => str_starts_with($t, '- Alerta')))->toBeFalse();
    expect(collect($texts)->contains(fn ($t) => str_starts_with($t, 'Com alerta')))->toBeFalse();

    $pressure = collect($catalog['questions'])->first(fn ($q) => str_contains($q['text'], 'pressão alta'));
    expect($pressure)->not->toBeNull();
    expect($pressure['has_alert'])->toBeTrue();
    expect($pressure['alert_text'])->toContain('Hipertenso');
    expect($pressure['type'])->toBe('yes_no_unknown_text');
});

it('seeds system templates with shared question bank', function () {
    $this->seed(\Database\Seeders\AnamnesisTemplatesSeeder::class);

    expect(AnamnesisTemplate::whereNull('clinic_id')->count())->toBe(7);
    expect(AnamnesisQuestion::count())->toBeGreaterThan(50);

    $shared = AnamnesisQuestion::where('text', 'like', '%diabetes%')->first();
    expect($shared->templates()->count())->toBeGreaterThan(1);
});

it('loads patient show with anamnesis hub data', function () {
    $this->seed(\Database\Seeders\AnamnesisTemplatesSeeder::class);

    $response = $this->actingAs($this->user)
        ->get(route('patients.show', $this->patient));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Show')
        ->has('anamnesisHub.templates', 7)
        ->has('anamnesisHub.instances')
        ->has('patientNotes')
    );
});

it('does not expose metadata questions in editor payload', function () {
    $this->seed(\Database\Seeders\AnamnesisTemplatesSeeder::class);

    $bad = AnamnesisQuestion::where('is_active', true)->get()->filter(fn ($q) => ! $q->isRenderable());
    expect($bad)->toBeEmpty();

    $template = AnamnesisTemplate::where('slug', 'anamnese-adulta')->first();
    $patient = $this->patient;
    $service = app(AnamnesisService::class);
    $instance = $service->createInstance($patient, $template->id, $this->user->id);
    $data = $service->loadEditorData($instance);

    $texts = collect($data['categories'])->flatMap(fn ($c) => collect($c['questions'])->pluck('text'));
    expect($texts->contains(fn ($t) => str_contains($t, 'Alerta:')))->toBeFalse();
    expect($texts->contains(fn ($t) => str_starts_with($t, 'Pergunta ')))->toBeFalse();

    $first = collect($data['categories'])->flatMap->questions->first();
    expect($first)->toHaveKeys(['id', 'text', 'type', 'value']);
    expect($first)->not->toHaveKey('alert_text');
    expect($first)->not->toHaveKey('type_label');
});

it('loads anamnesis edit page without error', function () {
    $this->seed(\Database\Seeders\AnamnesisTemplatesSeeder::class);

    $template = AnamnesisTemplate::where('slug', 'anamnese-adulta')->first();
    $service = app(AnamnesisService::class);
    $instance = $service->createInstance($this->patient, $template->id, $this->user->id);

    $response = $this->actingAs($this->user)
        ->get(route('patients.anamneses.edit', [$this->patient, $instance]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Anamneses/Edit')
        ->has('patient.id')
        ->has('editor.instance.id')
        ->has('editor.categories')
        ->where('editor.instance.template_name', $template->name)
    );

});

it('creates anamnesis instance and triggers alert on positive answer', function () {
    $this->seed(\Database\Seeders\AnamnesisTemplatesSeeder::class);

    $template = AnamnesisTemplate::where('slug', 'anamnese-adulta-resumida')->first();
    $question = $template->questions()->where('text', 'like', '%pressão alta%')->first();

    $service = app(AnamnesisService::class);
    $instance = $service->createInstance($this->patient, $template->id, $this->user->id);

    $service->saveAnswers($instance, [[
        'question_id' => $question->id,
        'value' => 'sim',
        'supplementary_text' => null,
    ]], $this->user->id);

    $alerts = $service->patientCardAlerts($this->patient);

    expect($alerts)->not->toBeEmpty();
    expect(collect($alerts)->first()['label'])->toContain('Hipertenso');
});