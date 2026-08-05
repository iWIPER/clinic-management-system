import { format, formatDistanceToNow, isToday, isYesterday, parseISO } from 'date-fns'
import { ptBR } from 'date-fns/locale'

export function parseDate(value) {
    if (!value) return null
    try {
        return typeof value === 'string' ? parseISO(value) : value
    } catch {
        return null
    }
}

export function fmtDateTime(value) {
    const date = parseDate(value)
    if (!date) return '—'

    if (isToday(date)) {
        return `Hoje às ${format(date, 'HH:mm', { locale: ptBR })}`
    }
    if (isYesterday(date)) {
        return `Ontem às ${format(date, 'HH:mm', { locale: ptBR })}`
    }

    return format(date, "dd/MM/yyyy 'às' HH:mm", { locale: ptBR })
}

export function fmtDate(value) {
    const date = parseDate(value)
    if (!date) return '—'
    return format(date, 'dd/MM/yyyy', { locale: ptBR })
}

export function fmtRelative(value) {
    const date = parseDate(value)
    if (!date) return '—'
    return formatDistanceToNow(date, { addSuffix: true, locale: ptBR })
}

export function displayValue(value) {
    if (value === null || value === undefined || value === '') return '—'
    return value
}

export function statusLabel(status) {
    if (!status) return '—'
    return status === 'ativo' ? 'Ativo' : status.charAt(0).toUpperCase() + status.slice(1)
}

export function statusClasses(status) {
    return status === 'ativo'
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : 'bg-slate-100 text-slate-600 border-slate-200'
}