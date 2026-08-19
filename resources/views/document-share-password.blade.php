<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Documento compartilhado — Wildental</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc; color: #1e293b; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;
        }
        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 2.5rem 2rem; max-width: 440px; width: 100%;
            box-shadow: 0 4px 24px rgba(15,23,42,0.06);
        }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 1.5rem; }
        .badge-valid { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-invalid { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 1.75rem; line-height: 1.6; }
        label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; margin-top: 14px; }
        label:first-of-type { margin-top: 0; }
        input[type=text] {
            width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 14px; color: #1e293b; background: #f8fafc;
        }
        input[type=text]:focus { outline: none; border-color: #0f766e; background: #fff; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 13px 20px; border-radius: 10px; border: none;
            font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 20px;
            background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%); color: #fff;
        }
        .btn:hover { opacity: .93; }
        .btn-secondary { background: #f1f5f9; color: #334155; margin-top: 10px; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 13px; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .doc-name { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; font-size: 14px; font-weight: 600; color: #1e293b; }
        .password-box { background: #f0fdfa; border: 1px dashed #0f766e; border-radius: 10px; padding: 18px; text-align: center; margin: 18px 0; }
        .password-value { font-family: 'Courier New', monospace; font-size: 24px; font-weight: 700; letter-spacing: 3px; color: #0f766e; user-select: all; }
        .channels { display: flex; gap: 8px; margin-top: 8px; }
        .channel-btn { flex: 1; padding: 9px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; font-size: 12px; font-weight: 600; color: #475569; cursor: pointer; }
        .channel-btn:hover { background: #f8fafc; }
        .toast { margin-top: 10px; font-size: 12px; text-align: center; color: #15803d; min-height: 16px; }
        .footer { margin-top: 1.5rem; text-align: center; font-size: 11px; color: #cbd5e1; }
        .footer strong { color: #94a3b8; }
        .invalid-msg { color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        @if(! $valid)
            <div class="badge badge-invalid">🔒 Link indisponível</div>
            <h1>Não foi possível abrir</h1>
            <p class="invalid-msg">
                @if(($reason ?? null) === 'expired') Este link de compartilhamento expirou.
                @elseif(($reason ?? null) === 'revoked') Este compartilhamento foi revogado pela clínica.
                @elseif(($reason ?? null) === 'locked') Muitas tentativas de verificação de identidade. Tente novamente mais tarde.
                @else Link inválido ou o documento não existe mais.
                @endif
            </p>
        @elseif(! $verified)
            <div class="badge badge-valid">🔑 Documento compartilhado</div>
            <h1>Confirme sua identidade</h1>
            <p class="subtitle">Para ver a senha do documento, confirme seu nome (pode ser parcial) e CPF cadastrados na clínica.</p>

            @if ($errors->any())
                <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('documents.shared.password.verify', $token) }}">
                @csrf
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" placeholder="Ex.: Maria Luiza" required value="{{ old('name') }}">

                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required value="{{ old('cpf') }}">

                <button type="submit" class="btn">Confirmar identidade</button>
            </form>
        @else
            <div class="badge badge-valid">✓ Identidade confirmada</div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">Enviado por <strong>{{ $clinicName }}</strong></p>

            <div class="doc-name">📄 {{ $filename }}</div>

            <a href="{{ route('documents.shared.view', $token) }}" target="_blank" class="btn" style="text-decoration:none;">Visualizar documento</a>

            <p style="font-size:12px;color:#94a3b8;margin-top:18px;">Senha do arquivo (para abrir o PDF baixado ou anexado por e-mail):</p>
            <div class="password-box">
                <span class="password-value" id="password-value">{{ $password }}</span>
            </div>
            <button type="button" class="btn btn-secondary" onclick="copyPassword()">Copiar senha</button>

            <p style="font-size:12px;color:#64748b;margin-top:20px;margin-bottom:4px;font-weight:600;">Enviar senha por</p>
            <div class="channels">
                <button type="button" class="channel-btn" onclick="sendPassword('email')">E-mail</button>
                <button type="button" class="channel-btn" onclick="sendPassword('whatsapp')">WhatsApp</button>
                <button type="button" class="channel-btn" onclick="sendPassword('sms')">SMS</button>
            </div>
            <div class="toast" id="toast"></div>
        @endif

        <p class="footer">Compartilhado com segurança via <strong>Wildental</strong></p>
    </div>

    @if($valid && $verified)
    <script>
        const token = @json($token);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function copyPassword() {
            const value = document.getElementById('password-value').textContent;
            navigator.clipboard.writeText(value).then(() => showToast('Senha copiada.'));
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            setTimeout(() => { toast.textContent = ''; }, 4000);
        }

        function sendPassword(channel) {
            fetch('/documentos/compartilhados/' + token + '/enviar-senha', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ channel }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'sent' || data.status === 'queued') {
                        showToast('Senha enviada por ' + channel + '.');
                    } else if (data.status === 'not_configured') {
                        navigator.clipboard.writeText(data.message).then(() => {
                            showToast('Canal ainda não conectado — mensagem copiada para você enviar manualmente.');
                        });
                    }
                })
                .catch(() => showToast('Não foi possível enviar. Tente novamente.'));
        }
    </script>
    @endif
</body>
</html>
