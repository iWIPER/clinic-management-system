// Regras visuais compartilhadas entre TaskListItem.vue (Lista) e
// TaskBoardCard.vue (Board) — um único lugar pra prioridade/data/paciente não
// divergirem entre as duas visualizações do mesmo módulo.

import { ClockIcon, ArrowPathIcon, PauseCircleIcon, CheckIcon } from '@heroicons/vue/24/outline'

// Tons suaves, mas perceptíveis à distância — o objetivo é dar contexto
// visual imediato sem cansar os olhos. Concluída ignora a prioridade (ver
// cardPriorityClass) porque não faz sentido destacar prioridade de algo já
// resolvido.
const PRIORITY_CARD = {
    urgente: 'bg-red-100 border-red-300',
    alta:    'bg-amber-100 border-amber-200',
    media:   'bg-blue-100 border-blue-200',
    baixa:   'bg-emerald-50 border-emerald-200',
}

// Texto da prioridade ecoa a cor do card — reforça que é uma informação de
// outra natureza que as etiquetas (que usam a cor própria de cada uma).
const PRIORITY_TEXT = {
    urgente: 'text-red-700',
    alta:    'text-amber-700',
    media:   'text-blue-700',
    baixa:   'text-emerald-700',
}

export function cardPriorityClass(task) {
    return task.status === 'done'
        ? 'bg-slate-50/60 border-slate-100'
        : (PRIORITY_CARD[task.priority] ?? 'bg-white border-slate-100')
}

export function priorityTextClass(task) {
    return task.status === 'done' ? 'text-slate-400' : (PRIORITY_TEXT[task.priority] ?? 'text-slate-600')
}

export function isOverdueTask(task) {
    if (!task.due_date || task.status === 'done') return false
    return task.due_date.slice(0, 10) < new Date().toISOString().slice(0, 10)
}

export function formatTaskDate(d) {
    return d ? new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', timeZone: 'UTC' }) : null
}

export function patientDisplayName(p) {
    return `${p.nome} ${p.sobrenome ?? ''}`.trim()
}

// Ícone de status — sinal discreto e PURAMENTE visual (mudar o status
// continua sendo só pelo modal de edição). Cor independente da prioridade,
// que continua sendo o fundo do card inteiro; aqui é só a cor do próprio
// ícone, sem chip/fundo. "Em andamento" usa o mesmo azul de "A fazer", só
// mais forte (400 -> 600), pra ficar claro que é progressão do mesmo estado.
const STATUS_ICON_COLOR = {
    todo:    'text-blue-400',
    doing:   'text-blue-600',
    waiting: 'text-amber-500',
    done:    'text-emerald-600',
}

const STATUS_ICON = {
    todo: ClockIcon,
    doing: ArrowPathIcon,
    waiting: PauseCircleIcon,
    done: CheckIcon,
}

export function statusIconClass(task) {
    return STATUS_ICON_COLOR[task.status] ?? 'text-slate-400'
}

export function statusIconFor(task) {
    return STATUS_ICON[task.status] ?? ClockIcon
}
