<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Prontuário — <?php echo e($patient->nome); ?> <?php echo e($patient->sobrenome); ?></title>
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
        <?php if($logoDataUri): ?>
            <img src="<?php echo e($logoDataUri); ?>" class="logo" alt="Logo">
        <?php endif; ?>
        <div class="clinic-name"><?php echo e($clinic->trade_name ?? $clinic->name); ?></div>
        <?php if($clinic->slogan): ?>
            <div class="slogan"><?php echo e($clinic->slogan); ?></div>
        <?php endif; ?>
    </div>

    <div class="doc-title">Prontuário Odontológico</div>

    
    <div class="section">
        <div class="section-title">Identificação do Paciente</div>
        <div class="grid-2">
            <div class="col">
                <div class="field"><span class="label">Nome: </span><span class="value"><?php echo e($patient->nome); ?> <?php echo e($patient->sobrenome); ?></span></div>
                <div class="field"><span class="label">Nascimento: </span><span class="value"><?php echo e($patient->nascimento?->format('d/m/Y') ?? '—'); ?></span></div>
                <div class="field"><span class="label">Documento: </span><span class="value"><?php echo e($patient->doc_tipo); ?> <?php echo e($patient->doc_numero); ?></span></div>
            </div>
            <div class="col">
                <div class="field"><span class="label">Telefone: </span><span class="value"><?php echo e($patient->telefone ?? '—'); ?></span></div>
                <div class="field"><span class="label">E-mail: </span><span class="value"><?php echo e($patient->email ?? '—'); ?></span></div>
                <div class="field"><span class="label">Status: </span><span class="value"><?php echo e(ucfirst($patient->status)); ?></span></div>
            </div>
        </div>
    </div>

    
    <?php if($patient->anamnesis): ?>
    <div class="section">
        <div class="section-title">Anamnese</div>
        <?php if($patient->anamnesis->queixa_principal): ?>
            <div class="field"><span class="label">Queixa principal: </span><span class="value"><?php echo e($patient->anamnesis->queixa_principal); ?></span></div>
        <?php endif; ?>
        <div style="margin: 6px 0;">
            <?php if($patient->anamnesis->gestante): ?><span class="badge badge-yes">Gestante</span><?php endif; ?>
            <?php if($patient->anamnesis->hipertensao): ?><span class="badge badge-yes">Hipertensão</span><?php endif; ?>
            <?php if($patient->anamnesis->diabetes): ?><span class="badge badge-yes">Diabetes</span><?php endif; ?>
            <?php if($patient->anamnesis->cardiopatia): ?><span class="badge badge-yes">Cardiopatia</span><?php endif; ?>
            <?php if($patient->anamnesis->hemorragia): ?><span class="badge badge-yes">Hemorragia</span><?php endif; ?>
            <?php if($patient->anamnesis->fumo): ?><span class="badge badge-yes">Fumo</span><?php endif; ?>
            <?php if($patient->anamnesis->alcool): ?><span class="badge badge-yes">Álcool</span><?php endif; ?>
        </div>
        <?php $__currentLoopData = ['alergias' => 'Alergias', 'medicamentos_em_uso' => 'Medicamentos', 'doencas_sistemicas' => 'Doenças sistêmicas', 'historico_medico' => 'Histórico médico', 'historico_familiar' => 'Histórico familiar', 'cirurgias_previas' => 'Cirurgias prévias', 'observacoes' => 'Observações']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($patient->anamnesis->$field): ?>
                <div class="field" style="margin-top:4px;"><span class="label"><?php echo e($label); ?>: </span><span class="value"><?php echo e($patient->anamnesis->$field); ?></span></div>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    
    <?php $teethData = $patient->odontogram?->teeth_data ?? []; ?>
    <?php if(!empty($teethData)): ?>
    <div class="section">
        <div class="section-title">Odontograma</div>
        <?php $__currentLoopData = [array_slice($fdiTeeth, 0, 16), array_slice($fdiTeeth, 16, 16)]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <table class="odontogram">
            <tr>
                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tooth): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $status = $teethData[$tooth]['status'] ?? 'saudavel'; ?>
                    <td class="tooth-<?php echo e($status); ?>">
                        <div class="tooth-num"><?php echo e($tooth); ?></div>
                        <div><?php echo e($toothStatuses[$status] ?? $status); ?></div>
                    </td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
        </table>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($patient->odontogram?->notes): ?>
            <div class="field" style="margin-top:6px;"><span class="label">Observações: </span><?php echo e($patient->odontogram->notes); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <?php if($patient->clinicalRecords->isNotEmpty()): ?>
    <div class="section">
        <div class="section-title">Procedimentos Realizados</div>
        <table class="data">
            <thead>
                <tr><th>Data</th><th>Procedimento</th><th>Profissional</th><th>Duração</th><th>Valor</th></tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $patient->clinicalRecords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($record->finished_at?->format('d/m/Y')); ?></td>
                    <td><?php echo e($record->procedure_name); ?></td>
                    <td><?php echo e($record->professional?->name); ?></td>
                    <td><?php echo e($record->duration_minutes ? $record->duration_minutes . ' min' : '—'); ?></td>
                    <td>R$ <?php echo e(number_format($record->price, 2, ',', '.')); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    
    <?php if($patient->evolutions->isNotEmpty()): ?>
    <div class="section">
        <div class="section-title">Evoluções Clínicas</div>
        <?php $__currentLoopData = $patient->evolutions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="evolution-item">
            <div class="evolution-date"><?php echo e($evo->recorded_at->format('d/m/Y H:i')); ?> — <?php echo e($evo->professional?->name); ?></div>
            <div class="text-block" style="margin-top:3px;"><?php echo e($evo->content); ?></div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    
    <?php if($patient->photos->isNotEmpty()): ?>
    <div class="section">
        <div class="section-title">Fotos Clínicas e Documentos</div>
        <table class="data">
            <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Dente</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $patient->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($photo->taken_at?->format('d/m/Y') ?? '—'); ?></td>
                    <td><?php echo e($photo->categoria); ?></td>
                    <td><?php echo e($photo->subcategoria ?? $photo->filename); ?></td>
                    <td><?php echo e($photo->dente ?? '—'); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">Emitido por CliniFlow — <?php echo e(now()->format('d/m/Y H:i')); ?></div>
</body>
</html><?php /**PATH C:\Users\drehs\dev\gestao-clinicas\resources\views/pdf/patient-prontuario.blade.php ENDPATH**/ ?>