/**
 * Central source of truth for appointment/consultation status visuals.
 * Shared across Agenda, Consultas, Dashboard, Notificações.
 */

export const STATUS_CONFIG = {
    // Estados derivados de appointment.status + consultation.status
    em_atendimento: {
        label:    'Em atendimento',
        color:    'bg-red-500',
        ring:     'ring-red-300',
        text:     'text-red-700',
        badge:    'bg-red-100 text-red-700',
        dot:      'bg-red-500',
        // card: mantém fundo/border já definidos em Index.vue para não duplicar
        priority: 1,
    },
    late: {
        label:    'Atrasada',
        color:    'bg-orange-500',
        ring:     'ring-orange-300',
        text:     'text-orange-700',
        badge:    'bg-orange-100 text-orange-700',
        dot:      'bg-orange-500',
        priority: 2,
    },
    checkin: {
        label:    'Check-in realizado',
        color:    'bg-yellow-400',
        ring:     'ring-yellow-300',
        text:     'text-yellow-700',
        badge:    'bg-yellow-100 text-yellow-700',
        dot:      'bg-yellow-400',
        priority: 3,
    },
    scheduled: {
        label:    'Aguardando confirmação',
        color:    'bg-violet-500',
        ring:     'ring-violet-300',
        text:     'text-violet-700',
        badge:    'bg-violet-100 text-violet-700',
        dot:      'bg-violet-500',
        priority: 4,
    },
    confirmed: {
        label:    'Confirmada',
        color:    'bg-green-500',
        ring:     'ring-green-300',
        text:     'text-green-700',
        badge:    'bg-green-100 text-green-700',
        dot:      'bg-green-500',
        priority: 5,
    },
    completed: {
        label:    'Concluída',
        color:    'bg-slate-500',
        ring:     'ring-slate-300',
        text:     'text-slate-500',
        badge:    'bg-slate-100 text-slate-500',
        dot:      'bg-slate-500',
        priority: 6,
    },
    cancelled: {
        label:    'Cancelada',
        color:    'bg-slate-300',
        ring:     'ring-slate-200',
        text:     'text-slate-400',
        badge:    'bg-slate-50 text-slate-400',
        dot:      'bg-slate-300',
        priority: 7,
    },
    no_show: {
        label:    'Faltou',
        color:    'bg-amber-500',
        ring:     'ring-amber-300',
        text:     'text-amber-700',
        badge:    'bg-amber-100 text-amber-700',
        dot:      'bg-amber-500',
        priority: 8,
    },
}

/**
 * Resolve o status visual efetivo de um agendamento.
 * Leva em conta: atraso, check-in e se a consulta foi iniciada.
 *
 * @param {Object} appt   - appointment (com appt.consultation?.status)
 * @param {Date}   now    - instante atual (injetado para reatividade Vue)
 */
export function resolveStatus(appt, now = new Date()) {
    const { status, consultation, start } = appt

    if (status === 'completed') return 'completed'
    if (status === 'cancelled') return 'cancelled'
    if (status === 'no_show')   return 'no_show'

    if (status === 'in_attendance') {
        // Se a consulta foi iniciada (em_atendimento) vs só check-in (aguardando)
        return consultation?.status === 'em_atendimento' ? 'em_atendimento' : 'checkin'
    }

    // scheduled | confirmed — verificar atraso
    if (new Date(start) < now) return 'late'
    return status // 'scheduled' | 'confirmed'
}

/**
 * Minutos de atraso (0 se não atrasado ou se já passou).
 */
export function getDelayMinutes(appt, now = new Date()) {
    const st = resolveStatus(appt, now)
    if (st !== 'late') return 0
    return Math.floor((now - new Date(appt.start)) / 60000)
}

/**
 * Resolve o status visual de uma consulta (tabela consultations).
 * Retorna uma chave compatível com STATUS_CONFIG.
 */
export function resolveConsultationStatus(consultation) {
    const map = {
        aguardando:     'checkin',
        em_atendimento: 'em_atendimento',
        finalizado:     'completed',
        cancelado:      'cancelled',
    }
    return map[consultation.status] ?? 'checkin'
}

/**
 * Ordena appointments por prioridade de status, depois por horário.
 */
export function sortByPriority(appointments, now = new Date()) {
    return [...appointments].sort((a, b) => {
        const pa = STATUS_CONFIG[resolveStatus(a, now)]?.priority ?? 99
        const pb = STATUS_CONFIG[resolveStatus(b, now)]?.priority ?? 99
        if (pa !== pb) return pa - pb
        return new Date(a.start) - new Date(b.start)
    })
}
