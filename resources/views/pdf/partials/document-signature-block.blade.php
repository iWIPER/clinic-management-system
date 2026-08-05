@php
    $roleLabels = [
        'patient' => 'Paciente / Responsável Legal',
        'professional' => 'Cirurgião-Dentista',
        'responsible' => 'Responsável',
        'witness' => 'Testemunha',
    ];
    $sigDataUri = null;
    if ($signature && $signature->signature_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($signature->signature_path)) {
        $sigDataUri = 'data:image/png;base64,' . base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($signature->signature_path));
    }
@endphp
<table width="100%" cellspacing="0" cellpadding="0" style="page-break-inside:avoid; margin: 10px 0;">
  <tr>
    <td style="text-align:center; vertical-align:top; width:100%;">
      @if($sigDataUri)
        <div style="font-size:6.5px; color:#276749; font-weight:bold; margin-bottom:4px;">&#10004; Assinado eletronicamente</div>
        <img src="{{ $sigDataUri }}" style="max-height:42px; max-width:160px; display:block; margin:0 auto 5px;" alt="Assinatura">
      @else
        <div style="font-size:6px; color:#a0aec0; font-weight:bold; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px;">Aguardando assinatura</div>
        <div style="height:42px;"></div>
      @endif
      <div style="border-top:1px solid #1a202c; width:60%; margin:0 auto 5px;"></div>
      <div style="font-size:8px; font-weight:bold; color:#1a202c;">{{ $signature->signer_name ?? '—' }}</div>
      <div style="font-size:6.5px; color:#718096; margin-top:1px;">{{ $roleLabels[$role] ?? ucfirst($role) }}</div>
      @if($signature?->professional_cro)
        <div style="font-size:7px; color:#4a5568; margin-top:2px;">CRO: {{ $signature->professional_cro }}</div>
      @endif
      @if($signature?->signed_at)
        <div style="font-size:6px; color:#a0aec0; margin-top:2px;">{{ $signature->signed_at->format('d/m/Y H:i') }}</div>
      @endif
    </td>
  </tr>
</table>
