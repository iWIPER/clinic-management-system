<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralConversion;
use App\Models\ReferralPayment;
use App\Models\ReferralSettings;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Fase System Admin/Backoffice — DashboardController ficou só com o
 * dashboard geral + configurações realmente globais da plataforma
 * (updateSettings). Clínicas, planos, billing, indicações e logs foram
 * extraídos pra controllers próprios por domínio (achado RC-16 da
 * auditoria C0, resolvido nesta fase — ver ClinicController,
 * PlanController, BillingController, ReferralAdminController,
 * LogController).
 */
class DashboardController extends Controller
{
    public function index(): \Inertia\Response
    {
        $totalClinics      = Clinic::count();
        $activeClinics     = Clinic::where('status', 'active')->count();
        $blockedClinics    = Clinic::where('status', 'suspended')->count();
        $trialing          = Subscription::where('status', 'trial')->count();
        $activeSubc        = Subscription::where('status', 'active')->count();
        $cancelledSubc     = Subscription::where('status', 'cancelled')->count();
        $totalUsers        = User::count();
        $newUsers30d       = User::where('created_at', '>=', now()->subDays(30))->count();
        $newClinics30d     = Clinic::where('created_at', '>=', now()->subDays(30))->count();
        $totalConversions  = ReferralConversion::count();
        $paidConversions   = ReferralConversion::where('status', 'paid')->count();
        $pendingPayments   = ReferralPayment::where('status', 'pending')->count();

        $revenueMonth = DB::table('invoices')
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $revenueYear = DB::table('invoices')
            ->where('status', 'paid')
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $mrr = DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.interval', 'monthly')
            ->sum('plans.price_monthly');

        // Distribuição de clínicas por plano (contagem real, não estimada)
        $clinicsByPlan = Subscription::select('plan_id', DB::raw('count(*) as total'))
            ->whereIn('status', ['trial', 'active'])
            ->with('plan:id,name')
            ->groupBy('plan_id')
            ->get()
            ->map(fn ($s) => ['plan' => $s->plan?->name ?? '—', 'total' => $s->total]);

        // Cadastros de clínicas nos últimos 30 dias, por dia — só quando há
        // dados suficientes pra fazer sentido como tendência (evita gráfico
        // vazio/enganoso em ambiente com pouquíssimas clínicas).
        $clinicSignupTrend = $newClinics30d >= 3
            ? Clinic::selectRaw('DATE(created_at) as day, count(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($r) => ['day' => $r->day, 'total' => $r->total])
            : [];

        $topReferrers = Referral::with('clinic:id,name')
            ->orderByDesc('conversions_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'clinic_name'      => $r->clinic?->name ?? '—',
                'code'             => $r->code,
                'clicks_count'     => $r->clicks_count,
                'conversions_count'=> $r->conversions_count,
                'conversion_rate'  => $r->conversionRate(),
            ]);

        $conversionsByStatus = ReferralConversion::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $conversionsByPlan = ReferralConversion::select('plan_id', DB::raw('count(*) as total'))
            ->whereNotNull('plan_id')
            ->with('plan:id,name')
            ->groupBy('plan_id')
            ->get()
            ->map(fn ($c) => ['plan' => $c->plan?->name ?? '—', 'total' => $c->total]);

        $settings = ReferralSettings::current();
        $plans    = Plan::withCount('subscriptions')->orderBy('sort_order')->get();

        $recentClinics = Clinic::with(['subscription.plan'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'created_at'   => $c->created_at->toISOString(),
                'plan'         => $c->subscription?->plan?->name ?? 'Sem plano',
                'status'       => $c->subscription?->status ?? 'sem_assinatura',
            ]);

        $pendingPaymentsList = ReferralPayment::with(['wallet.clinic:id,name'])
            ->where('status', 'pending')
            ->latest('requested_at')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'clinic_name'  => $p->wallet?->clinic?->name ?? '—',
                'amount'       => $p->amount,
                'pix_type'     => $p->pix_type,
                'pix_key'      => $p->pix_key,
                'requested_at' => $p->requested_at->toISOString(),
            ]);

        // Atividade administrativa recente — só ações que carregam a marca
        // "admin_*"/"system_admin_*" (não o log clínico do dia a dia).
        $recentAdminActivity = AccessLog::where(function ($q) {
                $q->where('action', 'like', 'admin_%')->orWhere('action', 'like', 'system_admin_%');
            })
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(15)
            ->get()
            ->map(fn ($log) => [
                'id'           => $log->id,
                'action_label' => $log->action_label,
                'description'  => $log->description,
                'user'         => $log->user?->name ?? '—',
                'created_at'   => $log->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Index', [
            'stats' => [
                'total_clinics'        => $totalClinics,
                'active_clinics'       => $activeClinics,
                'blocked_clinics'      => $blockedClinics,
                'trialing'             => $trialing,
                'active_subscriptions' => $activeSubc,
                'cancelled'            => $cancelledSubc,
                'total_users'          => $totalUsers,
                'new_users_30d'        => $newUsers30d,
                'new_clinics_30d'      => $newClinics30d,
                'revenue_month'        => $revenueMonth,
                'revenue_year'         => $revenueYear,
                'mrr'                  => $mrr,
                'total_conversions'    => $totalConversions,
                'paid_conversions'     => $paidConversions,
                'pending_payments'     => $pendingPayments,
                'churn'                => $totalClinics > 0 ? round(($cancelledSubc / max($totalClinics, 1)) * 100, 1) : 0,
            ],
            'clinics_by_plan'       => $clinicsByPlan,
            'clinic_signup_trend'   => $clinicSignupTrend,
            'top_referrers'         => $topReferrers,
            'conversions_by_status' => $conversionsByStatus,
            'conversions_by_plan'   => $conversionsByPlan,
            'recent_clinics'        => $recentClinics,
            'pending_payments'      => $pendingPaymentsList,
            'recent_admin_activity' => $recentAdminActivity,
            'settings'              => [
                'reward_amount'            => $settings->reward_amount,
                'referred_discount_amount' => $settings->referred_discount_amount,
                'minimum_withdraw'         => $settings->minimum_withdraw,
                'trial_days'               => $settings->trial_days,
                'enabled'                  => $settings->enabled,
            ],
            'plans' => $plans->map(fn ($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'price_monthly'     => $p->price_monthly,
                'price_yearly'      => $p->price_yearly,
                'is_active'         => $p->is_active,
                'subscriptions_count' => $p->subscriptions_count,
            ]),
        ]);
    }

    /**
     * Marca que o admin já viu o aviso de acesso privilegiado — persistido
     * no usuário (não na sessão), pra nunca reaparecer de novo depois de
     * reconhecido uma vez, em nenhum login futuro. Puramente informativo
     * (ver HandleInertiaRequests); nunca usado por nenhuma checagem de
     * autorização.
     */
    public function acknowledgeAccess(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            'preferences' => [...($user->preferences ?? []), 'admin_notice_acknowledged_at' => now()->toIso8601String()],
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function updateSettings(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'reward_amount'            => 'required|numeric|min:0',
            'referred_discount_amount' => 'required|numeric|min:0',
            'minimum_withdraw'         => 'required|numeric|min:0',
            'trial_days'               => 'required|integer|min:1|max:365',
            'enabled'                  => 'required|boolean',
        ]);

        ReferralSettings::current()->update($validated);

        AccessLog::record(
            action: 'admin_settings_updated',
            description: 'Configurações do programa de indicações atualizadas pelo administrador da plataforma',
            metadata: $validated,
        );

        return response()->json(['ok' => true]);
    }
}
