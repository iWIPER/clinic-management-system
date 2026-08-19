<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $share->friendly_filename }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%); padding: 40px 32px; text-align: center; }
        .header-logo img { max-height: 56px; max-width: 180px; object-fit: contain; border-radius: 8px; margin-bottom: 20px; }
        .brand-name { font-size: 22px; font-weight: 800; color: #ffffff; }
        .brand-name span { color: #99f6e4; }
        .header-badge { display: inline-block; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); color: #ccfbf1; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 12px; border-radius: 999px; margin-top: 16px; }
        .header-title { color: #ffffff; font-size: 22px; font-weight: 700; margin-top: 12px; line-height: 1.35; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #334155; line-height: 1.7; margin-bottom: 24px; }
        .greeting strong { color: #1e293b; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 4px 16px; margin-bottom: 24px; }
        .info-row { display: table; width: 100%; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { display: table-cell; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; width: 45%; vertical-align: middle; }
        .info-value { display: table-cell; font-size: 14px; color: #1e293b; font-weight: 500; text-align: right; vertical-align: middle; }
        .cta-section { text-align: center; margin-bottom: 28px; }
        .cta-btn { display: inline-block; background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%); color: #ffffff !important; text-decoration: none; font-size: 16px; font-weight: 700; padding: 16px 40px; border-radius: 12px; }
        .cta-link-text { font-size: 12px; color: #64748b; margin-top: 12px; }
        .cta-link-text a { color: #0f766e; word-break: break-all; }
        .notice-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; }
        .notice-text { font-size: 13px; color: #1e3a8a; line-height: 1.6; }
        .expiry-box { background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px; margin-bottom: 8px; }
        .expiry-text { font-size: 13px; color: #78350f; }
        .expiry-text strong { color: #92400e; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
        .footer { padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
        .footer-brand { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .footer-brand span { color: #0f766e; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.7; }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="card">

    <div class="header">
        @if($clinicLogo)
        <div class="header-logo"><img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" /></div>
        @else
        <div class="brand-name">Wil<span>Dental</span></div>
        @endif
        <div class="header-badge">Documento compartilhado</div>
        <div class="header-title">Você recebeu um documento protegido</div>
    </div>

    <div class="body">
        <p class="greeting">
            Olá, <strong>{{ $share->recipient_name ?: $share->patient->nome_completo }}</strong>!<br/><br/>
            <strong>{{ $clinicName }}</strong> compartilhou o documento abaixo com você.
        </p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Arquivo</span>
                <span class="info-value">{{ $share->friendly_filename }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Clínica</span>
                <span class="info-value">{{ $clinicName }}</span>
            </div>
        </div>

        @if($hasAttachment)
        <div class="notice-box">
            <p class="notice-text">📎 &nbsp;O arquivo está anexado a este e-mail, protegido por senha.</p>
        </div>
        @else
        <div class="notice-box">
            <p class="notice-text">📄 &nbsp;O arquivo é grande para anexo — acesse-o pelo botão abaixo.</p>
        </div>
        @endif

        <div class="cta-section">
            <a href="{{ $revealUrl }}" class="cta-btn" target="_blank" rel="noopener">
                🔑&nbsp; Ver senha do arquivo
            </a>
            <div class="cta-link-text">
                Ou copie este link no navegador:<br/>
                <a href="{{ $revealUrl }}">{{ $revealUrl }}</a>
            </div>
        </div>

        <div class="expiry-box">
            <p class="expiry-text">
                ⏰ &nbsp;Este link expira em <strong>{{ $expiresAt }}</strong>.
            </p>
        </div>

        <hr class="divider" />

        <p style="font-size:12px; color:#94a3b8; text-align:center; line-height:1.7;">
            Para abrir o PDF você vai precisar da senha, disponível no link acima após confirmar sua identidade (nome e CPF).<br/>
            Se você não esperava este e-mail, pode ignorá-lo com segurança.
        </p>
    </div>

    <div class="footer">
        <div class="footer-brand">Wil<span>Dental</span></div>
        <div class="footer-text">
            Plataforma de gestão de clínicas<br/>
            <a href="{{ config('app.url') }}">{{ config('app.url') }}</a><br/><br/>
            E-mail enviado automaticamente — não responda a este endereço.
        </div>
    </div>

</div>
</div>
</body>
</html>
