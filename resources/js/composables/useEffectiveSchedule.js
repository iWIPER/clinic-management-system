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
// GRID_FLOOR_HOUR não é exportado — só usado internamente neste arquivo
// (nenhum outro consumidor real; GRID_CEIL_HOUR abaixo é o único dos dois
// realmente usado fora daqui).
const GRID_FLOOR_HOUR = 7
export const GRID_CEIL_HOUR = 21

function dayKeyForDate(date) {
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

    // Nem profissional nem clínica configuraram horário pra este dia — teto
    // absoluto é o mesmo limite visual da própria grade (GRID_FLOOR_HOUR/
    // GRID_CEIL_HOUR), nunca "sem restrição" (mesmo fallback do backend,
    // ver AppointmentSchedulingService::DEFAULT_HOURS).
    if (start === null || end === null) {
        start = `${String(GRID_FLOOR_HOUR).padStart(2, '0')}:00`
        end = `${String(GRID_CEIL_HOUR).padStart(2, '0')}:00`
    }

    return { closed: false, start, end, reason: null, reasonLabel: null }
}

/**
 * @param {object} opts
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

    // O TOPO da grade é sempre GRID_FLOOR_HOUR — nunca encolhe pra
    // acompanhar o horário configurado (clínica ou profissional), mesmo
    // com regra obrigatória ativa. A grade visual é sempre 07:00→21:00; é
    // outOfHoursBandsFor() (ver Index.vue/Fullscreen.vue), não o tamanho da
    // grade, quem decora as horas fora do expediente com a banda cinza.
    const gridStartHour = computed(() => GRID_FLOOR_HOUR)

    return { dayWindow, gridStartHour }
}
