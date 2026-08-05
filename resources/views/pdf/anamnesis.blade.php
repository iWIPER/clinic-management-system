<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Ficha de Anamnese &mdash; {{ $instance->patient->nome_completo }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

@page { size: A4; margin: 15mm 16mm 20mm 16mm; }

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8px;
    line-height: 1.5;
    color: #1a202c;
    background: white;
}

/* ════ CABEÇALHO ════ */
.logo   { max-height: 36px; max-width: 110px; display: block; }
.c-name { font-size: 8.5px; color: #4a5568; text-align: center; margin-bottom: 1px; }
.c-sub  { font-size: 6.5px; color: #a0aec0; text-align: center; margin-top: 2px; }
.ttl    { font-size: 14px; font-weight: bold; text-align: center; color: #1a202c; letter-spacing: 0.03em; margin-top: 1px; }
.hdr-date { font-size: 7.5px; color: #c0392b; font-weight: bold; text-align: right; }

/* ════ DIVISORES ════ */
.rule       { border-top: 1.5px solid #2d3748; margin: 7px 0 5px; }

/* ════ CAMPOS PACIENTE ════ */
.f-lbl  { font-size: 7px; font-weight: bold; color: #2d3748; }
.f-val  { font-size: 7.5px; color: #4a5568; }

/* ════ ALERTAS ════ */
.alrt-wrap  { border: 1px solid #fbd38d; border-left: 3px solid #f6ad55;
              background: #fffaf0; padding: 4px 8px; margin-bottom: 5px; }
.alrt-title { font-size: 6.5px; font-weight: bold; text-transform: uppercase;
              letter-spacing: 0.06em; color: #9c4221; margin-bottom: 2px; }
.alrt-item  { font-size: 7px; color: #7b341e; margin-top: 1px; }

/* ════ TABELA DE PERGUNTAS ════ */
.q-table { width: 100%; border-collapse: collapse; }

.q-table thead tr th {
    background: #2d3748;
    color: white;
    font-size: 7.5px;
    font-weight: bold;
    padding: 5px 9px;
    text-align: left;
    border: none;
}
.q-table thead tr th.th-ans {
    text-align: center;
    width: 18%;
    white-space: nowrap;
}

/* Linha de categoria */
.q-cat td {
    background: #ebf8f5;
    color: #2c7a7b;
    font-size: 7px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    padding: 4px 9px 3px;
    border-bottom: 1px solid #b2f5ea;
    border-top: 1px solid #b2f5ea;
}

/* Linhas de pergunta */
.q-odd td  { background: white; }
.q-even td { background: #f7fafc; }
.q-odd td, .q-even td {
    padding: 4px 9px;
    border-bottom: 1px solid #edf2f7;
    vertical-align: middle;
}
.td-q   { font-size: 7.5px; color: #4a5568; }
.td-ans { text-align: center; font-size: 7.5px; font-weight: bold; white-space: nowrap; }

/* Linha de observação */
.q-obs-row td {
    padding: 2px 9px 5px 20px;
    border-bottom: 1px solid #edf2f7;
}
.q-obs { font-size: 6.5px; color: #a0aec0; font-style: italic; }

/* Cores de resposta */
.a-sim  { color: #276749; }
.a-nao  { color: #718096; font-weight: normal; }
.a-sei  { color: #975a16; font-weight: normal; }
.a-alrt { color: #c53030; }

/* ════ ASSINATURAS ════ */
.sig-decl { font-size: 7.5px; color: #4a5568; text-align: center; font-style: italic; margin: 14px 0 12px; }
.sig-badge-ok   { font-size: 6.5px; color: #276749; font-weight: bold; margin-bottom: 4px; }
.sig-badge-pend { font-size: 6px; color: #a0aec0; font-weight: bold; text-transform: uppercase;
                  letter-spacing: 0.06em; margin-bottom: 4px; }
.sig-img  { max-height: 42px; max-width: 160px; display: block; margin: 0 auto 5px; }
.sig-line { border-top: 1px solid #1a202c; width: 80%; margin: 0 auto 5px; }
.sig-nm   { font-size: 8px; font-weight: bold; color: #1a202c; }
.sig-role { font-size: 6.5px; color: #718096; margin-top: 1px; }
.sig-cro  { font-size: 7px; color: #4a5568; margin-top: 2px; }
.sig-dt   { font-size: 6px; color: #a0aec0; margin-top: 2px; }

/* ════ FOOTER ════ */
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
    /* ── configurações da clínica ── */
    $settings = $clinic?->settings ?? [];
    $cPhone   = $settings['phone'] ?? $settings['telefone'] ?? null;
    $cEmail   = $settings['email'] ?? null;
    $cAddr    = $settings['address'] ?? $settings['endereco'] ?? null;
    $patient  = $instance->patient;

    /* ── subtítulo da clínica (montado em PHP para evitar @endif@endif) ── */
    $cliSubParts = [];
    if ($cAddr) {
        $part = $cAddr;
        if ($clinic?->city) {
            $part .= ' — ' . $clinic->city;
        }
        $cliSubParts[] = $part;
    }
    if ($cPhone) {
        $cliSubParts[] = $cPhone;
    }
    if ($cEmail) {
        $cliSubParts[] = $cEmail;
    }
    $cliSub = implode(' · ', $cliSubParts);

    /* ── alertas clínicos ── */
    $alertQs = [];
    foreach ($categories as $cat) {
        foreach (($cat['questions'] ?? []) as $q) {
            if (!empty($q['has_alert']) && strtolower($q['value'] ?? '') === 'sim') {
                $alertQs[] = $q;
            }
        }
    }

    /* ── label e classe CSS para cada resposta ── */
    function pdfAnsLabel(string $v): string {
        $v = strtolower($v);
        if ($v === 'sim')  return 'Sim';
        if ($v === 'nao' || $v === 'não') return 'Não';
        if ($v === 'nao_sei' || $v === 'não sei') return 'Não sei';
        if ($v === '') return '—';
        return strtoupper($v);
    }
    function pdfAnsCls(array $q): string {
        $v = strtolower((string)($q['value'] ?? ''));
        if (!empty($q['has_alert']) && $v === 'sim') return 'a-alrt';
        if ($v === 'sim') return 'a-sim';
        if ($v === 'nao' || $v === 'não') return 'a-nao';
        if (str_contains($v, 'sei')) return 'a-sei';
        return '';
    }
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
      <div class="c-name">{{ $clinic?->trade_name ?: ($clinic?->name ?? '') }}</div>
      <div class="ttl">FICHA DE ANAMNESE</div>
      @if($cliSub)
        <div class="c-sub">{{ $cliSub }}</div>
      @endif
    </td>
    <td style="width:15%; vertical-align:top; text-align:right;">
      <div class="hdr-date">{{ $instance->effectiveDate()->format('d/m/Y') }}</div>
      @if($qrDataUri)
        <img src="{{ $qrDataUri }}" style="width:44px; height:44px; margin-top:3px;" alt="QR">
        <div style="font-size:5.5px; color:#a0aec0; text-align:center; margin-top:1px; line-height:1.4;">Validar<br>documento</div>
      @endif
    </td>
  </tr>
</table>

<div class="rule"></div>

{{-- ════════════ DADOS DO PACIENTE ════════════ --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:3px;">
  <tr>
    {{-- Coluna esquerda --}}
    <td style="width:50%; vertical-align:top; padding-right:14px;">
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td style="padding:2px 0; width:38%; vertical-align:top;"><div class="f-lbl">Nome:</div></td>
          <td style="padding:2px 0; vertical-align:top;"><div class="f-val">{{ $patient->nome_completo }}</div></td>
        </tr>
        @if(!empty($patient->cpf))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">CPF:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $patient->cpf }}</div></td>
        </tr>
        @endif
        @if(!empty($patient->data_nascimento))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Nascimento:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ \Carbon\Carbon::parse($patient->data_nascimento)->format('d/m/Y') }}</div></td>
        </tr>
        @endif
        @if(!empty($patient->sexo))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Sexo:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $patient->sexo }}</div></td>
        </tr>
        @endif
        @if(!empty($patient->celular) || !empty($patient->telefone))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Celular:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $patient->celular ?? $patient->telefone }}</div></td>
        </tr>
        @endif
      </table>
    </td>

    {{-- Coluna direita --}}
    <td style="width:50%; vertical-align:top; border-left:1px solid #e2e8f0; padding-left:14px;">
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td style="padding:2px 0; width:48%; vertical-align:top;"><div class="f-lbl">Profissional:</div></td>
          <td style="padding:2px 0; vertical-align:top;"><div class="f-val">{{ $instance->professional?->name ?? '—' }}</div></td>
        </tr>
        @if(($dentistSignature ?? null)?->professional_cro)
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">CRO:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $dentistSignature->professional_cro }}</div></td>
        </tr>
        @endif
        @if(!empty($patient->email))
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">E-mail:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $patient->email }}</div></td>
        </tr>
        @endif
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Modelo:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ $instance->displayName() }}</div></td>
        </tr>
        <tr>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-lbl">Prontu&aacute;rio n&ordm;:</div></td>
          <td style="padding:1.5px 0; vertical-align:top;"><div class="f-val">{{ str_pad($instance->id, 6, '0', STR_PAD_LEFT) }}</div></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="rule"></div>

{{-- ════════════ BOX DE ALERTAS ════════════ --}}
@if(count($alertQs) > 0)
<div class="alrt-wrap">
  <div class="alrt-title">&#9888; Pontos de aten&ccedil;&atilde;o cl&iacute;nica</div>
  @foreach($alertQs as $aq)
    <div class="alrt-item">
      &rsaquo; {{ $aq['text'] }}
      @if(!empty($aq['supplementary_text']))
        &mdash; {{ $aq['supplementary_text'] }}
      @endif
    </div>
  @endforeach
</div>
@endif

{{-- ════════════ TABELA DE PERGUNTAS ════════════ --}}
<table class="q-table" cellspacing="0" cellpadding="0">
  <thead>
    <tr>
      <th>Pergunta</th>
      <th class="th-ans">Resposta</th>
    </tr>
  </thead>
  <tbody>
  @php $rowIdx = 0; @endphp
  @foreach($categories as $category)
    @php
      $allQs    = collect($category['questions'] ?? []);
      $answered = $allQs->filter(function($q) {
          return !empty($q['value']) || !empty($q['supplementary_text']);
      })->values();
    @endphp
    @if($answered->count() > 0)
    <tr class="q-cat">
      <td colspan="2">
        @if(!empty($category['icon']))
          {{ $category['icon'] }}&nbsp;
        @endif
        {{ $category['name'] }}
      </td>
    </tr>
    @foreach($answered as $q)
      @php
        $qv      = strtolower((string)($q['value'] ?? ''));
        $qLabel  = pdfAnsLabel((string)($q['value'] ?? ''));
        $qCls    = pdfAnsCls($q);
        $hasObs  = !empty($q['supplementary_text']);
        $rowCls  = ($rowIdx % 2 === 0) ? 'q-even' : 'q-odd';
        $rowIdx++;
      @endphp
      <tr class="{{ $rowCls }}">
        <td class="td-q">{{ $q['text'] }}</td>
        <td class="td-ans {{ $qCls }}">{{ $qLabel }}</td>
      </tr>
      @if($hasObs)
      <tr class="q-obs-row">
        <td colspan="2" class="q-obs">Obs.: {{ $q['supplementary_text'] }}</td>
      </tr>
      @endif
    @endforeach
    @endif
  @endforeach
  </tbody>
</table>

{{-- ════════════ DECLARAÇÃO E ASSINATURAS ════════════ --}}
<div style="page-break-inside:avoid;">
  <div class="sig-decl">
    Declaro que as informa&ccedil;&otilde;es acima s&atilde;o verdadeiras e foram prestadas de forma livre e espont&acirc;nea.
  </div>

  <table width="100%" cellspacing="0" cellpadding="0">
    <tr>

      {{-- Paciente --}}
      <td width="45%" style="text-align:center; vertical-align:top;">
        @if($patientSignatureDataUri ?? false)
          <div class="sig-badge-ok">&#10004; Assinado eletronicamente</div>
          <img src="{{ $patientSignatureDataUri }}" class="sig-img" alt="Assinatura">
        @else
          <div class="sig-badge-pend">Aguardando assinatura</div>
          <div style="height:42px;"></div>
        @endif
        <div class="sig-line"></div>
        <div class="sig-nm">{{ $patient->nome_completo }}</div>
        <div class="sig-role">Paciente / Respons&aacute;vel Legal</div>
        @if(($patientSignature ?? null)?->signed_at)
          <div class="sig-dt">{{ $patientSignature->signed_at->format('d/m/Y H:i') }}</div>
        @endif
      </td>

      <td width="10%"></td>

      {{-- Dentista --}}
      <td width="45%" style="text-align:center; vertical-align:top;">
        @if($dentistSignatureDataUri ?? false)
          <div class="sig-badge-ok">&#10004; Assinado eletronicamente</div>
          <img src="{{ $dentistSignatureDataUri }}" class="sig-img" alt="Assinatura">
        @else
          <div class="sig-badge-pend">Aguardando assinatura</div>
          <div style="height:42px;"></div>
        @endif
        <div class="sig-line"></div>
        <div class="sig-nm">{{ $instance->professional?->name ?? 'Profissional Respons&aacute;vel' }}</div>
        <div class="sig-role">Cirurgi&atilde;o-Dentista</div>
        @if(($dentistSignature ?? null)?->professional_cro)
          <div class="sig-cro">CRO: {{ $dentistSignature->professional_cro }}</div>
        @endif
        @if(($dentistSignature ?? null)?->signed_at)
          <div class="sig-dt">{{ $dentistSignature->signed_at->format('d/m/Y H:i') }}</div>
        @endif
      </td>

    </tr>
  </table>
</div>

{{-- ════════════ FOOTER FIXO ════════════ --}}
<div class="foot">
  <table width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td style="font-size:6px; color:#a0aec0; vertical-align:middle;">
        Doc. {{ str_pad($instance->id, 6, '0', STR_PAD_LEFT) }}
        &nbsp;&middot;&nbsp; {{ $instance->effectiveDate()->format('d/m/Y') }}
        &nbsp;&middot;&nbsp; Gerado em {{ now()->format('d/m/Y H:i') }}
        @if($instance->validation_token)
          <br><span class="foot-mono">{{ $instance->validation_token }}</span>
        @endif
      </td>
      <td style="text-align:center; font-size:6px; color:#a0aec0; vertical-align:middle;">
        <span class="foot-brand">ClinicFlow</span>
      </td>
      <td style="text-align:right; font-size:6.5px; color:#a0aec0; vertical-align:middle;">
        P&aacute;gina 1 de 1
      </td>
    </tr>
  </table>
</div>

</body>
</html>
