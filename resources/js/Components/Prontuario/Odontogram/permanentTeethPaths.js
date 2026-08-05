// Indicadores visuais do odontograma — simplificado para só 3 estados que
// realmente pintam (ver plano). "Planejado" e status clínico do dente
// (cariado/restaurado/...) não têm cor própria; dente removido é indicado
// só pelo X (desenhado à parte), sem badge.
export const STATUS_VISUAL = {
  none:        { fill: '#ffffff', stroke: '#9ca3af', label: 'Saudável',     badge: null, badgeColor: null },
  in_progress: { fill: '#fee2e2', stroke: '#dc2626', label: 'Em andamento', badge: null, badgeColor: null },
  completed:   { fill: '#dcfce7', stroke: '#16a34a', label: 'Finalizado',   badge: null, badgeColor: null },
  future:      { fill: '#ede9fe', stroke: '#7c3aed', label: 'Futuro',       badge: null, badgeColor: null },
  removed:     { fill: '#fee2e2', stroke: '#dc2626', label: 'Removido',     badge: null, badgeColor: null },
}

// Ordem/itens mostrados na legenda (OdontogramLegend.vue) — só os 3 que pintam.
export const LEGEND_STATUSES = ['completed', 'in_progress', 'future']
