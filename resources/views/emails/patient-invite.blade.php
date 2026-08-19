<!DOCTYPE html>
<html lang="pt-BR" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>{{ $isUpdate ? 'Atualize seu cadastro' : 'Complete seu cadastro' }} — {{ $clinicName }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #059669 0%, #0d9488 100%); padding: 40px 32px; text-align: center; }
        .header-logo { margin-bottom: 20px; }
        .header-logo img { max-height: 56px; max-width: 180px; object-fit: contain; border-radius: 8px; }
        .brand-row { display: inline-flex; align-items: center; gap: 8px; }
        .brand-icon { width: 40px; height: 40px; background: rgba(255,255,255,.15); border-radius: 10px; display: inline-block; text-align: center; line-height: 40px; }
        .brand-name { font-size: 22px; font-weight: 800; color: #ffffff; }
        .brand-name span { color: #6ee7b7; }
        .header-badge { display: inline-block; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); color: #a7f3d0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 12px; border-radius: 999px; margin-top: 16px; }
        .header-title { color: #ffffff; font-size: 24px; font-weight: 700; margin-top: 12px; line-height: 1.35; }
        .header-sub { color: #a7f3d0; font-size: 14px; margin-top: 6px; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #334155; line-height: 1.7; margin-bottom: 24px; }
        .greeting strong { color: #1e293b; }
        .cta-section { text-align: center; margin-bottom: 28px; }
        .cta-btn { display: inline-block; background: linear-gradient(135deg, #059669 0%, #0d9488 100%); color: #ffffff !important; text-decoration: none; font-size: 16px; font-weight: 700; padding: 16px 40px; border-radius: 12px; letter-spacing: 0.2px; }
        .cta-link-text { font-size: 12px; color: #64748b; margin-top: 12px; }
        .cta-link-text a { color: #059669; word-break: break-all; }
        .expiry-box { background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; }
        .expiry-text { font-size: 13px; color: #78350f; }
        .expiry-text strong { color: #92400e; }
        .steps-title { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; }
        .step-row { display: table; width: 100%; margin-bottom: 12px; }
        .step-num { display: table-cell; width: 30px; vertical-align: top; }
        .step-num-badge { width: 24px; height: 24px; background: #059669; color: #fff; border-radius: 50%; font-size: 12px; font-weight: 700; text-align: center; line-height: 24px; }
        .step-text { display: table-cell; font-size: 14px; color: #475569; padding-top: 2px; line-height: 1.5; padding-left: 8px; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
        .footer { padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
        .footer-brand { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .footer-brand span { color: #059669; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.7; }
        .footer-text a { color: #059669; text-decoration: none; }
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
        <div class="header-badge">{{ $isUpdate ? 'Atualização cadastral' : 'Convite de cadastro' }}</div>
        <div class="header-title">
            {{ $isUpdate ? 'Atualize seus dados na' : 'Complete seu cadastro na' }}<br/>{{ $clinicName }}
        </div>
        <div class="header-sub">Leva só alguns minutos 🙂</div>
    </div>

    <!-- BODY -->
    <div class="body">

        <p class="greeting">
            Olá, <strong>{{ $patient->nome }}</strong>!<br/><br/>
            @if($isUpdate)
                A equipe de <strong>{{ $clinicName }}</strong> pede para você revisar e atualizar seus
                dados cadastrais. É rápido e pode ser feito direto pelo celular.
            @else
                A equipe de <strong>{{ $clinicName }}</strong> preparou um link para você mesmo(a)
                preencher seu cadastro, sem precisar fazer isso presencialmente.
            @endif
        </p>

        <!-- CTA -->
        <div class="cta-section">
            <a href="{{ $url }}" class="cta-btn" target="_blank" rel="noopener">
                &#10003;&nbsp; {{ $isUpdate ? 'Atualizar meus dados' : 'Completar meu cadastro' }}
            </a>
            <div class="cta-link-text">
                Ou copie este link no navegador:<br/>
                <a href="{{ $url }}">{{ $url }}</a>
            </div>
        </div>

        <!-- Expiração -->
        @if($expiresAt)
        <div class="expiry-box">
            <p class="expiry-text">
                ⏰ &nbsp;Este link expira em <strong>{{ $expiresAt }}</strong>. Após essa data, entre em
                contato com a clínica para solicitar um novo.
            </p>
        </div>
        @endif

        <!-- Passos -->
        <div class="steps-title">Como funciona</div>

        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">1</div></div>
            <div class="step-text">Clique no botão acima ou acesse o link no navegador do seu celular.</div>
        </div>
        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">2</div></div>
            <div class="step-text">Preencha seus dados — tudo é salvo automaticamente, então você pode fechar e continuar depois de onde parou.</div>
        </div>
        <div class="step-row">
            <div class="step-num"><div class="step-num-badge">3</div></div>
            <div class="step-text">Pronto! A clínica recebe suas informações automaticamente assim que você concluir.</div>
        </div>

        <hr class="divider" />

        <p style="font-size:12px; color:#94a3b8; text-align:center; line-height:1.7;">
            Se você não esperava este e-mail, pode ignorá-lo com segurança.<br/>
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
