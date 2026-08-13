<script setup>
import { reactive } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/20/solid'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    canManage: { type: Boolean, default: false },
    considerNationalHolidays: { type: Boolean, default: false },
    businessHours: { type: Object, required: true },
    businessHoursEnforced: { type: Boolean, default: false },
    dayKeys: { type: Array, default: () => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] },
})

const emit = defineEmits(['holidays-updated', 'business-hours-updated'])

const toast = useToast()

const DAY_LABELS = { mon: 'Seg', tue: 'Ter', wed: 'Qua', thu: 'Qui', fri: 'Sex', sat: 'Sáb', sun: 'Dom' }

function firstErrorMessage(e) {
    const errors = e.response?.data?.errors
    return errors ? Object.values(errors)[0]?.[0] : null
}

// ── Feriados nacionais ──────────────────────────────────────────────────
const holidays = reactive({
    value: props.considerNationalHolidays,
    saving: false,
})

async function toggleHolidays() {
    if (!props.canManage || holidays.saving) return
    const next = !holidays.value
    holidays.value = next
    holidays.saving = true
    try {
        await window.axios.put(route('clinic-settings.agendas.holidays'), {
            consider_national_holidays: next,
        })
        toast.success('Configuração de feriados atualizada.')
        emit('holidays-updated', next)
    } catch (e) {
        holidays.value = !next
        toast.error(firstErrorMessage(e) || 'Não foi possível salvar. Tente novamente.')
    } finally {
        holidays.saving = false
    }
}

// ── Horário de funcionamento ─────────────────────────────────────────────
// Cópia local reativa — mesmo padrão dos cards de profissional (Agendas.vue):
// cada mudança salva sozinha, sem botão "Salvar" separado.
const hours = reactive({
    enforced: props.businessHoursEnforced,
    days: props.dayKeys.reduce((acc, day) => {
        acc[day] = { ...props.businessHours[day] }
        return acc
    }, {}),
    saving: false,
})

async function persistHours() {
    hours.saving = true
    try {
        const payload = {
            enforced: hours.enforced,
            days: Object.fromEntries(props.dayKeys.map((day) => [day, hours.days[day]])),
        }
        await window.axios.put(route('clinic-settings.agendas.business-hours'), payload)
        toast.success('Horário de funcionamento atualizado.')
        emit('business-hours-updated', {
            businessHours: JSON.parse(JSON.stringify(hours.days)),
            enforced: hours.enforced,
        })
    } catch (e) {
        toast.error(firstErrorMessage(e) || 'Não foi possível salvar. Tente novamente.')
    } finally {
        hours.saving = false
    }
}

function toggleEnforced() {
    if (!props.canManage || hours.saving) return
    hours.enforced = !hours.enforced
    persistHours()
}

function toggleDayEnabled(day) {
    if (!props.canManage || hours.saving) return
    hours.days[day].enabled = !hours.days[day].enabled
    persistHours()
}

function onHoursChange(day) {
    if (!props.canManage || hours.saving) return
    persistHours()
}
</script>

<template>
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5"
     :class="{ 'opacity-60': hours.saving || holidays.saving }">

    <div class="flex items-center gap-1.5">
        <LockClosedIcon class="w-3.5 h-3.5 text-slate-400" />
        <h3 class="text-sm font-semibold text-slate-800">Regras da clínica</h3>
    </div>
    <p class="text-xs text-slate-500 mt-1">
        Defina regras gerais que podem prevalecer sobre as agendas individuais.
    </p>

    <!-- Feriados -->
    <div class="mt-4 pt-4 border-t border-slate-100">
        <label class="flex items-center justify-between gap-3"
               :class="canManage ? 'cursor-pointer' : 'opacity-60'">
            <span>
                <span class="text-xs font-semibold text-slate-700 block">Feriados nacionais</span>
                <span class="text-[11px] text-slate-500">
                    Considerar os feriados nacionais do Brasil como dias sem atendimento para toda a clínica.
                </span>
            </span>
            <button type="button" role="switch" :aria-checked="holidays.value"
                    :disabled="!canManage || holidays.saving"
                    @click="toggleHolidays"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0 disabled:cursor-not-allowed"
                    :class="holidays.value ? 'bg-emerald-600' : 'bg-slate-300'">
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                      :class="holidays.value ? 'translate-x-[19px]' : 'translate-x-1'" />
            </button>
        </label>
        <p v-if="holidays.value" class="mt-1.5 flex items-center gap-1 text-[10px] font-semibold text-amber-700">
            <LockClosedIcon class="w-3 h-3 shrink-0" /> Regra obrigatória para toda a clínica
        </p>
    </div>

    <!-- Horário de funcionamento -->
    <div class="mt-4 pt-4 border-t border-slate-100">
        <span class="text-xs font-semibold text-slate-700 block">Horário de funcionamento</span>
        <p class="text-[11px] text-slate-500 mt-0.5 mb-3">
            Horário por dia da semana. Sábado e domingo podem ser marcados como "não trabalha".
        </p>

        <div class="space-y-1.5">
            <div v-for="day in dayKeys" :key="day" class="flex items-center gap-1.5 text-xs">
                <span class="w-7 shrink-0 text-slate-500 font-medium">{{ DAY_LABELS[day] }}</span>
                <button type="button" :disabled="!canManage || hours.saving"
                        @click="toggleDayEnabled(day)"
                        class="px-2 py-0.5 rounded-full border text-[10px] font-medium shrink-0 transition-colors disabled:cursor-not-allowed"
                        :class="hours.days[day].enabled
                            ? 'bg-emerald-600 text-white border-emerald-600'
                            : 'text-slate-400 border-slate-200'">
                    {{ hours.days[day].enabled ? 'Trabalha' : 'Não trabalha' }}
                </button>
                <template v-if="hours.days[day].enabled">
                    <input type="time" v-model="hours.days[day].start" :disabled="!canManage || hours.saving"
                           @change="onHoursChange(day)"
                           class="rounded-lg border border-slate-200 px-1.5 py-1 text-[11px] text-slate-700 w-[76px] disabled:cursor-not-allowed disabled:opacity-60" />
                    <span class="text-slate-300">–</span>
                    <input type="time" v-model="hours.days[day].end" :disabled="!canManage || hours.saving"
                           @change="onHoursChange(day)"
                           class="rounded-lg border border-slate-200 px-1.5 py-1 text-[11px] text-slate-700 w-[76px] disabled:cursor-not-allowed disabled:opacity-60" />
                </template>
                <span v-else class="text-[11px] text-slate-400">—</span>
            </div>
        </div>

        <label class="mt-3.5 flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2"
               :class="canManage ? 'cursor-pointer' : 'opacity-60'">
            <span class="text-[11px] text-slate-600">
                <span class="font-semibold block">Regra obrigatória</span>
                Quando obrigatória, esta regra prevalece sobre a configuração individual dos profissionais.
            </span>
            <button type="button" role="switch" :aria-checked="hours.enforced"
                    :disabled="!canManage || hours.saving"
                    @click="toggleEnforced"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0 disabled:cursor-not-allowed"
                    :class="hours.enforced ? 'bg-amber-600' : 'bg-slate-300'">
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                      :class="hours.enforced ? 'translate-x-[19px]' : 'translate-x-1'" />
            </button>
        </label>
        <p v-if="hours.enforced" class="mt-1.5 flex items-center gap-1 text-[10px] font-semibold text-amber-700">
            <LockClosedIcon class="w-3 h-3 shrink-0" /> Regra obrigatória para toda a clínica
        </p>
    </div>

    <p v-if="!canManage" class="mt-4 pt-3 border-t border-slate-100 text-[10px] text-slate-400">
        Somente administradores da clínica podem alterar essas regras.
    </p>
</div>
</template>
