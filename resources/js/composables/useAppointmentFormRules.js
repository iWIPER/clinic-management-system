import { computed } from 'vue'
import { effectiveDayWindow } from '@/composables/useEffectiveSchedule'

/**
 * Avisos client-side compartilhados por Create.vue, Edit.vue e
 * AppointmentFormModal.vue — os três formulários de agendamento do sistema.
 * Quem garante de verdade é sempre o backend
 * (AppointmentController::assertProfessionalAvailable); isto aqui só evita
 * deixar o usuário submeter algo que já se sabe que vai ser rejeitado.
 *
 * A janela usada aqui é a EFETIVA (regra da clínica ∩ horário do
 * profissional, ver effectiveDayWindow em useEffectiveSchedule.js) — mesma
 * fonte usada pela grade da Agenda, nunca a config crua do profissional
 * sozinha. Isso é o que faltava antes: o formulário validava só contra
 * selectedProfessional.working_hours, ignorando business_hours/
 * business_hours_enforced da clínica por completo.
 *
 * Todos os parâmetros são Refs (ou computeds) — nos componentes que os
 * recebem via props, passe com `toRef(props, 'campo')` no call site.
 *
 * @param {import('vue').Ref<Array>} professionals - lista com working_days/working_hours
 * @param {import('vue').Ref<string>} professionalId
 * @param {import('vue').Ref<string>} date - 'YYYY-MM-DD'
 * @param {import('vue').Ref<string>} time - 'HH:MM'
 * @param {import('vue').Ref<number|string>} durationMinutes
 * @param {import('vue').Ref<Object>} holidays - { 'YYYY-MM-DD': 'Nome do feriado' }
 * @param {import('vue').Ref<boolean>} considerNationalHolidays
 * @param {import('vue').Ref<Object>} businessHours - regras da clínica por dia (ver Clinic::businessHoursResolved)
 * @param {import('vue').Ref<boolean>} businessHoursEnforced
 * @param {import('vue').Ref<{professionalId:string,date:string,time:string,durationMinutes:number}|null>} [originalSchedule]
 *   Só em modo edição — horário ORIGINAL do agendamento (antes de qualquer
 *   edição nesta sessão do formulário). Um agendamento antigo que já ficou
 *   fora do horário por uma mudança administrativa continua editável pra
 *   tudo que não é reagendamento (status, observações etc.) — só bloqueia
 *   quando o usuário está de fato MOVENDO pra outro horário/profissional/
 *   cadeira. Ausente (ou null) = sempre valida (comportamento de criação).
 */
export function useAppointmentFormRules({
    professionals, professionalId, date, time, durationMinutes,
    holidays, considerNationalHolidays, businessHours, businessHoursEnforced,
    originalSchedule = null,
}) {
    const WORKING_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

    const selectedProfessional = computed(() =>
        professionals.value.find(p => String(p.id) === String(professionalId.value)))

    const endTime = computed(() => {
        if (!date.value || !time.value || !durationMinutes.value) return ''
        const start = new Date(`${date.value}T${time.value}`)
        const end = new Date(start.getTime() + Number(durationMinutes.value) * 60000)
        return end.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    })

    const isDurationInvalid = computed(() => {
        const raw = durationMinutes.value
        const n = Number(raw)
        return raw === '' || raw == null || Number.isNaN(n) || n <= 0
    })

    // true = precisa validar (criação, ou edição mudando horário/
    // profissional/cadeira); false = edição sem tocar no agendamento em si
    // (ex.: só status/observações) — um agendamento antigo que ficou fora
    // do horário por uma regra nova não pode travar edições que não são
    // reagendamento.
    const scheduleChangedFromOriginal = computed(() => {
        const orig = originalSchedule?.value
        if (!orig) return true
        return String(orig.professionalId) !== String(professionalId.value)
            || orig.date !== date.value
            || orig.time !== time.value
            || Number(orig.durationMinutes) !== Number(durationMinutes.value)
    })

    const holidayName = computed(() => {
        if (!scheduleChangedFromOriginal.value) return ''
        if (!considerNationalHolidays?.value || !date.value) return ''
        return holidays.value?.[date.value] || ''
    })
    const isHoliday = computed(() => Boolean(holidayName.value))

    // Janela efetiva do profissional selecionado nesta data — mesma regra
    // (e mesma função) que a grade da Agenda usa pra decorar/bloquear.
    const dayWindow = computed(() => {
        if (!selectedProfessional.value || !date.value) return null
        return effectiveDayWindow({
            date: new Date(`${date.value}T00:00:00`),
            holidayName: holidayName.value || null,
            considerHolidays: Boolean(considerNationalHolidays?.value),
            clinicBusinessHours: businessHours?.value ?? null,
            clinicEnforced: Boolean(businessHoursEnforced?.value),
            workingDays: selectedProfessional.value.working_days ?? null,
            workingHours: selectedProfessional.value.working_hours ?? null,
        })
    })

    // Fechado por qualquer motivo QUE NÃO seja feriado (feriado já tem
    // aviso próprio, ver isHoliday/holidayName abaixo) — cobre tanto "esse
    // profissional não atende nesse dia" quanto "a clínica não atende nesse
    // dia" (regra obrigatória), com mensagens diferentes pra deixar claro
    // qual é o motivo real (nunca esconder o horário sem explicar).
    const isOffDay = computed(() =>
        scheduleChangedFromOriginal.value &&
        Boolean(dayWindow.value?.closed && dayWindow.value.reason !== 'holiday'))

    const offDayMessage = computed(() => {
        if (!isOffDay.value) return ''
        if (dayWindow.value?.reason === 'clinic-day-off') {
            return 'A clínica não atende neste dia — regra administrativa obrigatória.'
        }
        return `${selectedProfessional.value?.name || 'Este profissional'} não possui atendimento neste dia.`
    })

    // Cobre início ANTES de abrir e fim DEPOIS de fechar — duração maior
    // pode empurrar o término pra fora do expediente mesmo com início válido.
    const isOutsideWorkingHours = computed(() => {
        if (!scheduleChangedFromOriginal.value) return false
        const w = dayWindow.value
        if (!w || w.closed || !w.start || !w.end || !time.value) return false
        if (time.value < w.start || time.value > w.end) return true
        return Boolean(endTime.value) && endTime.value > w.end
    })

    const workingHoursMessage = computed(() => {
        if (!isOutsideWorkingHours.value) return ''
        const w = dayWindow.value
        const clinicNote = businessHoursEnforced?.value ? ' — considerando a regra obrigatória da clínica' : ''
        return `Este horário está fora do horário de atendimento (${w.start}–${w.end})${clinicNote}.`
    })

    return {
        selectedProfessional, endTime, isDurationInvalid,
        isOffDay, offDayMessage,
        isOutsideWorkingHours, workingHoursMessage,
        isHoliday, holidayName,
        dayWindow,
    }
}
