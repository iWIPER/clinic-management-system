<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralConversion;
use App\Models\ReferralPayment;
use App\Models\ReferralSettings;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(): \Inertia\Response
    {
        $totalClinics     = Clinic::count();
        $trialing         = Subscription::where('status', 'trial')->count();
        $activeSubc       = Subscription::where('status', 'active')->count();
        $cancelledSubc    = Subscription::where('status', 'cancelled')->count();
        $totalConversions = ReferralConversion::count();
        $paidConversions  = ReferralConversion::where('status', 'paid')->count();
        $pendingPayments  = ReferralPayment::where('status', 'pending')->count();

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

        // Ranking de indicadores
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

        // Conversões por status
        $conversionsByStatus = ReferralConversion::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Conversões por plano
        $conversionsByPlan = ReferralConversion::select('plan_id', DB::raw('count(*) as total'))
            ->whereNotNull('plan_id')
            ->with('plan:id,name')
            ->groupBy('plan_id')
            ->get()
            ->map(fn ($c) => ['plan' => $c->plan?->name ?? '—', 'total' => $c->total]);

        $settings = ReferralSettings::current();
        $plans    = Plan::withCount('subscriptions')->orderBy('sort_order')->get();

        // Últimas clínicas cadastradas
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

        // Pagamentos pendentes
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

        return Inertia::render('Admin/Index', [
            'stats' => [
                'total_clinics'       => $totalClinics,
                'trialing'            => $trialing,
                'active_subscriptions'=> $activeSubc,
                'cancelled'           => $cancelledSubc,
                'revenue_month'       => $revenueMonth,
                'revenue_year'        => $revenueYear,
                'mrr'                 => $mrr,
                'total_conversions'   => $totalConversions,
                'paid_conversions'    => $paidConversions,
                'pending_payments'    => $pendingPayments,
                'churn'               => $totalClinics > 0 ? round(($cancelledSubc / max($totalClinics, 1)) * 100, 1) : 0,
            ],
            'top_referrers'       => $topReferrers,
            'conversions_by_status' => $conversionsByStatus,
            'conversions_by_plan'   => $conversionsByPlan,
            'recent_clinics'        => $recentClinics,
            'pending_payments'      => $pendingPaymentsList,
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

        \App\Models\AccessLog::record(
            action: 'admin_settings_updated',
            description: 'Configurações do programa de indicações atualizadas pelo super-admin',
            metadata: $validated,
        );

        return response()->json(['ok' => true]);
    }

    public function approvePayment(\Illuminate\Http\Request $request, ReferralPayment $payment): \Illuminate\Http\JsonResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pagamento não está pendente.');

        $payment->update([
            'status'       => 'paid',
            'processed_at' => now(),
            'processed_by' => \Illuminate\Support\Facades\Auth::id(),
            'notes'        => $request->input('notes'),
        ]);

        // Atualizar carteira
        $wallet = $payment->wallet;
        $wallet->decrement('balance', $payment->amount);
        $wallet->increment('total_withdrawn', $payment->amount);
        $wallet->update(['last_payment_at' => now()]);

        \App\Models\AccessLog::record(
            action: 'admin_payment_approved',
            description: "Pagamento de R$ {$payment->amount} aprovado para {$wallet->clinic?->name}",
            metadata: ['payment_id' => $payment->id, 'amount' => $payment->amount],
        );

        \App\Models\AccessLog::record(
            action: 'referral_payment_sent',
            description: 'Seu pagamento foi enviado via PIX.',
            metadata: ['payment_id' => $payment->id, 'amount' => $payment->amount],
            clinicId: $wallet->clinic_id,
        );

        return response()->json(['ok' => true]);
    }

    public function rejectPayment(\Illuminate\Http\Request $request, ReferralPayment $payment): \Illuminate\Http\JsonResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pagamento não está pendente.');

        $payment->update([
            'status'       => 'rejected',
            'processed_at' => now(),
            'processed_by' => \Illuminate\Support\Facades\Auth::id(),
            'notes'        => $request->input('notes', 'Reprovado pelo administrador.'),
        ]);

        \App\Models\AccessLog::record(
            action: 'admin_payment_rejected',
            description: "Pagamento de R$ {$payment->amount} recusado para {$payment->wallet?->clinic?->name}",
            metadata: ['payment_id' => $payment->id],
        );

        return response()->json(['ok' => true]);
    }

    public function refundConversion(
        \Illuminate\Http\Request $request,
        ReferralConversion $conversion,
        \App\Services\ReferralService $referralService
    ): \Illuminate\Http\JsonResponse {
        $reason = $request->input('reason', '');
        $referralService->markRefunded($conversion, $reason);

        \App\Models\AccessLog::record(
            action: 'admin_referral_refunded',
            description: "Indicação #{$conversion->id} marcada como estornada pelo super-admin",
            metadata: ['conversion_id' => $conversion->id, 'reason' => $reason],
        );

        return response()->json(['ok' => true]);
    }

    public function reviewConversion(
        \Illuminate\Http\Request $request,
        ReferralConversion $conversion,
        \App\Services\ReferralService $referralService
    ): \Illuminate\Http\JsonResponse {
        $reason = $request->input('reason', '');
        $referralService->markUnderReview($conversion, $reason);

        \App\Models\AccessLog::record(
            action: 'admin_referral_under_review',
            description: "Indicação #{$conversion->id} colocada em revisão pelo super-admin",
            metadata: ['conversion_id' => $conversion->id, 'reason' => $reason],
        );

        return response()->json(['ok' => true]);
    }

    public function inviteAffiliate(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $invite = \App\Models\Invite::create([
            'type'          => 'affiliate',
            'clinic_id'     => null,
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'invited_by_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        \App\Models\AccessLog::record(
            action: 'admin_affiliate_invited',
            description: "Convite de afiliado criado para {$invite->name} ({$invite->email})",
            metadata: ['invite_id' => $invite->id],
        );

        return response()->json([
            'invite_link' => config('app.url') . '/convites/' . $invite->short_token,
        ]);
    }

    public function clinics(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $query = Clinic::with(['subscription.plan'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")
                ->orWhere('trade_name', 'ilike', "%{$s}%"))
            ->latest();

        $clinics = $query->paginate(25)->through(fn ($c) => [
            'id'         => $c->id,
            'name'       => $c->trade_name ?? $c->name,
            'created_at' => $c->created_at->format('d/m/Y'),
            'plan'       => $c->subscription?->plan?->name ?? 'Sem plano',
            'status'     => $c->subscription?->status ?? 'sem_assinatura',
            'referral_code' => Referral::where('clinic_id', $c->id)->value('code'),
        ]);

        return Inertia::render('Admin/Clinics/Index', [
            'clinics' => $clinics,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function referrals(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $conversions = ReferralConversion::with(['referral.clinic:id,name,trade_name', 'referral.affiliate:id,name', 'referredClinic:id,name', 'plan:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(30)
            ->through(fn ($c) => [
                'id'             => $c->id,
                'referrer'       => $c->referral?->ownerDisplayName() ?? '—',
                'referred'       => $c->referredClinic?->name ?? '—',
                'plan'           => $c->plan?->name ?? '—',
                'reward_amount'  => $c->reward_amount,
                'status'         => $c->status,
                'status_label'   => $c->statusLabel(),
                'trial_started'  => $c->trial_started_at?->format('d/m/Y'),
                'eligible_at'    => $c->eligible_at?->format('d/m/Y'),
                'paid_at'        => $c->paid_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Referrals/Index', [
            'conversions' => $conversions,
            'filters'     => ['status' => $request->status],
            'status_options' => ReferralConversion::STATUS_LABELS,
        ]);
    }

    public function plans(): \Inertia\Response
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'description'   => $p->description,
                'price_monthly' => $p->price_monthly,
                'price_yearly'  => $p->price_yearly,
                'trial_days'    => $p->trial_days,
                'max_patients'  => $p->max_patients,
                'max_users'     => $p->max_users,
                'is_active'     => $p->is_active,
                'is_featured'   => $p->is_featured,
                'features'      => $p->features->map(fn ($f) => [
                    'label'    => $f->feature_label,
                    'included' => $f->included,
                ]),
            ]),
        ]);
    }

    public function updatePlan(\Illuminate\Http\Request $request, Plan $plan): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly'  => 'required|numeric|min:0',
            'trial_days'    => 'required|integer|min:0',
            'max_patients'  => 'nullable|integer|min:1',
            'max_users'     => 'nullable|integer|min:1',
            'is_active'     => 'required|boolean',
            'description'   => 'nullable|string|max:500',
        ]);

        $plan->update($validated);

        \App\Models\AccessLog::record(
            action: 'admin_plan_updated',
            description: "Plano {$plan->name} atualizado pelo super-admin",
            metadata: $validated,
        );

        return response()->json(['ok' => true, 'plan' => $plan->fresh()]);
    }

    public function blockClinic(Clinic $clinic): \Illuminate\Http\JsonResponse
    {
        $clinic->update(['status' => 'suspended']);

        \App\Models\AccessLog::record(
            action: 'admin_clinic_blocked',
            description: "Clínica {$clinic->displayName()} bloqueada pelo super-admin",
            metadata: ['clinic_id' => $clinic->id],
        );

        return response()->json(['ok' => true]);
    }

    public function unblockClinic(Clinic $clinic): \Illuminate\Http\JsonResponse
    {
        $clinic->update(['status' => 'active']);

        \App\Models\AccessLog::record(
            action: 'admin_clinic_unblocked',
            description: "Clínica {$clinic->displayName()} desbloqueada pelo super-admin",
            metadata: ['clinic_id' => $clinic->id],
        );

        return response()->json(['ok' => true]);
    }

    public function logs(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $range = $request->get('range', '7days');
        $from  = match ($range) {
            'today'  => now()->startOfDay(),
            '30days' => now()->subDays(30)->startOfDay(),
            default  => now()->subDays(7)->startOfDay(),
        };

        $query = \App\Models\AccessLog::with(['user:id,name,email', 'clinic:id,name'])
            ->where('created_at', '>=', $from);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('created_at')->paginate(50)->through(fn ($log) => [
            'id'           => $log->id,
            'action'       => $log->action,
            'action_label' => $log->action_label,
            'description'  => $log->description,
            'ip_address'   => $log->ip_address,
            'browser'      => $log->browser,
            'user'         => $log->user?->name ?? '—',
            'clinic'       => $log->clinic?->name ?? '—',
            'created_at'   => $log->created_at->toISOString(),
            'metadata'     => $log->metadata,
        ]);

        return Inertia::render('Admin/Logs/Index', [
            'logs'    => $logs,
            'filters' => ['range' => $range, 'search' => $search ?? ''],
        ]);
    }
}
