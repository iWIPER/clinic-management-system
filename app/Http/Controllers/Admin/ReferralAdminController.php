<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Invite;
use App\Models\ReferralConversion;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Fase System Admin/Backoffice — extraído de Admin\DashboardController
// (RC-16). Comportamento preservado byte-a-byte.
class ReferralAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response
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

    public function refund(Request $request, ReferralConversion $conversion, ReferralService $referralService): \Illuminate\Http\JsonResponse
    {
        $reason = $request->input('reason', '');
        $referralService->markRefunded($conversion, $reason);

        AccessLog::record(
            action: 'admin_referral_refunded',
            description: "Indicação #{$conversion->id} marcada como estornada pelo administrador da plataforma",
            metadata: ['conversion_id' => $conversion->id, 'reason' => $reason],
        );

        return response()->json(['ok' => true]);
    }

    public function review(Request $request, ReferralConversion $conversion, ReferralService $referralService): \Illuminate\Http\JsonResponse
    {
        $reason = $request->input('reason', '');
        $referralService->markUnderReview($conversion, $reason);

        AccessLog::record(
            action: 'admin_referral_under_review',
            description: "Indicação #{$conversion->id} colocada em revisão pelo administrador da plataforma",
            metadata: ['conversion_id' => $conversion->id, 'reason' => $reason],
        );

        return response()->json(['ok' => true]);
    }

    public function inviteAffiliate(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $invite = Invite::create([
            'type'          => 'affiliate',
            'clinic_id'     => null,
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'invited_by_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        AccessLog::record(
            action: 'admin_affiliate_invited',
            description: "Convite de afiliado criado para {$invite->name} ({$invite->email})",
            metadata: ['invite_id' => $invite->id],
        );

        return response()->json([
            'invite_link' => config('app.url') . '/convites/' . $invite->token,
        ]);
    }
}
