<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Prontuário — {{ $patient->nome }} {{ $patient->sobrenome }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.45; }
        .header { text-align: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #0d9488; }
        .logo { max-height: 50px; max-width: 160px; margin-bottom: 6px; }
        .clinic-name { font-size: 18px; font-weight: bold; color: #0f766e; letter-spacing: 0.5px; }
        .slogan { font-size: 10px; color: #64748b; margin-top: 3px; font-style: italic; }
        .doc-title { font-size: 14px; font-weight: bold; text-align: center; margin: 14px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; }
        .section { margin-bottom: 14px; page-break-inside: avoid; }
        .section-title { font-size: 11px; font-weight: bold; color: #0f766e; border-bottom: 1px solid #99f6e4; padding-bottom: 3px; margin-bottom: 8px; text-transform: uppercase; }
        .grid-2 { display: table; width: 100%; }
        .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
        .field { margin-bottom: 5px; }
        .label { font-weight: bold; color: #475569; font-size: 9px; }
        .value { color: #1e293b; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; margin-right: 3px; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .badge-yes { background: #fef3c7; border-color: #fcd34d; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th { background: #f0fdfa; border: 1px solid #ccfbf1; padding: 4px 6px; text-align: left; font-size: 9px; color: #0f766e; }
        table.data td { border: 1px solid #e2e8f0; padding: 4px 6px; font-size: 9px; }
        .text-block { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px; white-space: pre-wrap; border-radius: 3px; }
        .odontogram { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .odontogram td { border: 1px solid #cbd5e1; text-align: center; padding: 3px; font-size: 8px; width: 6.25%; }
        .odontogram .tooth-num { font-weight: bold; color: #475569; }
        .tooth-saudavel { background: #ecfdf5; }
        .tooth-cariado { background: #fef2f2; }
        .tooth-restaurado { background: #eff6ff; }
        .tooth-ausente { background: #f1f5f9; color: #94a3b8; }
        .tooth-endodontia { background: #faf5ff; }
        .tooth-protese { background: #fff7ed; }
        .tooth-implante { background: #f0f9ff; }
        .tooth-fraturado { background: #fefce8; }
        .evolution-item { margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #e2e8f0; }
        .evolution-date { font-weight: bold; color: #0f766e; font-size: 9px; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
        @endif
        <div class="clinic-name">{{ $clinic->trade_name ?? $clinic->name }}</div>
        @if($clinic->slogan)
            <div class="slogan">{{ $clinic->slogan }}</div>
        @endif
    </div>

    <div class="doc-title">Prontuário Odontológico</div>

    {{-- Identificação --}}
    <div class="section">
        <div class="section-title">Identificação do Paciente</div>
        <div class="grid-2">
            <div class="col">
                <div class="field"><span class="label">Nome: </span><span class="value">{{ $patient->nome }} {{ $patient->sobrenome }}</span></div>
                <div class="field"><span class="label">Nascimento: </span><span class="value">{{ $patient->nascimento?->format('d/m/Y') ?? '—' }}</span></div>
                <div class="field"><span class="label">Documento: </span><span class="value">{{ $patient->doc_tipo }} {{ $patient->doc_numero }}</span></div>
            </div>
            <div class="col">
                <div class="field"><span class="label">Telefone: </span><span class="value">{{ $patient->telefone ?? '—' }}</span></div>
                <div class="field"><span class="label">E-mail: </span><span class="value">{{ $patient->email ?? '—' }}</span></div>
                <div class="field"><span class="label">Status: </span><span class="value">{{ ucfirst($patient->status) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Anamnese --}}
    @if($patient->anamnesis)
    <div class="section">
        <div class="section-title">Anamnese</div>
        @if($patient->anamnesis->queixa_principal)
            <div class="field"><span class="label">Queixa principal: </span><span class="value">{{ $patient->anamnesis->queixa_principal }}</span></div>
        @endif
        <div style="margin: 6px 0;">
            @if($patient->anamnesis->gestante)<span class="badge badge-yes">Gestante</span>@endif
            @if($patient->anamnesis->hipertensao)<span class="badge badge-yes">Hipertensão</span>@endif
            @if($patient->anamnesis->diabetes)<span class="badge badge-yes">Diabetes</span>@endif
            @if($patient->anamnesis->cardiopatia)<span class="badge badge-yes">Cardiopatia</span>@endif
            @if($patient->anamnesis->hemorragia)<span class="badge badge-yes">Hemorragia</span>@endif
            @if($patient->anamnesis->fumo)<span class="badge badge-yes">Fumo</span>@endif
            @if($patient->anamnesis->alcool)<span class="badge badge-yes">Álcool</span>@endif
        </div>
        @foreach(['alergias' => 'Alergias', 'medicamentos_em_uso' => 'Medicamentos', 'doencas_sistemicas' => 'Doenças sistêmicas', 'historico_medico' => 'Histórico médico', 'historico_familiar' => 'Histórico familiar', 'cirurgias_previas' => 'Cirurgias prévias', 'observacoes' => 'Observações'] as $field => $label)
            @if($patient->anamnesis->$field)
                <div class="field" style="margin-top:4px;"><span class="label">{{ $label }}: </span><span class="value">{{ $patient->anamnesis->$field }}</span></div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Odontograma --}}
    @php $teethData = $patient->odontogram?->teeth_data ?? []; @endphp
    @if(!empty($teethData))
    <div class="section">
        <div class="section-title">Odontograma</div>
        @foreach([array_slice($fdiTeeth, 0, 16), array_slice($fdiTeeth, 16, 16)] as $row)
        <table class="odontogram">
            <tr>
                @foreach($row as $tooth)
                    @php $status = $teethData[$tooth]['status'] ?? 'saudavel'; @endphp
                    <td class="tooth-{{ $status }}">
                        <div class="tooth-num">{{ $tooth }}</div>
                        <div>{{ $toothStatuses[$status] ?? $status }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
        @endforeach
        @if($patient->odontogram?->notes)
            <div class="field" style="margin-top:6px;"><span class="label">Observações: </span>{{ $patient->odontogram->notes }}</div>
        @endif
    </div>
    @endif

    {{-- Procedimentos --}}
    @if($patient->clinicalRecords->isNotEmpty())
    <div class="section">
        <div class="section-title">Procedimentos Realizados</div>
        <table class="data">
            <thead>
                <tr><th>Data</th><th>Procedimento</th><th>Profissional</th><th>Duração</th><th>Valor</th></tr>
            </thead>
            <tbody>
                @foreach($patient->clinicalRecords as $record)
                <tr>
                    <td>{{ $record->finished_at?->format('d/m/Y') }}</td>
                    <td>{{ $record->procedure_name }}</td>
                    <td>{{ $record->professional?->name }}</td>
                    <td>{{ $record->duration_minutes ? $record->duration_minutes . ' min' : '—' }}</td>
                    <td>R$ {{ number_format($record->price, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Evoluções --}}
    @if($patient->evolutions->isNotEmpty())
    <div class="section">
        <div class="section-title">Evoluções Clínicas</div>
        @foreach($patient->evolutions as $evo)
        <div class="evolution-item">
            <div class="evolution-date">{{ $evo->recorded_at->format('d/m/Y H:i') }} — {{ $evo->professional?->name }}</div>
            <div class="text-block" style="margin-top:3px;">{{ $evo->content }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Fotos e documentos --}}
    @if($patient->photos->isNotEmpty())
    <div class="section">
        <div class="section-title">Fotos Clínicas e Documentos</div>
        <table class="data">
            <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Dente</th></tr></thead>
            <tbody>
                @foreach($patient->photos as $photo)
                <tr>
                    <td>{{ $photo->taken_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $photo->categoria }}</td>
                    <td>{{ $photo->subcategoria ?? $photo->filename }}</td>
                    <td>{{ $photo->dente ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">Emitido por Wildental — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>