<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Atendimento #{{ $record->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #059669; }
        .logo { max-height: 60px; max-width: 180px; margin-bottom: 8px; }
        .clinic-name { font-size: 20px; font-weight: bold; color: #047857; }
        .slogan { font-size: 11px; color: #64748b; margin-top: 4px; }
        .title { font-size: 16px; font-weight: bold; margin: 20px 0 12px; color: #0f172a; }
        .section { margin-bottom: 16px; }
        .row { display: table; width: 100%; margin-bottom: 6px; }
        .label { display: table-cell; width: 140px; font-weight: bold; color: #475569; }
        .value { display: table-cell; color: #1e293b; }
        .notes { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-top: 8px; white-space: pre-wrap; }
        .price { font-size: 18px; font-weight: bold; color: #047857; margin-top: 12px; }
        .footer { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
        @endif
        <div class="clinic-name">{{ $clinic->trade_name ?? $clinic->name }}</div>
        @if($clinic->slogan)
            <div class="slogan">{{ $clinic->slogan }}</div>
        @endif
    </div>

    <div class="title">Comprovante de Atendimento</div>

    <div class="section">
        <div class="row">
            <span class="label">Paciente:</span>
            <span class="value">{{ $record->patient->nome }} {{ $record->patient->sobrenome }}</span>
        </div>
        <div class="row">
            <span class="label">Profissional:</span>
            <span class="value">{{ $record->professional->name }}</span>
        </div>
        <div class="row">
            <span class="label">Procedimento:</span>
            <span class="value">{{ $record->procedure_name }}</span>
        </div>
        @if($record->procedure_category)
        <div class="row">
            <span class="label">Categoria:</span>
            <span class="value">{{ $record->procedure_category }}</span>
        </div>
        @endif
        <div class="row">
            <span class="label">Data:</span>
            <span class="value">{{ $record->finished_at?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="row">
            <span class="label">Horário:</span>
            <span class="value">
                @if($record->started_at && $record->finished_at)
                    {{ $record->started_at->format('H:i') }} – {{ $record->finished_at->format('H:i') }}
                @else
                    —
                @endif
            </span>
        </div>
        @if($record->duration_minutes)
        <div class="row">
            <span class="label">Duração:</span>
            <span class="value">{{ $record->duration_minutes }} minutos</span>
        </div>
        @endif
    </div>

    <div class="price">Valor: R$ {{ number_format($record->price, 2, ',', '.') }}</div>

    @if($record->notes)
    <div class="section" style="margin-top: 20px;">
        <strong>Observações:</strong>
        <div class="notes">{{ $record->notes }}</div>
    </div>
    @endif

    <div class="footer">Emitido pelo CliniFlow</div>
</body>
</html>