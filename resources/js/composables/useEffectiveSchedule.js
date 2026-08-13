import { computed } from 'vue'

/**
 * Espelha (só pra DECISÃO VISUAL da Agenda) a mesma precedência já
 * implementada no backend — ver ClinicUserPivot::effectiveWorkingDayEnabled/
 * effectiveWorkingHours e Clinic::businessHoursFor/businessHoursEnforced.
 * A autoridade real de bloqueio de agendamento continua sendo o backend
 * (AppointmentController::assertProfessionalAvailable), que já aplica
 * exatamente esta mesma regra — isto aqui nunca decide sozinho se um
 * agendamento pode ser criado, só como a grade deve SE PARECER.
 *
 * Compartilhado por Index.vue e Fullscreen.vue — única fonte da regra no
 * frontend, pra não duplicar a matemática de interseção em dois arquivos.
 */

export const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

// Limite absoluto da grade — nunca ultrapassado, mesmo com configuração ou
// agendamento além disso (ver item 3 do pedido: 21:00 é o teto visual).
export const GRID_FLOOR_HOUR = 7
export const GRID_CEIL_HOUR = 21

export function dayKeyForDate(date) {
    const idx = date.getDay() === 0 ? 6 : date.getDay() - 1
    return DAY_KEYS[idx]
}

/**
 * Janela efetiva de UM dia — pra um profissional específico (workingDays/
 * workingHours preenchidos) ou só a regra da clínica (ambos null, usado no
 * modo "Todos"). Mesma ordem de precedência do backend: feriado > dia
 * fechado pela clínica > interseção de horário (clínica é teto, nunca
 * obrigação — um profissional mais restrito que a clínica continua como
 * está).
 *
 * @returns {{closed: boolean, start: string|null, end: string|null, reason: 'holiday'|'clinic-day-off'|null, reasonLabel: string|null}}
 */
export function effectiveDayWindow({
    date,
    holidayName = null,
    considerHolidays = false,
    clinicBusinessHours = null,
    clinicEnforced = false,
    workingDays = null,
    workingHours = null,
}) {
    if (considerHolidays && holidayName) {
        return { closed: true, start: null, end: null, reason: 'holiday', reasonLabel: holidayName }
    }

    const dayKey = dayKeyForDate(date)
    const clinicDay = clinicBusinessHours?.[dayKey] ?? null
    const ownEnabled = workingDays ? workingDays[dayKey] !== false : true

    // Clínica fechada nesse dia + regra obrigatória vence o profissional
    // (que continua com o dado dele intacto, só a leitura ignora) — checado
    // ANTES do dia-de-folga do próprio profissional pra não rotular errado
    // um dia que só está fechado por escolha dele, não da clínica.
    if (clinicEnforced && clinicDay && !clinicDay.enabled) {
        return { closed: true, start: null, end: null, reason: 'clinic-day-off', reasonLabel: null }
    }

    if (!ownEnabled) {
        return { closed: true, start: null, end: null, reason: 'professional-day-off', reasonLabel: null }
    }

    let start = workingHours?.start ?? null
    let end = workingHours?.end ?? null

    if (clinicEnforced && clinicDay?.enabled) {
        if (!workingHours) {
            start = clinicDay.start
            end = clinicDay.end
        } else {
            start = start > clinicDay.start ? start : clinicDay.start
            end = end < clinicDay.end ? end : clinicDay.end
        }
    }

    return { closed: false, start, end, reason: null, reasonLabel: null }
}

function hourOf(hhmm) {
    return parseInt(hhmm.split(':')[0], 10)
}

/**
 * @param {object} opts
 * @param {import('vue').Ref<Date[]>} opts.visibleDays
 * @param {(date: Date) => ({working_days: object|null, working_hours: object|null}|null)} opts.getProfessionalScopeForDay
 *   Retorna a config RAW do profissional relevante pra aquele dia (o
 *   selecionado, se houver) ou null no modo "Todos" (só a clínica decide).
 * @param {import('vue').Ref<boolean>} opts.considerNationalHolidays
 * @param {import('vue').Ref<object>} opts.holidays - {'YYYY-MM-DD': nome}
 * @param {import('vue').Ref<object>} opts.businessHours
 * @param {import('vue').Ref<boolean>} opts.businessHoursEnforced
 * @param {(date: Date) => string} opts.toDateStr
 */
export function useAgendaScheduleRules({
    visibleDays,
    getProfessionalScopeForDay,
    considerNationalHolidays,
    holidays,
    businessHours,
    businessHoursEnforced,
    toDateStr,
}) {
    function dayWindow(date) {
        const scope = getProfessionalScopeForDay(date)
        return effectiveDayWindow({
            date,
            holidayName: considerNationalHolidays.value ? (holidays.value[toDateStr(date)] || null) : null,
            considerHolidays: considerNationalHolidays.value,
            clinicBusinessHours: businessHours.value,
            clinicEnforced: businessHoursEnforced.value,
            workingDays: scope?.working_days ?? null,
            workingHours: scope?.working_hours ?? null,
        })
    }

    // Só encolhe o TOPO da grade quando a regra da clínica está ativa e
    // obrigatória (ver item 1 do pedido: "quando a regra... estiver ativa e
    // obrigatória"). Sem isso, comportamento idêntico ao hardcoded de
    // sempre (GRID_FLOOR_HOUR) — nenhuma mudança visual pra quem nunca
    // configurou regra global.
    const gridStartHour = computed(() => {
        if (!businessHoursEnforced.value) return GRID_FLOOR_HOUR

        let min = null
        for (const day of visibleDays.value) {
            const w = dayWindow(day)
            if (w.closed || !w.start) continue
            const h = hourOf(w.start)
            if (min === null || h < min) min = h
        }
        if (min === null) return GRID_FLOOR_HOUR
        return Math.max(GRID_FLOOR_HOUR, Math.min(min, GRID_CEIL_HOUR))
    })

    return { dayWindow, gridStartHour }
}
