<?php

use App\Models\AnamnesisTemplate;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;

function setupAnamnesisTemplateContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-anamtpl-' . uniqid(),
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
        'name' => 'Clínica Anamnese Templates',
        'slug' => 'clinica-anamtpl-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

// Regressão da fase C0.1 (RC-6): a página de criação (editor: null) não tinha
// nenhum formulário/botão alcançável — todo o construtor, incluindo o único
// botão "Salvar modelo", ficava atrás de `v-if="editor"` em Form.vue. O botão
// "Novo modelo" em Templates/Index.vue levava a um beco sem saída real.
test('create page renders with a null editor and the store endpoint creates a template', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAnamnesisTemplateContext();

    $this->actingAs($user)
        ->get(route('anamnesis-templates.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Anamneses/Templates/Form')
            ->where('editor', null)
        );

    expect(AnamnesisTemplate::where('clinic_id', $clinic->id)->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('anamnesis-templates.store'), [
            'name' => 'Modelo Novo',
            'description' => 'Descrição do modelo',
            'is_active' => true,
        ])
        ->assertRedirect();

    $template = AnamnesisTemplate::where('clinic_id', $clinic->id)->first();
    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Modelo Novo')
        ->and($template->is_system)->toBeFalse();
});

test('store redirects straight into the editor for the newly created template', function () {
    ['user' => $user] = setupAnamnesisTemplateContext();

    $response = $this->actingAs($user)->post(route('anamnesis-templates.store'), [
        'name' => 'Modelo Redirecionado',
    ]);

    $template = AnamnesisTemplate::where('name', 'Modelo Redirecionado')->firstOrFail();
    $response->assertRedirect(route('anamnesis-templates.edit', $template));
});

test('store requires a name', function () {
    ['user' => $user] = setupAnamnesisTemplateContext();

    $this->actingAs($user)
        ->post(route('anamnesis-templates.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

// Confirma que o fluxo de edição (que já funcionava) continua funcionando
// depois da mudança em Form.vue.
test('edit page renders the full editor payload for an existing template', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAnamnesisTemplateContext();

    $template = AnamnesisTemplate::create([
        'clinic_id' => $clinic->id,
        'name' => 'Modelo Existente',
        'slug' => 'modelo-existente-' . uniqid(),
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 1,
        'created_by_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('anamnesis-templates.edit', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Anamneses/Templates/Form')
            ->where('editor.template.id', $template->id)
            ->where('editor.template.name', 'Modelo Existente')
        );

    $this->actingAs($user)
        ->put(route('anamnesis-templates.update', $template), [
            'name' => 'Modelo Editado',
            'description' => 'Nova descrição',
            'is_active' => true,
        ])
        ->assertRedirect();

    $template->refresh();
    expect($template->name)->toBe('Modelo Editado')
        ->and($template->version)->toBe(2);
});
