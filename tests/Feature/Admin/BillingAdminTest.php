<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\ReferralPayment;
use App\Models\ReferralWallet;
use App\Models\SystemAdmin;
use App\Models\User;

// Fase System Admin/Backoffice — BillingController foi extraído do antigo
// DashboardController (RC-16) sem alterar comportamento. Zero testes
// cobriam approvePayment/rejectPayment antes desta fase — cobrindo agora.

function setupBillingAdminContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-billingadmin-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Billing Admin', 'slug' => 'clinica-billingadmin-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $wallet = ReferralWallet::create([
        'clinic_id' => $clinic->id, 'balance' => 100, 'pending_balance' => 0,
        'total_earned' => 100, 'total_withdrawn' => 0, 'pix_type' => 'email', 'pix_key' => 'a@b.com',
    ]);
    $payment = ReferralPayment::create([
        'wallet_id' => $wallet->id, 'amount' => 50, 'pix_type' => 'email', 'pix_key' => 'a@b.com',
        'status' => 'pending', 'requested_at' => now(),
    ]);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('clinic', 'wallet', 'payment', 'admin');
}

test('approving a pending payment updates wallet balance and is audited', function () {
    ['wallet' => $wallet, 'payment' => $payment, 'admin' => $admin] = setupBillingAdminContext();

    $this->actingAs($admin)->postJson(route('admin.payments.approve', $payment->id))->assertOk();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($wallet->fresh()->balance)->toBe(50.0)
        ->and(AccessLog::where('action', 'admin_payment_approved')->exists())->toBeTrue();
});

test('rejecting a pending payment marks it rejected without touching the wallet balance', function () {
    ['wallet' => $wallet, 'payment' => $payment, 'admin' => $admin] = setupBillingAdminContext();

    $this->actingAs($admin)->postJson(route('admin.payments.reject', $payment->id))->assertOk();

    expect($payment->fresh()->status)->toBe('rejected')
        ->and($wallet->fresh()->balance)->toBe(100.0)
        ->and(AccessLog::where('action', 'admin_payment_rejected')->exists())->toBeTrue();
});

test('approving a payment that is not pending is rejected with 422', function () {
    ['payment' => $payment, 'admin' => $admin] = setupBillingAdminContext();
    $payment->update(['status' => 'paid']);

    $this->actingAs($admin)->postJson(route('admin.payments.approve', $payment->id))->assertStatus(422);
});

test('a normal user cannot approve or reject payments', function () {
    ['payment' => $payment] = setupBillingAdminContext();
    $normal = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($normal)->postJson(route('admin.payments.approve', $payment->id))->assertForbidden();
    $this->actingAs($normal)->postJson(route('admin.payments.reject', $payment->id))->assertForbidden();
});

test('plan update preserves existing behavior and is audited', function () {
    ['admin' => $admin] = setupBillingAdminContext();
    $plan = Plan::first();

    $this->actingAs($admin)->putJson(route('admin.plans.update', $plan->id), [
        'name' => 'Plano Renomeado', 'price_monthly' => 199, 'price_yearly' => 1990,
        'trial_days' => 14, 'is_active' => true,
    ])->assertOk();

    expect($plan->fresh()->name)->toBe('Plano Renomeado')
        ->and(AccessLog::where('action', 'admin_plan_updated')->exists())->toBeTrue();
});
