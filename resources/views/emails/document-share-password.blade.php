<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Senha do arquivo {{ $share->friendly_filename }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .email-wrapper { max-width: 480px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%); padding: 28px 32px; text-align: center; }
        .brand-name { font-size: 18px; font-weight: 800; color: #ffffff; }
        .brand-name span { color: #99f6e4; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 14px; color: #334155; line-height: 1.6; margin-bottom: 20px; white-space: pre-line; }
        .footer { padding: 18px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
        .footer-text { font-size: 11px; color: #94a3b8; line-height: 1.6; }
    </style>
</head>
<body>
<div class="email-wrapper">
<div class="card">

    <div class="header">
        @if($clinicLogo)
        <img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" style="max-height:40px;max-width:150px;object-fit:contain;border-radius:6px;" />
        @else
        <div class="brand-name">Wil<span>Dental</span></div>
        @endif
    </div>

    <div class="body">
        <p class="greeting">{{ $composedMessage }}</p>
        <p style="font-size:12px;color:#94a3b8;line-height:1.6;">
            Use esta senha para abrir o arquivo PDF <strong>{{ $share->friendly_filename }}</strong>.
        </p>
    </div>

    <div class="footer">
        <p class="footer-text">Enviado por {{ $clinicName }} via WilDental — não responda a este e-mail.</p>
    </div>

</div>
</div>
</body>
</html>
