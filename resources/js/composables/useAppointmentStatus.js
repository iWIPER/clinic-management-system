/**
 * Central source of truth for appointment/consultation status visuals.
 * Shared across Agenda, Consultas, Dashboard, Notificações.
 */

// Paleta revisada: agendada=azul, confirmada=verde, aguardando=laranja, em
// atendimento=roxo, concluída=neutro, cancelada/faltou=vermelho (tons
// diferentes entre si pra continuarem distinguíveis) — mesma paleta usada
// pela borda/fundo do card via cardAppearance(), abaixo, pra não ter duas
// fontes de cor divergentes pro mesmo status.
export const STATUS_CONFIG = {
    // Estados derivados de appointment.status + consultation.status
    em_atendimento: {
        label:    'Em atendimento',
        color:    'bg-purple-500',
        ring:     'ring-purple-300',
        text:     'text-purple-700',
        badge:    'bg-purple-100 text-purple-700',
        dot:      'bg-purple-500',
        priority: 1,
    },
    late: {
        label:    'Atrasada',
        color:    'bg-amber-500',
        ring:     'ring-amber-300',
        text:     'text-amber-700',
        badge:    'bg-amber-100 text-amber-700',
        dot:      'bg-amber-500',
        priority: 2,
    },
    checkin: {
        label:    'Paciente aguardando',
        color:    'bg-orange-500',
        ring:     'ring-orange-300',
        text:     'text-orange-700',
        badge:    'bg-orange-100 text-orange-700',
        dot:      'bg-orange-500',
        priority: 3,
    },
    scheduled: {
        label:    'Agendada',
        color:    'bg-blue-500',
        ring:     'ring-blue-300',
        text:     'text-blue-700',
        badge:    'bg-blue-100 text-blue-700',
        dot:      'bg-blue-500',
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
        color:    'bg-red-400',
        ring:     'ring-red-200',
        text:     'text-red-500',
        badge:    'bg-red-50 text-red-500',
        dot:      'bg-red-400',
        priority: 7,
    },
    no_show: {
        label:    'Faltou',
        color:    'bg-red-600',
        ring:     'ring-red-300',
        text:     'text-red-700',
        badge:    'bg-red-100 text-red-700',
        dot:      'bg-red-600',
        priority: 8,
    },
}

// Aparência estável do card da Agenda (fundo + borda esquerda + cor do
// texto do horário/nome) — chaveada pelo MESMO status resolvido de
// resolveStatus() (não o appt.status cru), pra "aguardando" e "em
// atendimento" ficarem visualmente diferentes no card, não só no tooltip.
// "late" reaproveita a cor de "scheduled": atraso é um alerta do
// tooltip/popover, não muda a cor permanente do card (pedido: status deve
// ser estável, sem elementos piscando/mudando sozinhos).
export const CARD_APPEARANCE = {
    scheduled:      { bg: 'bg-blue-50',    border: 'border-l-blue-500',   text: 'text-blue-700' },
    late:           { bg: 'bg-blue-50',    border: 'border-l-blue-500',   text: 'text-blue-700' },
    checkin:        { bg: 'bg-orange-50',  border: 'border-l-orange-500', text: 'text-orange-700' },
    em_atendimento: { bg: 'bg-purple-50',  border: 'border-l-purple-500', text: 'text-purple-700' },
    confirmed:      { bg: 'bg-green-50',   border: 'border-l-green-500',  text: 'text-green-700' },
    completed:      { bg: 'bg-slate-100',  border: 'border-l-slate-400',  text: 'text-slate-600' },
    cancelled:      { bg: 'bg-red-50',     border: 'border-l-red-300',    text: 'text-red-400' },
    no_show:        { bg: 'bg-red-50',     border: 'border-l-red-500',    text: 'text-red-600' },
}

export function cardAppearance(status) {
    return CARD_APPEARANCE[status] ?? CARD_APPEARANCE.scheduled
}

// Opções do dropdown "controle completo de status" do popover da Agenda —
// `key` aponta pra STATUS_CONFIG (cor/dot, mesma fonte única de sempre),
// `value` é o valor aceito por AppointmentController::updateStatus() (só 6
// status existem no banco). "Paciente aguardando"/"em atendimento" e as duas
// variantes de cancelamento compartilham o mesmo `value` de propósito — a
// distinção entre elas é resolvida em outro lugar (consultation.status pro
// primeiro par; "quem cancelou" nunca foi persistido, ver decisão já
// tomada) e não pode ser forçada por aqui sem inventar uma segunda lógica.
export const STATUS_DROPDOWN_OPTIONS = [
    { value: 'scheduled',     key: 'scheduled',      label: 'Agendada' },
    { value: 'confirmed',     key: 'confirmed',       label: 'Confirmada' },
    { value: 'in_attendance', key: 'checkin',         label: 'Paciente aguardando' },
    { value: 'in_attendance', key: 'em_atendimento',  label: 'Paciente em atendimento' },
    { value: 'completed',     key: 'completed',       label: 'Finalizada' },
    { value: 'no_show',       key: 'no_show',         label: 'Faltou' },
    { value: 'cancelled',     key: 'cancelled',       label: 'Cancelada pelo paciente' },
    { value: 'cancelled',     key: 'cancelled',       label: 'Cancelada pelo dentista' },
]

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
