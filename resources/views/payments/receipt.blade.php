<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Pagamento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Arial, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.5; padding: 32px; max-width: 480px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #0d9488; }
        .clinic-name { font-size: 18px; font-weight: bold; color: #0f766e; }
        .title { font-size: 15px; font-weight: bold; margin: 20px 0 12px; color: #0f172a; text-align: center; }
        .section { margin-bottom: 16px; }
        .row { display: table; width: 100%; margin-bottom: 6px; }
        .label { display: table-cell; width: 160px; font-weight: bold; color: #475569; }
        .value { display: table-cell; color: #1e293b; }
        .amount { font-size: 20px; font-weight: bold; color: #0f766e; text-align: center; margin: 20px 0; padding: 16px; background: #f0fdfa; border-radius: 8px; }
        .disclaimer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-name">{{ $clinic->trade_name ?? $clinic->name }}</div>
    </div>

    <div class="title">Comprovante de Pagamento</div>

    <div class="section">
        <div class="row">
            <span class="label">Paciente:</span>
            <span class="value">{{ $patient->nome }} {{ $patient->sobrenome }}</span>
        </div>
        <div class="row">
            <span class="label">Tratamento:</span>
            <span class="value">{{ $payment->treatment?->procedure_name }}</span>
        </div>
        <div class="row">
            <span class="label">Parcela:</span>
            <span class="value">{{ $payment->installment_number }}/{{ $payment->installment_total }}</span>
        </div>
        <div class="row">
            <span class="label">Forma de pagamento:</span>
            <span class="value">{{ \App\Models\PatientPayment::METHODS[$payment->payment_method] ?? '—' }}</span>
        </div>
        <div class="row">
            <span class="label">Data do recebimento:</span>
            <span class="value">{{ $payment->paid_at?->format('d/m/Y H:i') ?? 'Recebimento parcial em andamento' }}</span>
        </div>
        <div class="row">
            <span class="label">Status:</span>
            <span class="value">{{ \App\Models\PatientPayment::STATUSES[$payment->status] ?? $payment->status }}</span>
        </div>
    </div>

    <div class="amount">
        Valor recebido: R$ {{ number_format($payment->amount_paid, 2, ',', '.') }}
        @if ($payment->status === \App\Models\PatientPayment::STATUS_PARCIAL)
            <div style="font-size: 12px; font-weight: normal; color: #64748b; margin-top: 4px;">
                Saldo devedor: R$ {{ number_format($payment->remaining(), 2, ',', '.') }}
            </div>
        @endif
    </div>

    <div class="disclaimer">
        Este comprovante não possui valor fiscal. Emitido pelo CliniFlow em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
