<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\ReferralConversion;
use App\Models\ReferralSettings;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\UserAvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $service) {}

    /**
     * The current actor's referral program owner: the active Clinic for
     * regular clinic users, or the user themself for standalone affiliates.
     */
    private function resolveCurrentOwner(): Clinic|User
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isAffiliate()) {
            return $user;
        }

        return Clinic::findOrFail(session('current_clinic_id'));
    }

    // ── Dashboard do programa de indicações ──────────────────────────────
    public function index(Request $request): \Inertia\Response
    {
        $owner    = $this->resolveCurrentOwner();
        $referral = $this->service->getOrCreate($owner);
        $wallet   = $this->service->getOrCreateWallet($owner);
        $settings = ReferralSettings::current();

        $conversions = $referral->conversions()
            ->with(['referredClinic:id,name,city', 'plan:id,name'])
            ->when($request->search, fn ($q, $s) => $q->whereHas(
                'referredClinic',
                fn ($qq) => $qq->where('name', 'like', "%{$s}%")
            ))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'              => $c->id,
                'clinic_name'     => $c->referredClinic?->name ?? '—',
                'clinic_city'     => $c->referredClinic?->city ?? null,
                'plan_name'       => $c->plan?->name ?? '—',
                'reward_amount'   => $c->reward_amount,
                'status'          => $c->status,
                'status_label'    => $c->statusLabel(),
                'days_remaining'  => $c->daysRemaining(),
                'eligible_at'     => $c->expectedEligibleAt()?->toISOString(),
                'trial_started'   => $c->trial_started_at?->toISOString(),
                'plan_subscribed' => $c->plan_subscribed_at?->toISOString(),
                'paid_at'         => $c->paid_at?->toISOString(),
                'cancelled_at'    => $c->cancelled_at?->toISOString(),
            ]);

        // Agrupamento em PHP (não em SQL) para funcionar igual em qualquer driver
        // de banco (MySQL em produção, SQLite nos testes).
        $monthlyConversions = $referral->conversions()
            ->where('trial_started_at', '>=', now()->subMonths(11)->startOfMonth())
            ->get('trial_started_at')
            ->groupBy(fn ($c) => $c->trial_started_at->format('Y-m'))
            ->map->count();

        $monthlyChart = collect(range(11, 0))->map(function ($i) use ($monthlyConversions) {
            $month = now()->subMonths($i);
            $key   = $month->format('Y-m');

            return [
                'label' => $month->translatedFormat('M/y'),
                'total' => (int) ($monthlyConversions[$key] ?? 0),
            ];
        });

        $transactions = $wallet->transactions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'amount'      => $t->amount,
                'description' => $t->description,
                'status'      => $t->status,
                'created_at'  => $t->created_at->toISOString(),
            ]);

        $payments = $wallet->payments()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'amount'       => $p->amount,
                'pix_type'     => $p->pix_type,
                'pix_key'      => $p->pix_key,
                'status'       => $p->status,
                'requested_at' => $p->requested_at->toISOString(),
                'processed_at' => $p->processed_at?->toISOString(),
            ]);

        return Inertia::render('Referrals/Index', [
            'referral' => [
                'id'               => $referral->id,
                'code'             => $referral->code,
                'link'             => $referral->link(),
                'clicks_count'     => $referral->clicks_count,
                'conversions_count'=> $referral->conversions_count,
                'is_active'        => $referral->is_active,
            ],
            'wallet' => [
                'balance'            => $wallet->balance,
                'pending_balance'    => $wallet->pending_balance,
                'total_earned'       => $wallet->total_earned,
                'total_withdrawn'    => $wallet->total_withdrawn,
                'last_payment_at'    => $wallet->last_payment_at?->toISOString(),
                'pix_type'           => $wallet->pix_type,
                'pix_key'            => $wallet->pix_key,
                'next_withdrawal_at' => $this->nextWithdrawalDate()->toISOString(),
            ],
            'stats'         => $this->service->stats($owner),
            'conversions'   => $conversions,
            'filters'       => ['search' => $request->search, 'status' => $request->status],
            'monthly_chart' => $monthlyChart,
            'transactions'  => $transactions,
            'payments'      => $payments,
            'settings'     => [
                'reward_amount'            => $settings->reward_amount,
                'referred_discount_amount' => $settings->referred_discount_amount,
                'minimum_withdraw'         => $settings->minimum_withdraw,
                'trial_days'               => $settings->trial_days,
                'enabled'                  => $settings->enabled,
            ],
        ]);
    }

    /**
     * Data prevista do próximo lote de saques — 1ª segunda-feira do próximo mês.
     */
    private function nextWithdrawalDate(): \Carbon\Carbon
    {
        $firstOfNextMonth = now()->addMonthNoOverflow()->startOfMonth();

        return $firstOfNextMonth->next(\Carbon\Carbon::MONDAY);
    }

    // ── Exportar histórico de indicações em CSV ───────────────────────────
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $owner    = $this->resolveCurrentOwner();
        $referral = $this->service->getOrCreate($owner);

        $conversions = $referral->conversions()
            ->with(['referredClinic:id,name,city', 'plan:id,name'])
            ->when($request->search, fn ($q, $s) => $q->whereHas(
                'referredClinic',
                fn ($qq) => $qq->where('name', 'like', "%{$s}%")
            ))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->get();

        $filename = 'indicacoes-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($conversions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['Nome', 'Status', 'Data', 'Valor', 'Observação']);

            foreach ($conversions as $c) {
                fputcsv($handle, [
                    $c->referredClinic?->name ?? '—',
                    $c->statusLabel(),
                    $c->trial_started_at?->format('d/m/Y') ?? '—',
                    number_format($c->reward_amount, 2, ',', '.'),
                    $c->referredClinic?->city ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Atualizar chave PIX ───────────────────────────────────────────────
    public function updatePix(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pix_type' => 'required|in:cpf,cnpj,email,phone,random',
            'pix_key'  => 'required|string|max:150',
        ]);

        $wallet = $this->service->getOrCreateWallet($this->resolveCurrentOwner());
        $wallet->update($validated);

        AccessLog::record(
            action: 'referral_pix_updated',
            description: 'Chave PIX da carteira de indicações atualizada',
            metadata: ['pix_type' => $validated['pix_type']],
        );

        return response()->json(['ok' => true]);
    }

    // ── Solicitar saque ───────────────────────────────────────────────────
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $wallet   = $this->service->getOrCreateWallet($this->resolveCurrentOwner());
        $settings = ReferralSettings::current();

        abort_if(! $wallet->pix_key, 422, 'Cadastre uma chave PIX antes de solicitar o saque.');
        abort_if($wallet->balance < $settings->minimum_withdraw, 422,
            "Saldo mínimo para saque é R$ {$settings->minimum_withdraw}. Saldo atual: R$ {$wallet->balance}."
        );

        $validated = $request->validate([
            'amount' => "required|numeric|min:{$settings->minimum_withdraw}|max:{$wallet->balance}",
        ]);

        $payment = $wallet->payments()->create([
            'amount'       => $validated['amount'],
            'pix_type'     => $wallet->pix_type,
            'pix_key'      => $wallet->pix_key,
            'status'       => 'pending',
            'requested_at' => now(),
        ]);

        AccessLog::record(
            action: 'referral_withdrawal_requested',
            description: "Saque de R$ {$validated['amount']} solicitado",
            metadata: ['payment_id' => $payment->id, 'amount' => $validated['amount']],
        );

        return response()->json(['ok' => true, 'payment_id' => $payment->id]);
    }

    // ── Detalhe de uma indicação ──────────────────────────────────────────
    public function show(ReferralConversion $conversion): \Inertia\Response
    {
        $owner = $this->resolveCurrentOwner();
        abort_unless($conversion->referral?->belongsToOwner($owner), 403);

        $conversion->load(['referredClinic:id,name,city', 'plan:id,name']);

        return Inertia::render('Referrals/Show', [
            'conversion' => [
                'id'              => $conversion->id,
                'clinic_name'     => $conversion->referredClinic?->name ?? '—',
                'clinic_city'     => $conversion->referredClinic?->city ?? '—',
                'plan_name'       => $conversion->plan?->name ?? '—',
                'status'          => $conversion->status,
                'status_label'    => $conversion->statusLabel(),
                'reward_amount'   => $conversion->reward_amount,
                'days_remaining'  => $conversion->daysRemaining(),
                'eligible_at'     => $conversion->expectedEligibleAt()?->toISOString(),
                'trial_started'   => $conversion->trial_started_at?->toISOString(),
                'plan_subscribed' => $conversion->plan_subscribed_at?->toISOString(),
                'payment_confirmed'=> $conversion->payment_confirmed_at?->toISOString(),
                'paid_at'         => $conversion->paid_at?->toISOString(),
                'cancelled_at'    => $conversion->cancelled_at?->toISOString(),
            ],
        ]);
    }

    // ── Página pública — rastrear clique e mostrar landing personalizada ──
    public function redirect(Request $request, string $code)
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $referral = \App\Models\Referral::where('is_active', true)
            ->where(function ($q) use ($code, $normalized) {
                $q->where('code', $code)
                  ->orWhereRaw("REPLACE(UPPER(code), '-', '') = ?", [$normalized]);
            })
            ->firstOrFail();

        $this->service->trackClick($referral, $request);

        // Grava o código canônico na sessão (usado no cadastro/onboarding) e também
        // num cookie de longa duração, para sobreviver ao fechamento do navegador
        // ou a uma sessão expirada antes de o visitante concluir o cadastro.
        session(['referral_code' => $referral->code]);
        Cookie::queue(Cookie::make('referral_code', $referral->code, 60 * 24 * 30));

        $referrerOwner = $referral->owner();
        $settings      = ReferralSettings::current();

        if ($referrerOwner instanceof Clinic) {
            $clinicOwnerUser = $referrerOwner->owner();
            $referrerInfo = [
                'clinic_name' => $referrerOwner->displayName(),
                'owner_name'  => $clinicOwnerUser?->name,
                'avatar_url'  => $clinicOwnerUser ? UserAvatarService::url($clinicOwnerUser) : null,
                'logo_url'    => $referrerOwner->logoUrl(),
            ];
        } else {
            // Afiliado puro — não há clínica nem logo, só o próprio nome.
            $referrerInfo = [
                'clinic_name' => null,
                'owner_name'  => $referrerOwner?->name,
                'avatar_url'  => $referrerOwner ? UserAvatarService::url($referrerOwner) : null,
                'logo_url'    => null,
            ];
        }

        return Inertia::render('Referrals/Landing', [
            'referrer' => $referrerInfo,
            'benefits' => [
                'trial_days'               => $settings->trial_days,
                'reward_amount'            => $settings->reward_amount,
                'referred_discount_amount' => $settings->referred_discount_amount,
            ],
            'registerUrl' => route('register'),
        ]);
    }
}
