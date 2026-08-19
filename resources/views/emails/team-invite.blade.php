<!DOCTYPE html>
<html lang="pt-BR" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Convite para {{ $clinicName }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #2563eb 0%, #4338ca 100%); padding: 40px 32px; text-align: center; }
        .header-logo { margin-bottom: 20px; }
        .header-logo img { max-height: 56px; max-width: 180px; object-fit: contain; border-radius: 8px; }
        .brand-row { display: inline-flex; align-items: center; gap: 8px; }
        .brand-icon { width: 40px; height: 40px; background: rgba(255,255,255,.15); border-radius: 10px; display: inline-block; text-align: center; line-height: 40px; }
        .brand-name { font-size: 22px; font-weight: 800; color: #ffffff; }
        .brand-name span { color: #93c5fd; }
        .header-badge { display: inline-block; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); color: #bfdbfe; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 12px; border-radius: 999px; margin-top: 16px; }
        .header-title { color: #ffffff; font-size: 24px; font-weight: 700; margin-top: 12px; line-height: 1.35; }
        .header-sub { color: #bfdbfe; font-size: 14px; margin-top: 6px; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #334155; line-height: 1.7; margin-bottom: 24px; }
        .greeting strong { color: #1e293b; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 4px 16px; margin-bottom: 24px; }
        .info-row { display: table; width: 100%; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { display: table-cell; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; width: 45%; vertical-align: middle; }
        .info-value { display: table-cell; font-size: 14px; color: #1e293b; font-weight: 500; text-align: right; vertical-align: middle; }
        .token-section { background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%); border: 2px solid #bfdbfe; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px; }
        .token-label { font-size: 11px; font-weight: 700; color: #3b82f6; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
        .token-code { font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 800; color: #1d4ed8; letter-spacing: 6px; }
        .token-hint { font-size: 12px; color: #64748b; margin-top: 8px; }
        .cta-section { text-align: center; margin-bottom: 28px; }
        .cta-btn { display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #4338ca 100%); color: #ffffff !important; text-decoration: none; font-size: 16px; font-weight: 700; padding: 16px 40px; border-radius: 12px; letter-spacing: 0.2px; }
        .cta-link-text { font-size: 12px; color: #64748b; margin-top: 12px; }
        .cta-link-text a { color: #3b82f6; word-break: break-all; }
        .expiry-box { background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; }
        .expiry-text { font-size: 13px; color: #78350f; }
        .expiry-text strong { color: #92400e; }
        .steps-title { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; }
        .step-row { display: table; width: 100%; margin-bottom: 12px; }
        .step-num { display: table-cell; width: 30px; vertical-align: top; }
        .step-num-badge { width: 24px; height: 24px; background: #2563eb; color: #fff; border-radius: 50%; font-size: 12px; font-weight: 700; text-align: center; line-height: 24px; }
        .step-text { display: table-cell; font-size: 14px; color: #475569; padding-top: 2px; line-height: 1.5; padding-left: 8px; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
        .footer { padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
        .footer-brand { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .footer-brand span { color: #3b82f6; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.7; }
        .footer-text a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="card">

    <!-- HEADER -->
    <div class="header">
        @if($clinicLogo)
        <div class="header-logo">
            <img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" />
        </div>
        @else
        <div class="brand-row">
            <div class="brand-icon">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#fff" style="vertical-align:middle;margin-top:9px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <span class="brand-name">Wil<span>Dental</span></span>
        </div>
        @endif
        <div class="header-badge">Convite de equipe</div>
        <div class="header-title">Você foi convidado para<br/>{{ $clinicName }}</div>
        <div class="header-sub">{{ $invitedBy }} está aguardando você 🎉</div>
    </div>

    <!-- BODY -->
    <div class="body">

        <p class="greeting">
            Olá, <strong>{{ $invite->name ?? $invite->email }}</strong>!<br/><br/>
            <strong>{{ $invitedBy }}</strong> convidou você para fazer parte da equipe de
            <strong>{{ $clinicName }}</strong> no Wildental.
        </p>

        <!-- Detalhes do convite -->
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">E-mail de acesso</span>
                <span class="info-value">{{ $invite->email }}</span>
            </div>
            @if($invite->job_title)
            <div class="info-row">
                <span class="info-label">Cargo</span>
                <span class="info-value">{{ $invite->job_title }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Clínica</span>
                <span class="info-value">{{ $clinicName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Convidado por</span>
                <span class="info-value">{{ $invitedBy }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Expira em</span>
                <span class="info-value">{{ $expiresAt }}</span>
            </div>
        </div>

        <!-- Código do convite -->
        <div class="token-section">
            <div class="token-label">Código do convite</div>
            <div class="token-code">{{ $invite->short_token }}</div>
            <div class="token-hint">Guarde este código como referência — para aceitar, clique no botão abaixo</div>
        </div>

        <!-- CTA -->
        <div class="cta-section">
            <a href="{{ $acceptUrl }}" class="cta-btn" target="_blank" rel="noopener">
                &#10003;&nbsp; Aceitar convite e entrar
            </a>
            <div class="cta-link-text">
                Ou copie este link no navegador:<br/>
                <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
            </div>
        </div>

        <!-- Expiração -->
        <div class="expiry-box">
            <p class="expiry-text">
                ⏰ &nbsp;Este convite expira em
                <strong>{{ $daysLeft }} dia{{ $daysLeft !== 1 ? 's' : '' }}</strong>
                ({{ $expiresAt }}). Após essa data, um novo convite precisará ser gerado.
            </p>
        </div>

        <!-- Passos -->
        <div class="steps-title">Como aceitar o convite</div>

        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">1</div></div>
            <div class="step-text">Clique em <strong>"Aceitar convite"</strong> acima ou acesse o link no navegador.</div>
        </div>
        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">2</div></div>
            <div class="step-text">Se já possui conta no Wildental, confirme com sua senha. Se é novo usuário, defina uma senha para criar sua conta.</div>
        </div>
        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">3</div></div>
            <div class="step-text">Pronto! Você terá acesso imediato à clínica <strong>{{ $clinicName }}</strong>.</div>
        </div>

        <hr class="divider" />

        <p style="font-size:12px; color:#94a3b8; text-align:center; line-height:1.7;">
            Se você não esperava este convite, pode ignorar este e-mail com segurança.<br/>
            Este link é de uso pessoal — não o compartilhe com outras pessoas.
        </p>

    </div>

    <!-- FOOTER -->
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
