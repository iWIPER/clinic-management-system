<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

// Fase System Admin/Backoffice — LogController extraído do antigo
// DashboardController (RC-16) + filtros novos (admin/ação/clínica), além
// do range+busca que já existia. As próprias ações sensíveis (promoção de
// admin, bloqueio de clínica/usuário, exportação, exclusão) já são
// provadas gravando AccessLog nos outros arquivos de teste desta fase —
// aqui cobrimos a tela de consulta em si.

function setupLogAdminContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-logadmin-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Log Admin', 'slug' => 'clinica-logadmin-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('clinic', 'admin');
}

test('a normal user cannot access the audit log', function () {
    $normal = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($normal)->get(route('admin.logs'))->assertForbidden();
});

test('index lists recent entries within the default range and exposes action_options for the filter', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupLogAdminContext();

    AccessLog::record(action: 'admin_clinic_blocked', description: 'teste', userId: $admin->id, clinicId: $clinic->id);

    $this->actingAs($admin)
        ->get(route('admin.logs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Logs/Index')
            ->has('action_options')
            ->where('logs.data.0.action', 'admin_clinic_blocked')
        );
});

test('filtering by action returns only matching entries', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupLogAdminContext();

    AccessLog::record(action: 'admin_clinic_blocked', description: 'x', userId: $admin->id, clinicId: $clinic->id);
    AccessLog::record(action: 'admin_plan_updated', description: 'y', userId: $admin->id, clinicId: $clinic->id);

    $this->actingAs($admin)
        ->get(route('admin.logs', ['action' => 'admin_plan_updated', 'range' => 'all']))
        ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'admin_plan_updated'));
});

test('filtering by clinic_id returns only that clinic\'s entries', function () {
    ['clinic' => $clinicA, 'admin' => $admin] = setupLogAdminContext();
    $planB = Plan::create([
        'name' => 'Test Plan B', 'slug' => 'test-plan-logadmin-b-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinicB = Clinic::create([
        'name' => 'Clínica B Log', 'slug' => 'clinica-b-log-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $planB->id,
    ]);

    AccessLog::record(action: 'admin_clinic_blocked', description: 'a', userId: $admin->id, clinicId: $clinicA->id);
    AccessLog::record(action: 'admin_clinic_blocked', description: 'b', userId: $admin->id, clinicId: $clinicB->id);

    $this->actingAs($admin)
        ->get(route('admin.logs', ['clinic_id' => $clinicB->id, 'range' => 'all']))
        ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.clinic.id', $clinicB->id));
});

test('sensitive admin actions across the whole phase are actually persisted to the audit log', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupLogAdminContext();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)->postJson(route('admin.system-admins.store'), ['email' => $target->email]);
    $this->actingAs($admin)->postJson(route('admin.clinics.block', $clinic->id));
    $this->actingAs($admin)->post(route('admin.exports.download', 'clinics'));

    $actions = AccessLog::whereIn('action', ['system_admin_granted', 'admin_clinic_blocked', 'admin_export_downloaded'])
        ->pluck('action')->unique();

    expect($actions)->toHaveCount(3);
});
