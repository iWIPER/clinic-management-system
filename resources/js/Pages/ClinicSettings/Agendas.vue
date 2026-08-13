<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsTabs from '@/Components/ClinicSettings/SettingsTabs.vue'
import ClinicRulesPanel from '@/Components/ClinicSettings/ClinicRulesPanel.vue'
import { LockClosedIcon } from '@heroicons/vue/20/solid'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    professionals: { type: Array, default: () => [] },
    dayKeys: { type: Array, default: () => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] },
    considerNationalHolidays: { type: Boolean, default: false },
    businessHours: { type: Object, default: () => ({}) },
    businessHoursEnforced: { type: Boolean, default: false },
    canManageClinicRules: { type: Boolean, default: false },
})

const toast = useToast()

const DAY_LABELS = { mon: 'Seg', tue: 'Ter', wed: 'Qua', thu: 'Qui', fri: 'Sex', sat: 'Sáb', sun: 'Dom' }

// Cópia local reativa — cada card salva a própria alteração assim que o
// usuário mexe num toggle (sem um botão "Salvar" separado por card).
const list = reactive(props.professionals.map((p) => ({
    ...p,
    working_days: { ...p.working_days },
    working_hours: { ...p.working_hours },
    saving: false,
})))

// Cópia local das regras da clínica — atualizada via eventos emitidos pelo
// ClinicRulesPanel depois de cada salvamento bem-sucedido, só pra decidir
// o "cadeado" visual nos dias bloqueados dos cards de profissional abaixo
// (a fonte de verdade de leitura/escrita continua sendo o próprio painel).
const clinicRules = reactive({
    businessHours: { ...props.businessHours },
    businessHoursEnforced: props.businessHoursEnforced,
})

function isDayLockedByClinic(day) {
    return clinicRules.businessHoursEnforced && clinicRules.businessHours[day]?.enabled === false
}

async function persist(prof) {
    prof.saving = true
    try {
        await window.axios.put(route('clinic-settings.agendas.update', prof.id), {
            agenda_visible_to_team: prof.agenda_visible_to_team,
            working_days: prof.working_days,
            working_start: prof.working_hours.start,
            working_end: prof.working_hours.end,
        })
        toast.success('Configurações de agenda atualizadas.')
    } catch (e) {
        const errors = e.response?.data?.errors
        toast.error(errors?.working_end?.[0] || errors?.working_days?.[0] || 'Não foi possível salvar. Tente novamente.')
    } finally {
        prof.saving = false
    }
}

function toggleVisibility(prof) {
    if (!prof.can_edit || prof.saving) return
    prof.agenda_visible_to_team = !prof.agenda_visible_to_team
    persist(prof)
}

function toggleDay(prof, day) {
    if (!prof.can_edit || prof.saving || isDayLockedByClinic(day)) return
    prof.working_days[day] = !prof.working_days[day]
    persist(prof)
}

function onWorkingHoursChange(prof) {
    if (!prof.can_edit || prof.saving) return
    persist(prof)
}
</script>

<template>
<AppLayout>
    <SettingsTabs active="agendas" />

    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Agendas</h2>
        <p class="text-sm text-slate-500 mt-1 max-w-2xl">
            Cada profissional decide em quais dias e horários atende. Quando a clínica ativa uma
            regra obrigatória, ela prevalece sobre a configuração individual, sem apagar o que o
            profissional já configurou.
        </p>
    </div>

    <!-- Duas colunas no desktop: profissionais (maior, à esquerda) e regras
         da clínica (compacta, à direita) — ordem invertida no mobile
         (regras primeiro) via order-*, sem duplicar markup por breakpoint. -->
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-4 items-start">

        <!-- Agendas dos profissionais -->
        <div class="order-2 lg:order-1 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Agendas dos profissionais</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Configure a disponibilidade individual de cada profissional.
                </p>
            </div>

            <div v-for="prof in list" :key="prof.id"
                 class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5"
                 :class="{ 'opacity-60': prof.saving }">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-sm font-semibold text-slate-800">{{ prof.name }}</span>
                    <span v-if="prof.is_current_user"
                          class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                        Você
                    </span>
                </div>

                <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3.5 py-2.5"
                       :class="prof.can_edit ? 'cursor-pointer' : 'opacity-60'">
                    <span>
                        <span class="text-sm text-slate-700 font-medium block">Disponibilizar minha agenda para a equipe</span>
                        <span class="text-xs text-slate-400">Quando ativado, outros profissionais autorizados da clínica poderão visualizar sua agenda.</span>
                    </span>
                    <button type="button" role="switch" :aria-checked="prof.agenda_visible_to_team" :disabled="!prof.can_edit || prof.saving"
                            @click="toggleVisibility(prof)"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0 disabled:cursor-not-allowed"
                            :class="prof.agenda_visible_to_team ? 'bg-emerald-600' : 'bg-slate-300'">
                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                              :class="prof.agenda_visible_to_team ? 'translate-x-[19px]' : 'translate-x-1'" />
                    </button>
                </label>

                <div class="mt-4">
                    <div class="text-xs font-medium text-slate-600 mb-2">Dias de atendimento</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="day in dayKeys" :key="day" type="button"
                                :disabled="!prof.can_edit || prof.saving || isDayLockedByClinic(day)"
                                :title="isDayLockedByClinic(day) ? 'Bloqueado pela clínica (regra obrigatória)' : undefined"
                                @click="toggleDay(prof, day)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full border transition-colors disabled:cursor-not-allowed"
                                :class="isDayLockedByClinic(day)
                                    ? 'text-slate-300 border-slate-100 bg-slate-50'
                                    : (prof.working_days[day]
                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                        : 'text-slate-500 border-slate-200 hover:bg-slate-50')">
                            <LockClosedIcon v-if="isDayLockedByClinic(day)" class="w-2.5 h-2.5" />
                            {{ DAY_LABELS[day] }}
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-xs font-medium text-slate-600 mb-2">Horário de atendimento</div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">Início</span>
                            <input type="time" v-model="prof.working_hours.start" :disabled="!prof.can_edit || prof.saving"
                                   @change="onWorkingHoursChange(prof)"
                                   class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-60" />
                        </label>
                        <label class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">Fim</span>
                            <input type="time" v-model="prof.working_hours.end" :disabled="!prof.can_edit || prof.saving"
                                   @change="onWorkingHoursChange(prof)"
                                   class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-60" />
                        </label>
                    </div>
                    <p v-if="clinicRules.businessHoursEnforced" class="text-[11px] text-amber-600 mt-1.5 flex items-center gap-1">
                        <LockClosedIcon class="w-3 h-3 shrink-0" />
                        A clínica pode limitar este horário — ver "Regras da clínica".
                    </p>
                </div>
            </div>

            <p v-if="!list.length" class="text-sm text-slate-400 py-8 text-center">
                Nenhum profissional com agenda de atendimento nesta clínica ainda.
            </p>
        </div>

        <!-- Regras da clínica -->
        <div class="order-1 lg:order-2">
            <ClinicRulesPanel
                :can-manage="canManageClinicRules"
                :consider-national-holidays="considerNationalHolidays"
                :business-hours="businessHours"
                :business-hours-enforced="businessHoursEnforced"
                :day-keys="dayKeys"
                @business-hours-updated="({ businessHours: bh, enforced }) => {
                    clinicRules.businessHours = bh
                    clinicRules.businessHoursEnforced = enforced
                }"
            />
        </div>
    </div>
</AppLayout>
</template>
