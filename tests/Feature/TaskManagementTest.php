<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\TaskList;
use App\Models\User;

function setupTaskContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-tasks-' . uniqid(),
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
        'name' => 'Clínica Tarefas',
        'slug' => 'clinica-tasks-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $colleague = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Recepção', 'status' => 'ativo']);
    $clinic->users()->attach($colleague->id, ['role' => 'staff']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'colleague');
}

test('tasks panel endpoint exposes tasks scoped to the current user by default', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague] = setupTaskContext();

    Task::create(['clinic_id' => $clinic->id, 'title' => 'Minha tarefa', 'status' => 'todo', 'priority' => 'alta', 'created_by' => $user->id, 'assigned_to' => $user->id]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Tarefa da colega', 'status' => 'todo', 'priority' => 'media', 'created_by' => $colleague->id, 'assigned_to' => $colleague->id]);

    $this->actingAs($user)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Minha tarefa')
        ->assertJsonStructure(['tasks', 'statuses', 'priorities', 'availableLabels', 'teamMembers', 'currentUserId']);
});

test('unassigned task created by me counts as mine, everything else counts as team', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague] = setupTaskContext();

    Task::create(['clinic_id' => $clinic->id, 'title' => 'Sem responsável, criei eu', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Sem responsável, colega criou', 'status' => 'todo', 'priority' => 'media', 'created_by' => $colleague->id]);

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine']))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Sem responsável, criei eu');

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'team']))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Sem responsável, colega criou');
});

test('a task can be created with labels via json', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $label = TaskLabel::create(['clinic_id' => $clinic->id, 'name' => 'Urgente', 'color' => '#dc2626']);

    $response = $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Confirmar consulta',
            'description' => 'Ligar antes das 12h',
            'status' => 'todo',
            'priority' => 'alta',
            'assigned_to' => $user->id,
            'due_date' => now()->addDay()->toDateString(),
            'label_ids' => [$label->id],
        ])
        ->assertOk()
        ->assertJsonPath('title', 'Confirmar consulta')
        ->assertJsonPath('labels.0.id', $label->id);

    $task = Task::where('clinic_id', $clinic->id)->where('title', 'Confirmar consulta')->firstOrFail();

    expect($task->created_by)->toBe($user->id);
    expect($task->assigned_to)->toBe($user->id);
    expect($task->id)->toBe($response->json('id'));
});

test('a task can be updated via json', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create([
        'clinic_id' => $clinic->id, 'title' => 'Cobrar orçamento', 'status' => 'todo',
        'priority' => 'media', 'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->putJson(route('tasks.update', $task), [
            'title' => 'Cobrar orçamento do paciente',
            'description' => 'Ligar antes de fechar o caixa',
            'status' => 'doing',
            'priority' => 'urgente',
            'label_ids' => [],
        ])
        ->assertOk()
        ->assertJsonPath('title', 'Cobrar orçamento do paciente')
        ->assertJsonPath('status', 'doing');

    $task->refresh();
    expect($task->title)->toBe('Cobrar orçamento do paciente');
    expect($task->priority)->toBe('urgente');
});

test('updating task status to done stamps completed_at, reopening clears it', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create([
        'clinic_id' => $clinic->id, 'title' => 'Solicitar documentação', 'status' => 'todo',
        'priority' => 'media', 'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patchJson(route('tasks.update-status', $task), ['status' => 'done'])
        ->assertOk()
        ->assertJsonPath('status', 'done');

    $task->refresh();
    expect($task->completed_at)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('tasks.update-status', $task), ['status' => 'todo'])
        ->assertOk();

    $task->refresh();
    expect($task->completed_at)->toBeNull();
});

test('a task can be deleted via json', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create([
        'clinic_id' => $clinic->id, 'title' => 'Comprar materiais', 'status' => 'todo',
        'priority' => 'baixa', 'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tasks.destroy', $task))
        ->assertOk()
        ->assertJsonPath('id', $task->id);

    expect(Task::find($task->id))->toBeNull();
});

test('completed tasks older than the default 30-day window drop out of the panel', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $recent = Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída recente', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDays(5)]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída antiga', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDays(45)]);

    $this->actingAs($user)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.id', $recent->id);
});

test('the completed window can be widened via the days parameter, or lifted entirely with "all"', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída recente', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDays(5)]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída há 45 dias', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDays(45)]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída há 2 anos', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDays(730)]);

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['days' => 90]))
        ->assertOk()
        ->assertJsonCount(2, 'tasks');

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['days' => 'all']))
        ->assertOk()
        ->assertJsonCount(3, 'tasks');
});

test('title over 40 characters and description over 3000 characters are rejected', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => str_repeat('a', 41),
            'status' => 'todo',
            'priority' => 'media',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa válida',
            'description' => str_repeat('a', 3001),
            'status' => 'todo',
            'priority' => 'media',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => str_repeat('a', 40),
            'description' => str_repeat('a', 3000),
            'status' => 'todo',
            'priority' => 'media',
        ])
        ->assertOk();
});

test('urgent priority is not allowed together with a due date in the future', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa urgente adiada', 'description' => 'x', 'status' => 'todo',
            'priority' => 'urgente', 'due_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['priority']);

    // Urgente sem vencimento continua permitido.
    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa urgente pra já', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente',
        ])
        ->assertOk();

    // Urgente com vencimento hoje (ou já vencido) também continua permitido
    // — só "Próximas" (depois de hoje) é que bloqueia.
    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa urgente pra hoje', 'description' => 'x', 'status' => 'todo',
            'priority' => 'urgente', 'due_date' => now()->toDateString(),
        ])
        ->assertOk();
});

test('description is required to create or update a task', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('tasks.store'), ['title' => 'Sem descrição', 'status' => 'todo', 'priority' => 'media'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Tarefa existente', 'description' => 'Original', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('tasks.update', $task), ['title' => 'Tarefa existente', 'status' => 'todo', 'priority' => 'media'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('a task cannot have more than 2 labels', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $labelIds = collect(range(1, 3))->map(
        fn ($i) => TaskLabel::create(['clinic_id' => $clinic->id, 'name' => "Rótulo {$i}", 'color' => '#0d9488'])->id
    )->all();

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa com muitas etiquetas',
            'description' => 'Teste de limite',
            'status' => 'todo',
            'priority' => 'media',
            'label_ids' => $labelIds,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa com 2 etiquetas',
            'description' => 'Teste de limite',
            'status' => 'todo',
            'priority' => 'media',
            'label_ids' => array_slice($labelIds, 0, 2),
        ])
        ->assertOk();
});

test('a clinic cannot register more than 10 task labels', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    for ($i = 1; $i <= 10; $i++) {
        TaskLabel::create(['clinic_id' => $clinic->id, 'name' => "Rótulo {$i}", 'color' => '#0d9488']);
    }

    $this->actingAs($user)
        ->postJson(route('task-labels.store'), ['name' => 'Décima primeira'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Limite de etiquetas atingido. Exclua uma etiqueta existente para criar outra.');

    expect(TaskLabel::forClinic($clinic->id)->count())->toBe(10);
});

test('a task label can be created and deleted via json', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $response = $this->actingAs($user)
        ->postJson(route('task-labels.store'), ['name' => 'Financeiro', 'color' => '#0d9488'])
        ->assertOk()
        ->assertJsonPath('name', 'Financeiro');

    $labelId = $response->json('id');
    expect(TaskLabel::find($labelId))->not->toBeNull();

    $this->actingAs($user)
        ->deleteJson(route('task-labels.destroy', $labelId))
        ->assertOk();

    expect(TaskLabel::find($labelId))->toBeNull();
});

test('a label name over 15 characters is rejected', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('task-labels.store'), ['name' => str_repeat('a', 16)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($user)
        ->postJson(route('task-labels.store'), ['name' => str_repeat('a', 15)])
        ->assertOk();
});

test('deleting a label in use requires confirmation, then removes it from the tasks that used it', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $label = TaskLabel::create(['clinic_id' => $clinic->id, 'name' => 'Financeiro', 'color' => '#0d9488']);
    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Cobrar orçamento', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);
    $task->labels()->sync([$label->id]);

    $this->actingAs($user)
        ->deleteJson(route('task-labels.destroy', $label->id))
        ->assertStatus(409)
        ->assertJsonPath('usage_count', 1);

    expect(TaskLabel::find($label->id))->not->toBeNull();

    $this->actingAs($user)
        ->deleteJson(route('task-labels.destroy', $label->id), ['force' => true])
        ->assertOk();

    expect(TaskLabel::find($label->id))->toBeNull();
    expect($task->labels()->count())->toBe(0);
});

test('a task can be linked to a patient of the current clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'João', 'sobrenome' => 'Silva', 'status' => 'ativo']);

    $response = $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Ligar para o paciente',
            'description' => 'Confirmar retorno da próxima semana',
            'status' => 'todo',
            'priority' => 'media',
            'patient_id' => $patient->id,
        ])
        ->assertOk()
        ->assertJsonPath('patient.id', $patient->id);

    $task = Task::findOrFail($response->json('id'));
    expect($task->patient_id)->toBe($patient->id);
});

test('a task cannot be linked to a patient from another clinic', function () {
    ['user' => $user] = setupTaskContext();

    $otherPlan = Plan::create([
        'name' => 'Other Plan', 'slug' => 'other-plan-tasks-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $otherClinic = Clinic::create([
        'name' => 'Outra Clínica', 'slug' => 'outra-clinica-' . uniqid(), 'type' => 'odontologia',
        'status' => 'active', 'plan_id' => $otherPlan->id,
    ]);
    $foreignPatient = Patient::create(['clinic_id' => $otherClinic->id, 'nome' => 'Maria', 'sobrenome' => 'Souza', 'status' => 'ativo']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Ligar para o paciente',
            'status' => 'todo',
            'priority' => 'media',
            'patient_id' => $foreignPatient->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['patient_id']);
});

// Auditoria de segurança — label_ids.* validava só exists:task_labels,id,
// sem checar clinic_id (diferente de task_list_id, duas linhas acima no
// controller). Um label de outra clínica podia ser associado a uma tarefa.
test('a task cannot be associated with a label from another clinic', function () {
    ['user' => $user] = setupTaskContext();

    $otherPlan = Plan::create([
        'name' => 'Other Plan', 'slug' => 'other-plan-labels-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $otherClinic = Clinic::create([
        'name' => 'Outra Clínica Labels', 'slug' => 'outra-clinica-labels-' . uniqid(), 'type' => 'odontologia',
        'status' => 'active', 'plan_id' => $otherPlan->id,
    ]);
    $foreignLabel = TaskLabel::create(['clinic_id' => $otherClinic->id, 'name' => 'Alheio', 'color' => '#dc2626']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa qualquer',
            'description' => 'Descrição qualquer',
            'status' => 'todo',
            'priority' => 'media',
            'label_ids' => [$foreignLabel->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['label_ids.0']);
});

test('a task can still be associated with a global (system-wide) label with a null clinic_id', function () {
    ['user' => $user] = setupTaskContext();
    $globalLabel = TaskLabel::create(['clinic_id' => null, 'name' => 'Global', 'color' => '#2563eb']);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa com etiqueta global',
            'description' => 'Descrição qualquer',
            'status' => 'todo',
            'priority' => 'media',
            'label_ids' => [$globalLabel->id],
        ])
        ->assertOk()
        ->assertJsonPath('labels.0.id', $globalLabel->id);
});

test('the tasks panel endpoint auto-creates default mine/team lists on first access', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonPath('lists.mine.name', 'Minhas tarefas')
        ->assertJsonPath('lists.mine.color', '#3b82f6')
        ->assertJsonPath('lists.mine.sharing_type', 'private')
        ->assertJsonPath('lists.team.name', 'Tarefas da equipe')
        ->assertJsonPath('lists.team.color', '#ef4444')
        ->assertJsonPath('lists.team.sharing_type', 'team');

    expect(TaskList::where('user_id', $user->id)->count())->toBe(2);
});

test('mine/team scopes only allow the color to change — name and sharing stay fixed', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    // Mesmo mandando nome/compartilhamento no payload, só a cor é aplicada —
    // são escopos fixos por regra de negócio (não podem ser renomeados, nem
    // ter o compartilhamento alterado, nem excluídos).
    $this->actingAs($user)
        ->putJson(route('task-lists.update', 'mine'), [
            'name' => 'Meu foco',
            'color' => '#8b5cf6',
            'sharing_type' => 'selected',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Minhas tarefas')
        ->assertJsonPath('color', '#8b5cf6')
        ->assertJsonPath('sharing_type', 'private');

    $list = TaskList::where(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'key' => 'mine'])->firstOrFail();
    expect($list->name)->toBe('Minhas tarefas');
    expect($list->color)->toBe('#8b5cf6');
    expect($list->sharing_type)->toBe('private');

    $this->actingAs($user)
        ->putJson(route('task-lists.update', 'team'), ['color' => '#f59e0b'])
        ->assertOk()
        ->assertJsonPath('name', 'Tarefas da equipe')
        ->assertJsonPath('color', '#f59e0b')
        ->assertJsonPath('sharing_type', 'team');
});

test('updating a fixed scope without a color is rejected', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->putJson(route('task-lists.update', 'mine'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
});

test('an invalid task list key is rejected', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->putJson(route('task-lists.update', 'bogus'), ['name' => 'X', 'color' => '#000', 'sharing_type' => 'private'])
        ->assertStatus(404);
});

test('pinning and favoriting a task toggle their state and reflect in the presented task', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Revisar prontuário', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->patchJson(route('tasks.toggle-pin', $task))
        ->assertOk()
        ->assertJsonPath('pinned_at', fn ($v) => $v !== null);

    $task->refresh();
    expect($task->pinned_at)->not->toBeNull();

    $this->actingAs($user)->patchJson(route('tasks.toggle-pin', $task))->assertOk()->assertJsonPath('pinned_at', null);
    $task->refresh();
    expect($task->pinned_at)->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('tasks.toggle-favorite', $task))
        ->assertOk()
        ->assertJsonPath('is_favorite', true);

    $task->refresh();
    expect($task->is_favorite)->toBeTrue();
});

test('a custom scope can be created and appears for its owner', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('task-lists.store'), [
            'name' => 'Financeiro', 'color' => '#8b5cf6', 'sharing_type' => 'private',
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Financeiro')
        ->assertJsonPath('is_owner', true)
        ->assertJsonPath('task_count', 0);

    $this->actingAs($user)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonCount(1, 'lists.custom')
        ->assertJsonPath('lists.custom.0.name', 'Financeiro');
});

test('a clinic cannot register more than 5 custom scopes', function () {
    ['user' => $user] = setupTaskContext();

    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($user)
            ->postJson(route('task-lists.store'), ['name' => "Escopo {$i}", 'color' => '#000000', 'sharing_type' => 'private'])
            ->assertStatus(201);
    }

    $this->actingAs($user)
        ->postJson(route('task-lists.store'), ['name' => 'Sexto escopo', 'color' => '#000000', 'sharing_type' => 'private'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Limite de escopos personalizados atingido. Exclua um escopo existente para criar outro.');
});

test('a custom scope name over 30 characters is rejected', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('task-lists.store'), ['name' => str_repeat('a', 31), 'color' => '#000', 'sharing_type' => 'private'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('only the creator can update or delete a custom scope', function () {
    ['user' => $user, 'colleague' => $colleague] = setupTaskContext();

    $list = TaskList::create([
        'clinic_id' => session('current_clinic_id'), 'user_id' => $user->id, 'key' => null,
        'name' => 'Compras', 'color' => '#0d9488', 'sharing_type' => 'team',
    ]);

    $this->actingAs($colleague)
        ->putJson(route('task-lists.update-custom', $list->id), ['name' => 'Hackeado', 'color' => '#000', 'sharing_type' => 'team'])
        ->assertStatus(403);

    $this->actingAs($colleague)
        ->deleteJson(route('task-lists.destroy', $list->id))
        ->assertStatus(403);

    $this->actingAs($user)
        ->putJson(route('task-lists.update-custom', $list->id), ['name' => 'Compras e Estoque', 'color' => '#0d9488', 'sharing_type' => 'team'])
        ->assertOk()
        ->assertJsonPath('name', 'Compras e Estoque');
});

test('a custom scope cannot be managed by someone from another clinic', function () {
    ['user' => $foreignUser] = setupTaskContext();

    $otherPlan = Plan::create([
        'name' => 'Other Plan Scope', 'slug' => 'other-plan-scope-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $otherClinic = Clinic::create([
        'name' => 'Clínica Alheia', 'slug' => 'clinica-alheia-' . uniqid(), 'type' => 'odontologia',
        'status' => 'active', 'plan_id' => $otherPlan->id,
    ]);
    $ownerElsewhere = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $otherClinic->users()->attach($ownerElsewhere->id, ['role' => 'owner']);

    $foreignList = TaskList::create([
        'clinic_id' => $otherClinic->id, 'user_id' => $ownerElsewhere->id, 'key' => null,
        'name' => 'Escopo de outra clínica', 'color' => '#000', 'sharing_type' => 'team',
    ]);

    // $foreignUser está autenticado na clínica de setupTaskContext(), não na
    // de $foreignList — mesmo sendo tecnicamente o "dono" (user_id não é o
    // que barra aqui), o clinic_id divergente já é suficiente pra negar.
    $this->actingAs($foreignUser)
        ->putJson(route('task-lists.update-custom', $foreignList->id), ['name' => 'X', 'color' => '#000', 'sharing_type' => 'team'])
        ->assertStatus(403);
});

test('deleting a custom scope moves its tasks back to mine/team and nothing is lost', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $list = TaskList::create([
        'clinic_id' => $clinic->id, 'user_id' => $user->id, 'key' => null,
        'name' => 'Marketing', 'color' => '#0d9488', 'sharing_type' => 'private',
    ]);
    $task = Task::create([
        'clinic_id' => $clinic->id, 'title' => 'Postar nas redes', 'description' => 'x',
        'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id, 'task_list_id' => $list->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('task-lists.destroy', $list->id))
        ->assertOk();

    expect(TaskList::find($list->id))->toBeNull();
    $task->refresh();
    expect($task->task_list_id)->toBeNull();

    // A tarefa não sumiu — volta a aparecer em "Minhas tarefas" (criada por
    // mim, sem responsável).
    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine']))
        ->assertOk()
        ->assertJsonPath('tasks.0.id', $task->id);
});

test('a task created inside a custom scope does not appear in mine/team and disappears when the scope no longer exists', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $listId = $this->actingAs($user)
        ->postJson(route('task-lists.store'), ['name' => 'Recepção', 'color' => '#0d9488', 'sharing_type' => 'private'])
        ->json('id');

    $taskId = $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Confirmar horários', 'description' => 'x', 'status' => 'todo', 'priority' => 'media',
            'task_list_id' => $listId,
        ])
        ->assertOk()
        ->json('id');

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine']))
        ->assertOk()
        ->assertJsonCount(0, 'tasks');

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => $listId]))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.id', $taskId);
});

test('a private custom scope is only accessible to its creator', function () {
    ['user' => $user, 'colleague' => $colleague] = setupTaskContext();

    $listId = $this->actingAs($user)
        ->postJson(route('task-lists.store'), ['name' => 'Pessoal', 'color' => '#0d9488', 'sharing_type' => 'private'])
        ->json('id');

    $this->actingAs($colleague)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonCount(0, 'lists.custom');

    $this->actingAs($colleague)
        ->getJson(route('tasks.index', ['scope' => $listId]))
        ->assertStatus(403);
});

test('a team-shared custom scope is accessible to every clinic member', function () {
    ['user' => $user, 'colleague' => $colleague] = setupTaskContext();

    $listId = $this->actingAs($user)
        ->postJson(route('task-lists.store'), ['name' => 'Projetos', 'color' => '#0d9488', 'sharing_type' => 'team'])
        ->json('id');

    $this->actingAs($colleague)
        ->getJson(route('tasks.index'))
        ->assertOk()
        ->assertJsonCount(1, 'lists.custom');

    $this->actingAs($colleague)
        ->getJson(route('tasks.index', ['scope' => $listId]))
        ->assertOk();
});

test('a selected-sharing custom scope is only accessible to the chosen professionals', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague] = setupTaskContext();

    $outsider = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Recepção', 'status' => 'ativo']);
    $clinic->users()->attach($outsider->id, ['role' => 'staff']);

    $listId = $this->actingAs($user)
        ->postJson(route('task-lists.store'), [
            'name' => 'Time restrito', 'color' => '#0d9488', 'sharing_type' => 'selected',
            'shared_user_ids' => [$colleague->id],
        ])
        ->json('id');

    $this->actingAs($colleague)
        ->getJson(route('tasks.index', ['scope' => $listId]))
        ->assertOk();

    $this->actingAs($outsider)
        ->getJson(route('tasks.index', ['scope' => $listId]))
        ->assertStatus(403);
});

test('the patient search endpoint matches by first or last name and caps at 15 results', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Carlos', 'sobrenome' => 'Pereira', 'status' => 'ativo']);
    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Ana', 'sobrenome' => 'Carletti', 'status' => 'ativo']);
    Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Bruno', 'sobrenome' => 'Souza', 'status' => 'ativo']);

    $this->actingAs($user)
        ->getJson(route('patients.search', ['q' => 'Carl']))
        ->assertOk()
        ->assertJsonCount(2);

    $this->actingAs($user)
        ->getJson(route('patients.search'))
        ->assertOk()
        ->assertJsonCount(3);
});

// ── Urgente sem data → hoje automaticamente ─────────────────────────────────

test('an urgent task created without a due date automatically gets due_date = today', function () {
    ['user' => $user] = setupTaskContext();
    $today = now()->toDateString();

    $response = $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa urgente sem data', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente',
        ])
        ->assertOk();

    expect($response->json('due_date'))->toStartWith($today);

    $task = Task::findOrFail($response->json('id'));
    expect($task->due_date->toDateString())->toBe($today);
});

test('an urgent task created with due_date already set to today keeps that date', function () {
    ['user' => $user] = setupTaskContext();
    $today = now()->toDateString();

    $response = $this->actingAs($user)
        ->postJson(route('tasks.store'), [
            'title' => 'Urgente com data de hoje', 'description' => 'x', 'status' => 'todo',
            'priority' => 'urgente', 'due_date' => $today,
        ])
        ->assertOk();

    expect($response->json('due_date'))->toStartWith($today);
});

test('non-urgent tasks created without a due date stay without one (Entrada)', function () {
    ['user' => $user] = setupTaskContext();

    $this->actingAs($user)
        ->postJson(route('tasks.store'), ['title' => 'Tarefa média sem data', 'description' => 'x', 'status' => 'todo', 'priority' => 'media'])
        ->assertOk()
        ->assertJsonPath('due_date', null);

    $this->actingAs($user)
        ->postJson(route('tasks.store'), ['title' => 'Tarefa baixa sem data', 'description' => 'x', 'status' => 'todo', 'priority' => 'baixa'])
        ->assertOk()
        ->assertJsonPath('due_date', null);
});

test('changing an existing task priority to urgent later does not backfill due_date', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create([
        'clinic_id' => $clinic->id, 'title' => 'Tarefa qualquer', 'description' => 'x',
        'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->putJson(route('tasks.update', $task), [
            'title' => 'Tarefa qualquer', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente',
        ])
        ->assertOk()
        ->assertJsonPath('due_date', null);

    $task->refresh();
    expect($task->due_date)->toBeNull();
});

// ── Concluídas ordenadas exclusivamente por completed_at DESC ──────────────

test('done tasks are ordered by completed_at DESC, with different priorities and completion times', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $mid = Task::create(['clinic_id' => $clinic->id, 'title' => 'Meio', 'status' => 'done', 'priority' => 'alta', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subMinutes(20)]);
    $newest = Task::create(['clinic_id' => $clinic->id, 'title' => 'Mais nova', 'status' => 'done', 'priority' => 'baixa', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subMinutes(5)]);
    $oldest = Task::create(['clinic_id' => $clinic->id, 'title' => 'Mais antiga', 'status' => 'done', 'priority' => 'urgente', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subHour()]);

    $response = $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine', 'days' => 'all']))
        ->assertOk()
        ->assertJsonCount(3, 'tasks');

    expect($response->json('tasks.0.id'))->toBe($newest->id);
    expect($response->json('tasks.1.id'))->toBe($mid->id);
    expect($response->json('tasks.2.id'))->toBe($oldest->id);
});

test('a higher-priority task completed earlier appears after a lower-priority task completed later', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $urgentEarlier = Task::create(['clinic_id' => $clinic->id, 'title' => 'Urgente concluída primeiro', 'status' => 'done', 'priority' => 'urgente', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subHour()]);
    $lowLater = Task::create(['clinic_id' => $clinic->id, 'title' => 'Baixa concluída depois', 'status' => 'done', 'priority' => 'baixa', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subMinutes(10)]);

    $response = $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine', 'days' => 'all']))
        ->assertOk();

    expect($response->json('tasks.0.id'))->toBe($lowLater->id);
    expect($response->json('tasks.1.id'))->toBe($urgentEarlier->id);
});

// ── Painel "Controle" (concluídas hoje) ─────────────────────────────────────

test('control panel shows only tasks completed today, split into mine/team with matching counts', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague] = setupTaskContext();

    $mineToday = Task::create(['clinic_id' => $clinic->id, 'title' => 'Minha concluída hoje', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subHour()]);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Minha concluída ontem', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subDay()]);
    // Vence hoje mas não está concluída — não é "completed_at hoje", não deve entrar no painel.
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Vence hoje mas não concluída', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id, 'due_date' => now()->toDateString()]);
    $teamToday = Task::create(['clinic_id' => $clinic->id, 'title' => 'Da equipe concluída hoje', 'status' => 'done', 'priority' => 'alta', 'created_by' => $colleague->id, 'assigned_to' => $colleague->id, 'completed_at' => now()->subMinutes(20)]);

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $sections = collect($response->json('sections'))->keyBy('key');

    expect($sections['mine']['count'])->toBe(1);
    expect($sections['mine']['tasks'])->toHaveCount(1);
    expect($sections['mine']['tasks'][0]['id'])->toBe($mineToday->id);

    expect($sections['team']['count'])->toBe(1);
    expect($sections['team']['tasks'][0]['id'])->toBe($teamToday->id);
});

test('control panel shows only mine/team sections when the user has no custom scopes', function () {
    ['user' => $user] = setupTaskContext();

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $sections = collect($response->json('sections'));

    expect($sections->pluck('key')->all())->toBe(['mine', 'team']);
    expect($sections->firstWhere('key', 'mine')['count'])->toBe(0);
    expect($sections->firstWhere('key', 'mine')['tasks'])->toBe([]);
});

test('control panel shows a custom scope created by the user, with its completed-today tasks, and hides an empty one', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $list = TaskList::create(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'key' => null, 'name' => 'Marketing', 'color' => '#0d9488', 'sharing_type' => 'private']);
    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Publicar post', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'task_list_id' => $list->id, 'completed_at' => now()->subMinutes(15)]);

    // Escopo sem nenhuma tarefa concluída hoje — não deve gerar seção vazia.
    TaskList::create(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'key' => null, 'name' => 'Vazio', 'color' => '#000000', 'sharing_type' => 'private']);

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $sections = collect($response->json('sections'))->keyBy('key');

    expect($sections)->toHaveCount(3); // mine, team, Marketing (Vazio ficou de fora)
    expect($sections->has((string) $list->id))->toBeTrue();
    expect($sections[(string) $list->id]['name'])->toBe('Marketing');
    expect($sections[(string) $list->id]['count'])->toBe(1);
    expect($sections[(string) $list->id]['tasks'][0]['id'])->toBe($task->id);
});

test('control panel shows a scope shared with the user, but not one they cannot access', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague] = setupTaskContext();

    $sharedList = TaskList::create(['clinic_id' => $clinic->id, 'user_id' => $colleague->id, 'key' => null, 'name' => 'Time restrito', 'color' => '#8b5cf6', 'sharing_type' => 'selected']);
    $sharedList->sharedWith()->attach($user->id);
    $sharedTask = Task::create(['clinic_id' => $clinic->id, 'title' => 'Tarefa do time restrito', 'status' => 'done', 'priority' => 'media', 'created_by' => $colleague->id, 'task_list_id' => $sharedList->id, 'completed_at' => now()->subMinutes(10)]);

    $privateList = TaskList::create(['clinic_id' => $clinic->id, 'user_id' => $colleague->id, 'key' => null, 'name' => 'Pessoal da colega', 'color' => '#000000', 'sharing_type' => 'private']);
    Task::create(['clinic_id' => $clinic->id, 'title' => 'Tarefa privada da colega', 'status' => 'done', 'priority' => 'media', 'created_by' => $colleague->id, 'task_list_id' => $privateList->id, 'completed_at' => now()->subMinutes(10)]);

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $sections = collect($response->json('sections'))->keyBy('key');

    expect($sections->has((string) $sharedList->id))->toBeTrue();
    expect($sections[(string) $sharedList->id]['tasks'][0]['id'])->toBe($sharedTask->id);
    expect($sections->has((string) $privateList->id))->toBeFalse();
});

test('control panel orders tasks within each section by completed_at DESC', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $older = Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída primeiro', 'status' => 'done', 'priority' => 'urgente', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subHour()]);
    $newer = Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída por último', 'status' => 'done', 'priority' => 'baixa', 'created_by' => $user->id, 'assigned_to' => $user->id, 'completed_at' => now()->subMinutes(5)]);

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $mineTasks = collect($response->json('sections'))->firstWhere('key', 'mine')['tasks'];

    expect($mineTasks[0]['id'])->toBe($newer->id);
    expect($mineTasks[1]['id'])->toBe($older->id);
});

// ── Board — endpoint de movimentação (PATCH /tasks/{task}/move) ────────────

test('moving a task to "today" sets due_date to today', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Sem data', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'today'])
        ->assertOk()
        ->assertJsonPath('due_date', fn ($v) => str_starts_with($v, now()->toDateString()));

    $task->refresh();
    expect($task->due_date->toDateString())->toBe(now()->toDateString());
});

test('moving a done task to "today" reopens it and sets due_date to today', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída', 'description' => 'x', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'completed_at' => now()->subDay(), 'due_date' => now()->subDays(3)]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'today'])
        ->assertOk()
        ->assertJsonPath('status', 'todo')
        ->assertJsonPath('completed_at', null);

    $task->refresh();
    expect($task->status)->toBe('todo');
    expect($task->completed_at)->toBeNull();
    expect($task->due_date->toDateString())->toBe(now()->toDateString());
});

test('moving a task to "inbox" clears due_date without touching priority', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Com data', 'description' => 'x', 'status' => 'todo', 'priority' => 'alta', 'created_by' => $user->id, 'due_date' => now()->toDateString()]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'inbox'])
        ->assertOk()
        ->assertJsonPath('due_date', null)
        ->assertJsonPath('priority', 'alta');

    $task->refresh();
    expect($task->due_date)->toBeNull();
    expect($task->priority)->toBe('alta');
});

test('moving an urgent task to "inbox" is allowed (clears due_date, no future date involved)', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Urgente hoje', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente', 'created_by' => $user->id, 'due_date' => now()->toDateString()]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'inbox'])
        ->assertOk()
        ->assertJsonPath('due_date', null)
        ->assertJsonPath('priority', 'urgente');
});

test('moving a task to "upcoming" without a future date is rejected', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Sem data', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'upcoming'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'upcoming', 'due_date' => now()->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    // Falhou — o estado no banco não deve ter mudado (nada pra "restaurar" no frontend).
    $task->refresh();
    expect($task->due_date)->toBeNull();
});

test('moving a task to "upcoming" with a valid future date succeeds', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Vai pra próximas', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id]);
    $future = now()->addDays(5)->toDateString();

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'upcoming', 'due_date' => $future])
        ->assertOk()
        ->assertJsonPath('due_date', fn ($v) => str_starts_with($v, $future));

    $task->refresh();
    expect($task->due_date->toDateString())->toBe($future);
});

test('moving an urgent task to "upcoming" is rejected even with a valid future date', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Urgente', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente', 'created_by' => $user->id, 'due_date' => now()->toDateString()]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'upcoming', 'due_date' => now()->addDays(3)->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $task->refresh();
    expect($task->due_date->toDateString())->toBe(now()->toDateString());
});

test('moving a task to "done" marks it done, stamps completed_at, and keeps due_date', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $dueDate = now()->addDays(2)->toDateString();
    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'A concluir', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id, 'due_date' => $dueDate]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'done'])
        ->assertOk()
        ->assertJsonPath('status', 'done')
        ->assertJsonPath('due_date', fn ($v) => str_starts_with($v, $dueDate));

    $task->refresh();
    expect($task->status)->toBe('done');
    expect($task->completed_at)->not->toBeNull();
    expect($task->due_date->toDateString())->toBe($dueDate);
});

test('moving a task out of "done" reopens it and applies the destination column due_date', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Concluída', 'description' => 'x', 'status' => 'done', 'priority' => 'media', 'created_by' => $user->id, 'completed_at' => now()->subHour(), 'due_date' => now()->subDays(2)]);

    $this->actingAs($user)
        ->patchJson(route('tasks.move', $task), ['column' => 'inbox'])
        ->assertOk()
        ->assertJsonPath('status', 'todo')
        ->assertJsonPath('completed_at', null)
        ->assertJsonPath('due_date', null);

    $task->refresh();
    expect($task->status)->toBe('todo');
    expect($task->completed_at)->toBeNull();
    expect($task->due_date)->toBeNull();
});

test('tasks moved to "done" at different times keep the completed_at DESC ordering', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $urgent = Task::create(['clinic_id' => $clinic->id, 'title' => 'Urgente movida primeiro', 'description' => 'x', 'status' => 'todo', 'priority' => 'urgente', 'created_by' => $user->id]);
    $low = Task::create(['clinic_id' => $clinic->id, 'title' => 'Baixa movida depois', 'description' => 'x', 'status' => 'todo', 'priority' => 'baixa', 'created_by' => $user->id]);

    $this->actingAs($user)->patchJson(route('tasks.move', $urgent), ['column' => 'done'])->assertOk();
    $this->actingAs($user)->patchJson(route('tasks.move', $low), ['column' => 'done'])->assertOk();

    $response = $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => 'mine', 'days' => 'all']))
        ->assertOk();

    // A movida por último (baixa) aparece primeiro, mesmo com prioridade menor.
    expect($response->json('tasks.0.id'))->toBe($low->id);
    expect($response->json('tasks.1.id'))->toBe($urgent->id);
});

test('a task moved to "done" via the board appears in the control panel today', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Movida pro board', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id, 'assigned_to' => $user->id]);

    $this->actingAs($user)->patchJson(route('tasks.move', $task), ['column' => 'done'])->assertOk();

    $response = $this->actingAs($user)->getJson(route('tasks.controle'))->assertOk();
    $mine = collect($response->json('sections'))->firstWhere('key', 'mine');

    expect($mine['count'])->toBe(1);
    expect($mine['tasks'][0]['id'])->toBe($task->id);
});

test('moving a task inside a custom scope keeps it in that scope', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTaskContext();

    $list = TaskList::create(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'key' => null, 'name' => 'Financeiro', 'color' => '#0d9488', 'sharing_type' => 'private']);
    $task = Task::create(['clinic_id' => $clinic->id, 'title' => 'Tarefa do escopo', 'description' => 'x', 'status' => 'todo', 'priority' => 'media', 'created_by' => $user->id, 'task_list_id' => $list->id]);

    $this->actingAs($user)->patchJson(route('tasks.move', $task), ['column' => 'today'])->assertOk();

    $task->refresh();
    expect($task->task_list_id)->toBe($list->id);

    $this->actingAs($user)
        ->getJson(route('tasks.index', ['scope' => $list->id]))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.id', $task->id);
});
