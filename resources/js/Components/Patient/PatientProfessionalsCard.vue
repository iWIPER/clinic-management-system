<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    patient: Object,
    hub: Object,
    responsibleTeam: { type: Array, default: () => [] },
    eligibleProfessionals: { type: Array, default: () => [] },
})

const showResponsibleModal = ref(false)
const selectedResponsibleId = ref(props.patient.responsible_professional?.id ?? null)
const savingResponsible = ref(false)
const responsibleError = ref('')

function fmtDate(iso) {
    if (!iso) return null
    return new Date(iso).toLocaleDateString('pt-BR')
}

function teamUrl(professionalId) {
    return route('team.index', { highlight: professionalId })
}

function saveResponsible() {
    if (!selectedResponsibleId.value) return
    savingResponsible.value = true
    responsibleError.value = ''
    router.put(route('patients.responsible-professional', props.patient.id), {
        responsible_professional_id: selectedResponsibleId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showResponsibleModal.value = false },
        onError: (errors) => {
            responsibleError.value = errors.responsible_professional_id || 'Não foi possível atualizar o profissional responsável.'
        },
        onFinish: () => { savingResponsible.value = false },
    })
}
</script>

<template>
    <div v-if="hub?.professionals" class="bg-white rounded-2xl border p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-900">Profissionais</h3>
            <button @click="responsibleError = ''; showResponsibleModal = true" class="text-xs font-medium text-teal-600 hover:text-teal-700">Alterar</button>
        </div>

        <div class="space-y-4 text-sm">
            <div>
                <p class="text-xs text-slate-500">Responsável atual</p>
                <p v-if="hub.professionals.responsible" class="font-medium text-slate-900">
                    <Link :href="teamUrl(hub.professionals.responsible.id)" class="hover:text-teal-700 hover:underline">
                        {{ hub.professionals.responsible.name }}
                    </Link>
                    <span v-if="hub.professionals.responsible.job_title" class="text-slate-400 font-normal"> — {{ hub.professionals.responsible.job_title }}</span>
                </p>
                <p v-else class="text-slate-400">Nenhum responsável definido</p>
                <p class="text-xs text-slate-400 mt-0.5">desde {{ fmtDate(patient.updated_at) || '—' }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <p class="text-xs text-slate-500">Primeiro atendimento</p>
                    <p class="text-slate-800">{{ hub.professionals.first_attendance?.professional || '—' }}</p>
                    <p v-if="hub.professionals.first_attendance?.date" class="text-xs text-slate-400">{{ fmtDate(hub.professionals.first_attendance.date) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Último atendimento</p>
                    <p class="text-slate-800">{{ hub.professionals.last_attendance?.professional || '—' }}</p>
                    <p v-if="hub.professionals.last_attendance?.date" class="text-xs text-slate-400">{{ fmtDate(hub.professionals.last_attendance.date) }}</p>
                </div>
            </div>

            <div v-if="responsibleTeam.length > 1" class="pt-3 border-t border-slate-100">
                <p class="text-xs text-slate-500 mb-2">Equipe envolvida</p>
                <div class="flex flex-wrap gap-2">
                    <Link v-for="m in responsibleTeam" :key="m.id" :href="teamUrl(m.id)"
                          class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100 hover:text-teal-700">
                        {{ m.name }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Modal: alterar responsável -->
        <Teleport to="body">
            <div v-if="showResponsibleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showResponsibleModal = false">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">Alterar profissional responsável</h3>
                    <select v-model="selectedResponsibleId" class="w-full rounded-lg border px-3 py-2 text-sm">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="p in eligibleProfessionals" :key="p.id" :value="p.id">{{ p.job_title ? `${p.name} — ${p.job_title}` : p.name }}</option>
                    </select>
                    <p v-if="!eligibleProfessionals.length" class="mt-2 text-xs text-slate-400">Nenhum dentista disponível na clínica.</p>
                    <InputError :message="responsibleError" />
                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="showResponsibleModal = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                        <button @click="saveResponsible" :disabled="savingResponsible || !selectedResponsibleId"
                                class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50">
                            {{ savingResponsible ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
