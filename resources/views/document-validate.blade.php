<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validação de Documento — ClinicFlow</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc; color: #1e293b; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;
        }
        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 2.5rem 2rem; max-width: 480px; width: 100%;
            box-shadow: 0 4px 24px rgba(15,23,42,0.06);
        }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 1.5rem; }
        .badge-valid { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-invalid { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-icon { width: 18px; height: 18px; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 2rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table tr { border-bottom: 1px solid #f1f5f9; }
        .table tr:last-child { border-bottom: none; }
        .table td { padding: 10px 0; font-size: 13px; vertical-align: top; }
        .table td:first-child { color: #94a3b8; width: 160px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding-right: 12px; }
        .table td:last-child { color: #1e293b; font-weight: 500; }
        .hash { margin-top: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        .hash-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 4px; }
        .hash-value { font-family: 'Courier New', monospace; font-size: 11px; color: #475569; word-break: break-all; }
        .footer { margin-top: 1.5rem; text-align: center; font-size: 11px; color: #cbd5e1; }
        .footer strong { color: #94a3b8; }
        .invalid-msg { color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 1rem; }
        .sig-section { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; }
        .sig-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 10px; }
        .sig-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; }
        .badge-signed { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-unsigned { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        @if($valid)
            <div class="badge badge-valid">
                <svg class="badge-icon" fill="none" viewBox="0 0 24 24" stroke="#15803d" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Documento Válido
            </div>
            <h1>{{ $document_title }}</h1>
            <p class="subtitle">Este documento foi verificado e é autêntico.</p>

            <table class="table">
                <tr><td>Paciente</td><td>{{ $patient_name }}</td></tr>
                <tr><td>Clínica</td><td>{{ $clinic_name }}</td></tr>
                <tr><td>Código</td><td>{{ $document_code }}</td></tr>
                <tr><td>Gerado em</td><td>{{ $created_at }}</td></tr>
                <tr><td>Status</td><td>{{ $status_label }}</td></tr>
            </table>

            <div class="hash">
                <div class="hash-label">Hash de verificação</div>
                <div class="hash-value">{{ $document_hash }}</div>
            </div>

            @if(count($signers) > 0)
            <div class="sig-section">
                <div class="sig-section-label">Assinaturas</div>
                @foreach($signers as $s)
                <div class="sig-row">
                    <div>
                        <div style="font-size:13px;font-weight:500;">{{ $s['role_label'] }}</div>
                        @if($s['signed'])
                        <div style="font-size:11px;color:#64748b;">{{ $s['signer_name'] }} — {{ $s['signed_at'] }}</div>
                        @endif
                    </div>
                    <span class="{{ $s['signed'] ? 'badge-signed' : 'badge-unsigned' }}">
                        {{ $s['signed'] ? '✓ Assinado' : '⌛ Pendente' }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        @else
            <div class="badge badge-invalid">
                <svg class="badge-icon" fill="none" viewBox="0 0 24 24" stroke="#b91c1c" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                Documento Não Encontrado
            </div>
            <h1>Não foi possível validar</h1>
            <p class="invalid-msg">O link de validação é inválido, expirou ou o documento foi removido do sistema.</p>
        @endif

        <p class="footer">Verificado por <strong>ClinicFlow</strong> — Sistema de Gestão para Clínicas Odontológicas</p>
    </div>
</body>
</html>
