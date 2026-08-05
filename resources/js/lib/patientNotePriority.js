// Única fonte de cor/ícone/label por nível de prioridade de observações-alerta.
// Reaproveitado por PatientNotesTab.vue (form + cards) e PatientAlertChips.vue
// (badge do topo + popover) para não duplicar o mapa de estilos em dois lugares.
export const NOTE_PRIORITIES = {
    critico: {
        label: 'Crítico',
        emoji: '🔴',
        badgeClass: 'border-red-300 bg-red-50 text-red-700',
        cardClass: 'border-red-200 bg-red-50/30',
        pillActiveClass: 'bg-red-600 text-white border-red-600',
    },
    atencao: {
        label: 'Atenção',
        emoji: '🟠',
        badgeClass: 'border-amber-300 bg-amber-50 text-amber-700',
        cardClass: 'border-amber-200 bg-amber-50/30',
        pillActiveClass: 'bg-amber-600 text-white border-amber-600',
    },
    informativo: {
        label: 'Informativo',
        emoji: '🔵',
        badgeClass: 'border-blue-300 bg-blue-50 text-blue-700',
        cardClass: 'border-blue-200 bg-blue-50/30',
        pillActiveClass: 'bg-blue-600 text-white border-blue-600',
    },
}

// Do mais grave ao menos grave — usado para escolher o pior nível entre
// vários alertas (ex: badge de "N alertas importantes" no topo do perfil).
export const NOTE_PRIORITY_ORDER = ['critico', 'atencao', 'informativo']

export function notePriorityStyle(priority) {
    return NOTE_PRIORITIES[priority] ?? NOTE_PRIORITIES.critico
}

export function worstNotePriority(notes) {
    for (const level of NOTE_PRIORITY_ORDER) {
        if (notes.some((n) => (n.priority ?? 'critico') === level)) {
            return level
        }
    }
    return 'critico'
}
