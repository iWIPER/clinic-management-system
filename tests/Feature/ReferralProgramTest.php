<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralSettings;
use App\Models\User;
use App\Services\ReferralService;

function setupReferralContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-ref-' . uniqid(),
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
        'name' => 'Clínica Indicadora',
        'slug' => 'clinica-ref-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    ReferralSettings::current();

    return compact('user', 'clinic', 'plan');
}

test('clinic gets permanent referral code and link', function () {
    ['clinic' => $clinic] = setupReferralContext();

    $referral = app(ReferralService::class)->getOrCreate($clinic);

    expect($referral->code)->not->toBeEmpty();
    expect($referral->link())->toContain('/r/' . $referral->code);
});

test('referral link renders landing page and stores code in session', function () {
    ['clinic' => $clinic] = setupReferralContext();

    $referral = Referral::create([
        'clinic_id' => $clinic->id,
        'code'      => 'ABC-123',
        'is_active' => true,
    ]);

    $response = $this->get('/r/ABC123');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Referrals/Landing'));
    expect(session('referral_code'))->toBe($referral->code);
});

test('referral link never redirects straight to login', function () {
    ['clinic' => $clinic] = setupReferralContext();

    Referral::create([
        'clinic_id' => $clinic->id,
        'code'      => 'XYZ-789',
        'is_active' => true,
    ]);

    $response = $this->get('/r/XYZ789');

    $response->assertOk();
    expect($response->headers->get('Location'))->toBeNull();
});

test('referral dashboard is accessible for authenticated clinic user', function () {
    ['user' => $user, 'clinic' => $clinic] = setupReferralContext();

    $response = $this->actingAs($user)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('referrals.index'));

    $response->assertOk();
});

test('super admin can access backoffice', function () {
    $admin = User::factory()->create(['email' => 'lellis.joseanesl@gmail.com']);

    $response = $this->actingAs($admin)->get(route('admin.index'));

    $response->assertOk();
});

test('non super admin cannot access backoffice', function () {
    $user = User::factory()->create(['email' => 'other@example.com']);

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response->assertForbidden();
});

test('affiliate account gets its own referral link and wallet without a clinic', function () {
    $affiliate = User::factory()->create(['account_type' => 'affiliate']);
    ReferralSettings::current();

    $referral = app(ReferralService::class)->getOrCreate($affiliate);
    $wallet   = app(ReferralService::class)->getOrCreateWallet($affiliate);

    expect($referral->clinic_id)->toBeNull();
    expect($referral->affiliate_user_id)->toBe($affiliate->id);
    expect($wallet->affiliate_user_id)->toBe($affiliate->id);
});

test('affiliate can access the affiliate dashboard but not clinic routes', function () {
    $affiliate = User::factory()->create(['account_type' => 'affiliate', 'email_verified_at' => now()]);

    $this->actingAs($affiliate)->get(route('affiliate.dashboard'))->assertOk();
    $this->actingAs($affiliate)->get(route('dashboard'))->assertRedirect(route('affiliate.dashboard'));
});

test('regular clinic user cannot access the affiliate dashboard', function () {
    ['user' => $user] = setupReferralContext();

    $this->actingAs($user)->get(route('affiliate.dashboard'))->assertForbidden();
});

test('checkout summary applies the referred discount only for clinics that came from a referral', function () {
    ['user' => $referredUser, 'clinic' => $referredClinic] = setupReferralContext();
    ['clinic' => $referrerClinic] = setupReferralContext();

    $paidPlan = \App\Models\Plan::create([
        'name' => 'Pro',
        'slug' => 'pro-test-' . uniqid(),
        'is_free' => false,
        'price_monthly_cents' => 20000,
        'price_yearly_cents' => 200000,
        'stripe_price_id_monthly' => 'price_test_123',
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $referral = \App\Models\Referral::create([
        'clinic_id' => $referrerClinic->id,
        'code'      => 'DSC-001',
        'is_active' => true,
    ]);

    \App\Models\ReferralConversion::create([
        'referral_id'        => $referral->id,
        'referred_clinic_id' => $referredClinic->id,
        'reward_amount'      => 50,
        'status'             => \App\Models\ReferralConversion::STATUS_TESTING,
        'trial_started_at'   => now(),
    ]);

    ReferralSettings::current()->update(['referred_discount_amount' => 30]);

    $response = $this->actingAs($referredUser)
        ->withSession(['current_clinic_id' => $referredClinic->id])
        ->get(route('checkout.show', $paidPlan->slug));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Checkout/Show')
        ->where('has_discount', true)
        ->where('discount_amount', 30)
        ->where('total_amount', 170)
    );
});