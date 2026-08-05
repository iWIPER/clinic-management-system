<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assinatura de Documento — ClinicFlow</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc; color: #1e293b; min-height: 100vh; padding: 2rem 1rem;
        }
        .wrap { max-width: 640px; margin: 0 auto; }
        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 2rem; box-shadow: 0 4px 24px rgba(15,23,42,0.06); margin-bottom: 1.25rem;
        }
        h1 { font-size: 19px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 1.25rem; }
        .doc-content { font-size: 13.5px; line-height: 1.7; color: #334155; max-height: 420px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px; padding: 16px 18px; background: #fbfcfd; }
        .doc-content h1, .doc-content h2, .doc-content h3 { margin: 10px 0 6px; color: #0f172a; }
        .doc-content p { margin-bottom: 8px; }
        .role-select { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1rem; }
        .role-btn { border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; transition: all .15s; }
        .role-btn.active { background: #0f766e; border-color: #0f766e; color: #fff; }
        label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; margin-bottom: 5px; margin-top: 14px; }
        label:first-child { margin-top: 0; }
        input[type=text], input[type=email] {
            width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 14px; color: #1e293b; outline: none;
        }
        input:focus { border-color: #2dd4bf; box-shadow: 0 0 0 3px rgba(45,212,191,.15); }
        .pad-wrap { position: relative; border: 2px dashed #cbd5e1; border-radius: 12px; overflow: hidden; margin-top: 14px; background: #fff; touch-action: none; }
        canvas { display: block; width: 100%; cursor: crosshair; touch-action: none; }
        .pad-clear { position: absolute; bottom: 8px; right: 8px; font-size: 11px; color: #94a3b8; background: rgba(255,255,255,.85); border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px 10px; cursor: pointer; }
        .pad-hint { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); color: #cbd5e1; font-size: 13px; pointer-events: none; }
        .btn-submit {
            width: 100%; margin-top: 18px; background: #0f766e; color: #fff; border: none; border-radius: 12px;
            padding: 13px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .15s;
        }
        .btn-submit:hover { background: #0e6b63; }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; }
        .error-msg { color: #dc2626; font-size: 12px; font-weight: 600; margin-top: 10px; }
        .success-box { text-align: center; padding: 2rem 1rem; }
        .success-icon { width: 56px; height: 56px; border-radius: 50%; background: #f0fdf4; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
        .invalid-msg { color: #64748b; font-size: 14px; line-height: 1.6; }
        .footer-note { text-align: center; font-size: 11px; color: #cbd5e1; }
    </style>
</head>
<body>
<div class="wrap">

@if(!$valid)
    <div class="card" style="text-align:center;">
        <div class="success-icon" style="background:#fef2f2;">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#b91c1c" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        </div>
        <h1>Link inválido ou expirado</h1>
        <p class="invalid-msg" style="margin-top:8px;">Este link de assinatura não é mais válido. Solicite um novo link à clínica.</p>
    </div>
@else
    <div class="card">
        <h1>{{ $document->template_name }}</h1>
        <p class="subtitle">{{ $document->clinic?->displayName() }} — assinatura eletrônica solicitada para {{ $document->patient?->nome_completo }}</p>
        <div class="doc-content">{!! $contentHtml !!}</div>
    </div>

    <div class="card" id="sign-card">
        @if(empty($pendingRoles))
            <div class="success-box">
                <div class="success-icon">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#15803d" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h1>Documento já assinado</h1>
                <p class="invalid-msg" style="margin-top:6px;">Não há mais pendências para este link.</p>
            </div>
        @else
            <h1 style="font-size:16px;">Assinar documento</h1>
            <p class="subtitle">Preencha seus dados e assine no campo abaixo.</p>

            @if(count($pendingRoles) > 1)
                <label>Assinando como</label>
                <div class="role-select" id="role-select">
                    @foreach($pendingRoles as $r)
                        <button type="button" class="role-btn" data-role="{{ $r }}">{{ ['patient' => 'Paciente', 'responsible' => 'Responsável', 'witness' => 'Testemunha'][$r] ?? ucfirst($r) }}</button>
                    @endforeach
                </div>
            @endif

            <label>Nome completo</label>
            <input type="text" id="signer_name" placeholder="Seu nome completo" value="{{ $document->patient?->nome_completo }}">

            <label>CPF (opcional)</label>
            <input type="text" id="signer_cpf" placeholder="000.000.000-00">

            <label>E-mail (opcional)</label>
            <input type="email" id="signer_email" placeholder="email@exemplo.com">

            <label>Assinatura</label>
            <div class="pad-wrap">
                <canvas id="pad" width="560" height="180"></canvas>
                <span class="pad-hint" id="pad-hint">Assine aqui</span>
                <button type="button" class="pad-clear" id="pad-clear">Limpar</button>
            </div>

            <button type="button" class="btn-submit" id="submit-btn">Confirmar assinatura</button>
            <p class="error-msg" id="error-msg" style="display:none;"></p>
        @endif
    </div>
@endif

<p class="footer-note">Assinatura processada por <strong>ClinicFlow</strong> — Sistema de Gestão para Clínicas Odontológicas</p>
</div>

@if($valid && !empty($pendingRoles))
<script>
(function () {
    var pendingRoles = @json($pendingRoles);
    var selectedRole = pendingRoles[0];
    var token = @json($token);

    var roleButtons = document.querySelectorAll('.role-btn');
    if (roleButtons.length) {
        roleButtons[0].classList.add('active');
        roleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedRole = btn.dataset.role;
                roleButtons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
    }

    var canvas = document.getElementById('pad');
    var ctx = canvas.getContext('2d');
    var hint = document.getElementById('pad-hint');
    var drawing = false, lastX = 0, lastY = 0, empty = true;

    function resizeCanvas() {
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = 180 * ratio;
        ctx.scale(ratio, ratio);
    }
    resizeCanvas();

    function getPos(e) {
        var rect = canvas.getBoundingClientRect();
        var clientX = e.touches ? e.touches[0].clientX : e.clientX;
        var clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return [clientX - rect.left, clientY - rect.top];
    }

    function start(e) {
        e.preventDefault();
        drawing = true;
        var pos = getPos(e);
        lastX = pos[0]; lastY = pos[1];
    }
    function move(e) {
        if (!drawing) return;
        e.preventDefault();
        var pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos[0], pos[1]);
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        lastX = pos[0]; lastY = pos[1];
        if (empty) { empty = false; hint.style.display = 'none'; }
    }
    function end() { drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    document.getElementById('pad-clear').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        empty = true;
        hint.style.display = 'block';
    });

    function getGeolocation() {
        return new Promise(function (resolve) {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }); },
                function () { resolve(null); },
                { timeout: 3000 }
            );
        });
    }

    var submitBtn = document.getElementById('submit-btn');
    var errorMsg = document.getElementById('error-msg');

    submitBtn.addEventListener('click', function () {
        var name = document.getElementById('signer_name').value.trim();
        errorMsg.style.display = 'none';

        if (!name) {
            errorMsg.textContent = 'Informe o nome completo.';
            errorMsg.style.display = 'block';
            return;
        }
        if (empty) {
            errorMsg.textContent = 'Realize a assinatura no campo acima.';
            errorMsg.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando…';

        getGeolocation().then(function (geolocation) {
            var payload = {
                signer_role: selectedRole,
                signer_name: name,
                signer_cpf: document.getElementById('signer_cpf').value.trim() || null,
                signer_email: document.getElementById('signer_email').value.trim() || null,
                signature_data: canvas.toDataURL('image/png'),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                browser_info: {
                    browser: navigator.userAgent,
                    platform: navigator.platform,
                    language: navigator.language,
                    screen_width: window.screen.width,
                    screen_height: window.screen.height,
                    device_pixel_ratio: window.devicePixelRatio || 1,
                },
                geolocation: geolocation,
            };

            fetch('/documentos/assinar/' + token, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            }).then(function (result) {
                if (!result.ok) {
                    errorMsg.textContent = result.data.error || 'Erro ao processar assinatura.';
                    errorMsg.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Confirmar assinatura';
                    return;
                }
                document.getElementById('sign-card').innerHTML =
                    '<div class="success-box"><div class="success-icon"><svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#15803d" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>' +
                    '<h1>Assinatura registrada!</h1><p class="invalid-msg" style="margin-top:6px;">Obrigado. Você já pode fechar esta página.</p></div>';
            }).catch(function () {
                errorMsg.textContent = 'Erro de conexão. Tente novamente.';
                errorMsg.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirmar assinatura';
            });
        });
    });
})();
</script>
@endif
</body>
</html>
