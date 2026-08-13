<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $document->template_name }} &mdash; {{ $patient->nome_completo }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

@page { size: A4; margin: 15mm 16mm 22mm 16mm; }

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9px;
    line-height: 1.6;
    color: #1a202c;
    background: white;
}

.logo   { max-height: 36px; max-width: 110px; display: block; }
.c-name { font-size: 8.5px; color: #4a5568; text-align: center; margin-bottom: 1px; }
.c-sub  { font-size: 6.5px; color: #a0aec0; text-align: center; margin-top: 2px; }
.ttl    { font-size: 13px; font-weight: bold; text-align: center; color: #1a202c; letter-spacing: 0.03em; margin-top: 1px; }
.hdr-date { font-size: 7.5px; color: #c0392b; font-weight: bold; text-align: right; }

.rule { border-top: 1.5px solid #2d3748; margin: 7px 0 8px; }

.f-lbl { font-size: 7px; font-weight: bold; color: #2d3748; }
.f-val { font-size: 7.5px; color: #4a5568; }

.doc-body { font-size: 9px; line-height: 1.7; color: #1a202c; }
.doc-body p { margin-bottom: 6px; }
.doc-body h1, .doc-body h2, .doc-body h3 { color: #1a202c; margin: 10px 0 6px; }
.doc-body h1 { font-size: 12px; }
.doc-body h2 { font-size: 11px; }
.doc-body h3 { font-size: 10px; }
.doc-body ul, .doc-body ol { margin: 4px 0 8px 16px; }
.doc-body strong { color: #0f172a; }

.sig-decl { font-size: 7.5px; color: #4a5568; text-align: center; font-style: italic; margin: 16px 0 12px; }

.foot {
    position: fixed; bottom: 0; left: 0; right: 0;
    border-top: 1px solid #e2e8f0; padding: 4px 16mm;
    background: white;
}
.foot-brand { color: #2c7a7b; font-weight: bold; font-size: 7px; }
.foot-mono  { font-family: DejaVu Sans Mono, monospace; font-size: 5.5px; color: #cbd5e0; }
</style>
</head>
<body>

@php
    $cliSubParts = array_filter([
        $clinic?->fullAddress(),
        $clinic?->phone,
        $clinic?->email,
    ]);
    $cliSub = implode(' · ', $cliSubParts);
@endphp

{{-- ════════════ CABEÇALHO ════════════ --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:6px;">
  <tr>
    <td style="width:15%; vertical-align:middle;">
      @if($logoDataUri)
        <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div class="c-name">{{ $clinic?->displayName() }}</div>
      <div class="ttl">{{ strtoupper($document->template_name) }}</div>
      @if($cliSub)
        <div class="c-sub">{{ $cliSub }}</div>
      @endif
    </td>
    <td style="width:15%; vertical-align:top; text-align:right;">
      <div class="hdr-date">{{ ($document->issued_at ?? $document->created_at)->format('d/m/Y') }}</div>
      @if($qrDataUri)
        <img src="{{ $qrDataUri }}" style="width:44px; height:44px; margin-top:3px;" alt="QR">
        <div style="font-size:5.5px; color:#a0aec0; text-align:center; margin-top:1px; line-height:1.4;">Validar<br>documento</div>
      @endif
    </td>
  </tr>
</table>

<div class="rule"></div>

{{-- ════════════ DADOS DO PACIENTE ════════════ --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:6px;">
  <tr>
    <td style="width:50%; vertical-align:top; padding-right:14px;">
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td style="padding:2px 0; width:38%; vertical-align:top;"><div class="f-lbl">Paciente:</div></td>
          <td style="padding:2px 0; vertical-align:top;"><div class="f-val">{{ $patient->nome_completo }}</div></td>
        </tr>
        @if(!empty($patient->doc_numero))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">{{ strtoupper($patient->doc_tipo ?? 'Doc.') }}:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $patient->doc_numero }}</div></td>
        </tr>
        @endif
      </table>
    </td>
    <td style="width:50%; vertical-align:top; border-left:1px solid #e2e8f0; padding-left:14px;">
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td style="padding:2px 0; width:48%; vertical-align:top;"><div class="f-lbl">Código:</div></td>
          <td style="padding:2px 0; vertical-align:top;"><div class="f-val">{{ $document->document_code }}</div></td>
        </tr>
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Versão:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">v{{ $document->templateVersion?->version }}</div></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="rule"></div>

{{-- ════════════ CONTEÚDO ════════════ --}}
<div class="doc-body">
    {!! $contentHtml !!}
</div>

{{-- ════════════ FOOTER FIXO ════════════ --}}
<div class="foot">
  <table width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td style="font-size:6px; color:#a0aec0; vertical-align:middle;">
        {{ $clinic?->displayName() }}
        @if($clinic?->cnpj) &nbsp;&middot;&nbsp; CNPJ {{ $clinic->cnpj }} @endif
        @if($clinic?->website) &nbsp;&middot;&nbsp; {{ $clinic->website }} @endif
        <br>
        Doc. {{ $document->document_code }}
        &nbsp;&middot;&nbsp; Emitido em {{ ($document->issued_at ?? $document->created_at)->format('d/m/Y H:i') }}
        @if($document->content_hash)
          <br><span class="foot-mono">{{ $document->content_hash }}</span>
        @endif
      </td>
      <td style="text-align:center; font-size:6px; color:#a0aec0; vertical-align:middle;">
        <span class="foot-brand">Wildental</span>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
