<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Atendimento #<?php echo e($record->id); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #059669; }
        .logo { max-height: 60px; max-width: 180px; margin-bottom: 8px; }
        .clinic-name { font-size: 20px; font-weight: bold; color: #047857; }
        .slogan { font-size: 11px; color: #64748b; margin-top: 4px; }
        .title { font-size: 16px; font-weight: bold; margin: 20px 0 12px; color: #0f172a; }
        .section { margin-bottom: 16px; }
        .row { display: table; width: 100%; margin-bottom: 6px; }
        .label { display: table-cell; width: 140px; font-weight: bold; color: #475569; }
        .value { display: table-cell; color: #1e293b; }
        .notes { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-top: 8px; white-space: pre-wrap; }
        .price { font-size: 18px; font-weight: bold; color: #047857; margin-top: 12px; }
        .footer { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
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

    <div class="title">Comprovante de Atendimento</div>

    <div class="section">
        <div class="row">
            <span class="label">Paciente:</span>
            <span class="value"><?php echo e($record->patient->nome); ?> <?php echo e($record->patient->sobrenome); ?></span>
        </div>
        <div class="row">
            <span class="label">Profissional:</span>
            <span class="value"><?php echo e($record->professional->name); ?></span>
        </div>
        <div class="row">
            <span class="label">Procedimento:</span>
            <span class="value"><?php echo e($record->procedure_name); ?></span>
        </div>
        <?php if($record->procedure_category): ?>
        <div class="row">
            <span class="label">Categoria:</span>
            <span class="value"><?php echo e($record->procedure_category); ?></span>
        </div>
        <?php endif; ?>
        <div class="row">
            <span class="label">Data:</span>
            <span class="value"><?php echo e($record->finished_at?->format('d/m/Y') ?? '—'); ?></span>
        </div>
        <div class="row">
            <span class="label">Horário:</span>
            <span class="value">
                <?php if($record->started_at && $record->finished_at): ?>
                    <?php echo e($record->started_at->format('H:i')); ?> – <?php echo e($record->finished_at->format('H:i')); ?>

                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
        <?php if($record->duration_minutes): ?>
        <div class="row">
            <span class="label">Duração:</span>
            <span class="value"><?php echo e($record->duration_minutes); ?> minutos</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="price">Valor: R$ <?php echo e(number_format($record->price, 2, ',', '.')); ?></div>

    <?php if($record->notes): ?>
    <div class="section" style="margin-top: 20px;">
        <strong>Observações:</strong>
        <div class="notes"><?php echo e($record->notes); ?></div>
    </div>
    <?php endif; ?>

    <div class="footer">Emitido pelo CliniFlow</div>
</body>
</html><?php /**PATH C:\Users\drehs\dev\gestao-clinicas\resources\views/pdf/clinical-record.blade.php ENDPATH**/ ?>