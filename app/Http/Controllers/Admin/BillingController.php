<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\ReferralPayment;
use Illuminate\Http\Request;

// Fase System Admin/Backoffice — extraído de Admin\DashboardController
// (RC-16). Comportamento preservado byte-a-byte.
class BillingController extends Controller
{
    public function approvePayment(Request $request, ReferralPayment $payment): \Illuminate\Http\JsonResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pagamento não está pendente.');

        $payment->update([
            'status'       => 'paid',
            'processed_at' => now(),
            'processed_by' => \Illuminate\Support\Facades\Auth::id(),
            'notes'        => $request->input('notes'),
        ]);

        $wallet = $payment->wallet;
        $wallet->decrement('balance', $payment->amount);
        $wallet->increment('total_withdrawn', $payment->amount);
        $wallet->update(['last_payment_at' => now()]);

        AccessLog::record(
            action: 'admin_payment_approved',
            description: "Pagamento de R$ {$payment->amount} aprovado para {$wallet->clinic?->name}",
            metadata: ['payment_id' => $payment->id, 'amount' => $payment->amount],
        );

        AccessLog::record(
            action: 'referral_payment_sent',
            description: 'Seu pagamento foi enviado via PIX.',
            metadata: ['payment_id' => $payment->id, 'amount' => $payment->amount],
            clinicId: $wallet->clinic_id,
        );

        return response()->json(['ok' => true]);
    }

    public function rejectPayment(Request $request, ReferralPayment $payment): \Illuminate\Http\JsonResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Pagamento não está pendente.');

        $payment->update([
            'status'       => 'rejected',
            'processed_at' => now(),
            'processed_by' => \Illuminate\Support\Facades\Auth::id(),
            'notes'        => $request->input('notes', 'Reprovado pelo administrador.'),
        ]);

        AccessLog::record(
            action: 'admin_payment_rejected',
            description: "Pagamento de R$ {$payment->amount} recusado para {$payment->wallet?->clinic?->name}",
            metadata: ['payment_id' => $payment->id],
        );

        return response()->json(['ok' => true]);
    }
}
