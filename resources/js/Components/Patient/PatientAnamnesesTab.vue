<script setup>
import InputError from '@/Components/InputError.vue'
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    patient: Object,
    anamnesisHub: Object,
})

const showNew   = ref(false)
const form      = useForm({ template_id: '' })

// Estado do modal de confirmação de exclusão
const deleteTarget  = ref(null) // item sendo excluído
const deleteError   = ref('')
const deleteLoading = ref(false)

const STATUS_STYLES = {
    draft:              'bg-slate-100 text-slate-600 border-slate-200',
    in_progress:        'bg-blue-50 text-blue-700 border-blue-100',
    completed:          'bg-emerald-50 text-emerald-700 border-emerald-100',
    awaiting_signature: 'bg-amber-50 text-amber-700 border-amber-100',
    signed:             'bg-amber-50 text-amber-700 border-amber-100',
    fully_signed:       'bg-teal-50 text-teal-700 border-teal-100',
    cancelled:          'bg-red-50 text-red-700 border-red-100',
}

const STATUS_ICONS = {
    draft:              '○',
    in_progress:        '◔',
    completed:          '✔',
    awaiting_signature: '⌛',
    signed:             '✎',
    fully_signed:       '✔',
    cancelled:          '✖',
}

// Computa o badge de assinatura inteligente
const signatureBadge = (item) => {
    const hasP = item.has_patient_signature
    const hasD = item.has_dentist_signature

    if (!hasP && !hasD) return { label: 'Sem assinaturas',                    cls: 'bg-slate-100 text-slate-500 border-slate-200' }
    if (hasP && hasD)   return { label: 'Documento completamente assinado',   cls: 'bg-teal-50 text-teal-700 border-teal-200' }
    if (hasP && !hasD)  return { label: 'Falta assinatura do dentista',       cls: 'bg-amber-50 text-amber-700 border-amber-200' }
    /* !hasP && hasD */  return { label: 'Falta assinatura do paciente',       cls: 'bg-amber-50 text-amber-700 border-amber-200' }
}

const createAnamnesis = () => {
    form.post(route('patients.anamneses.create', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => { showNew.value = false; form.reset() },
    })
}

const openDelete = (item) => {
    deleteTarget.value  = item
    deleteError.value   = ''
    deleteLoading.value = false
}

const confirmDelete = async () => {
    if (!deleteTarget.value) return
    deleteLoading.value = true
    deleteError.value   = ''
    try {
        await axios.delete(route('patients.anamneses.destroy', [props.patient.id, deleteTarget.value.id]))
        deleteTarget.value = null
        router.reload({ only: ['patient', 'anamnesisHub'] })
    } catch (e) {
        deleteError.value   = e?.response?.data?.error || 'Erro ao excluir. Tente novamente.'
        deleteLoading.value = false
    }
}

const changePage = (page) => {
    router.visit(route('patients.show', props.patient.id), {
        data:          { anamneses_page: page },
        only:          ['anamnesisHub'],
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-slate-900">Anamneses</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ anamnesisHub?.pagination?.total ?? anamnesisHub?.instances?.length ?? 0 }} registro(s)</p>
            </div>
            <button
                @click="showNew = !showNew"
                class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition-colors shadow-sm"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nova Anamnese
            </button>
        </div>

        <!-- New anamnesis form -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            leave-active-class="transition-all duration-150 ease-in"
            enter-from-class="opacity-0 -translate-y-1"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="showNew" class="mb-5 rounded-2xl border border-teal-100 bg-teal-50/40 p-4">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Selecionar modelo</label>
                <select
                    v-model="form.template_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm mb-3 outline-none focus:border-teal-400 bg-white"
                >
                    <option value="">Escolha um modelo de anamnese…</option>
                    <option v-for="t in anamnesisHub?.templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div class="flex gap-2">
                    <button
                        @click="createAnamnesis"
                        :disabled="!form.template_id || form.processing"
                        class="rounded-xl bg-teal-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 hover:bg-teal-700 transition-colors"
                    >Iniciar</button>
                    <button
                        @click="showNew = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors"
                    >Cancelar</button>
                </div>
            </div>
        </Transition>

        <!-- Empty state -->
        <div v-if="!anamnesisHub?.instances?.length" class="rounded-2xl border border-dashed border-slate-200 py-16 text-center">
            <div class="text-3xl mb-3">📋</div>
            <p class="text-sm font-medium text-slate-600">Nenhuma anamnese registrada.</p>
            <p class="text-[11px] text-slate-400 mt-1">Clique em Nova Anamnese para começar.</p>
        </div>

        <!-- Anamnesis list -->
        <div v-else class="space-y-2.5">
            <div
                v-for="item in anamnesisHub?.instances"
                :key="item.id"
                class="group rounded-2xl border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm transition-all duration-150 overflow-hidden"
            >
                <!-- Card body -->
                <div class="px-4 py-3.5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-slate-900 text-sm truncate">
                                    {{ item.display_name || item.template_name }}
                                </p>
                                <span
                                    v-if="item.display_name && item.display_name !== item.template_name"
                                    class="text-[9px] text-teal-600 bg-teal-50 border border-teal-100 px-1.5 py-0.5 rounded font-medium"
                                >renomeada</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                {{ item.professional || '—' }} · {{ item.date }} às {{ item.time }}
                            </p>
                        </div>

                        <!-- Status badge do documento -->
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold shrink-0"
                            :class="STATUS_STYLES[item.status] || STATUS_STYLES.draft"
                        >
                            <span class="text-[9px]">{{ STATUS_ICONS[item.status] || '○' }}</span>
                            {{ item.status_label }}
                        </span>
                    </div>

                    <!-- Progress bar (apenas quando em andamento ou concluído) -->
                    <div v-if="item.status === 'in_progress' || item.status === 'completed'" class="mt-2.5 flex items-center gap-2">
                        <div class="flex-1 h-1 rounded-full bg-slate-100 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="item.progress >= 100 ? 'bg-emerald-400' : 'bg-teal-400'"
                                :style="{ width: item.progress + '%' }"
                            />
                        </div>
                        <span class="text-[10px] text-slate-400 tabular-nums">{{ item.progress }}%</span>
                    </div>

                    <!-- Badge de assinatura inteligente + indicadores individuais -->
                    <div v-if="item.status !== 'draft' && item.status !== 'in_progress'" class="mt-2.5 space-y-1.5">
                        <!-- Badge composto -->
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold"
                            :class="signatureBadge(item).cls"
                        >
                            {{ signatureBadge(item).label }}
                        </span>

                        <!-- Indicadores individuais -->
                        <div class="flex flex-wrap gap-x-4 gap-y-0.5">
                            <span class="flex items-center gap-1 text-[10px]" :class="item.has_patient_signature ? 'text-teal-600' : 'text-slate-400'">
                                <span>{{ item.has_patient_signature ? '✔' : '○' }}</span>
                                Paciente {{ item.has_patient_signature ? 'assinou' : 'não assinou' }}
                            </span>
                            <span class="flex items-center gap-1 text-[10px]" :class="item.has_dentist_signature ? 'text-teal-600' : 'text-slate-400'">
                                <span>{{ item.has_dentist_signature ? '✔' : '○' }}</span>
                                Dentista {{ item.has_dentist_signature ? 'assinou' : 'não assinou' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions row -->
                <div class="flex flex-wrap gap-0 border-t border-slate-100 bg-slate-50/50">
                    <!-- Botão principal: "Visualizar e assinar" ou "Visualizar documento" ou "Visualizar" -->
                    <a
                        v-if="item.has_patient_signature && !item.has_dentist_signature"
                        :href="route('patients.anamneses.show', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50/60 transition-colors border-r border-slate-100"
                    >✍ Visualizar e assinar</a>
                    <a
                        v-else-if="item.is_fully_signed"
                        :href="route('patients.anamneses.show', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-teal-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >📄 Visualizar documento</a>
                    <a
                        v-else
                        :href="route('patients.anamneses.show', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >Visualizar</a>

                    <a
                        :href="route('patients.anamneses.edit', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >Editar</a>
                    <button
                        @click="router.post(route('patients.anamneses.duplicate', [patient.id, item.id]))"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >Duplicar</button>
                    <a
                        :href="route('patients.anamneses.pdf', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >PDF</a>
                    <button
                        @click="openDelete(item)"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-red-400 hover:text-red-600 hover:bg-red-50/50 transition-colors"
                    >Excluir</button>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <Pagination v-if="anamnesisHub?.pagination"
                    :pagination="anamnesisHub.pagination"
                    @change="changePage" />

        <!-- Modal de confirmação de exclusão -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                leave-active-class="transition-all duration-150 ease-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="deleteTarget"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                    @click.self="deleteTarget = null"
                >
                    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-100">

                        <!-- Bloqueado: paciente já assinou -->
                        <template v-if="deleteTarget.has_patient_signature">
                            <div class="px-6 pt-5 pb-4 border-b border-slate-100">
                                <h2 class="text-base font-bold text-slate-900">Exclusão não permitida</h2>
                            </div>
                            <div class="px-6 py-4 space-y-3">
                                <div class="rounded-xl border border-red-100 bg-red-50/50 px-4 py-3">
                                    <p class="text-[13px] text-red-700 font-medium">Esta anamnese já foi assinada pelo paciente.</p>
                                    <p class="text-[12px] text-red-600 mt-1">Por segurança jurídica, ela não pode mais ser excluída.</p>
                                    <p class="text-[12px] text-red-600 mt-1">Caso necessário, cancele a anamnese e crie uma nova versão.</p>
                                </div>
                            </div>
                            <div class="flex justify-end px-6 pb-5">
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 transition-colors border border-slate-200"
                                    @click="deleteTarget = null"
                                >Entendido</button>
                            </div>
                        </template>

                        <!-- Permitido: sem assinatura do paciente -->
                        <template v-else>
                            <div class="px-6 pt-5 pb-4 border-b border-slate-100">
                                <h2 class="text-base font-bold text-slate-900">Excluir anamnese</h2>
                            </div>
                            <div class="px-6 py-4 space-y-3">
                                <p class="text-[13px] text-slate-700">
                                    Você está prestes a excluir <strong>{{ deleteTarget.display_name || deleteTarget.template_name }}</strong>.
                                </p>
                                <div
                                    v-if="deleteTarget.has_dentist_signature"
                                    class="rounded-xl border border-amber-100 bg-amber-50/50 px-4 py-3"
                                >
                                    <p class="text-[12px] text-amber-700">A assinatura do dentista será perdida. Deseja realmente excluir?</p>
                                </div>
                                <p v-else class="text-[12px] text-slate-500">Esta ação não pode ser desfeita.</p>
                                <InputError :message="deleteError" />
                            </div>
                            <div class="flex items-center justify-end gap-2 px-6 pb-5">
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 transition-colors border border-slate-200"
                                    @click="deleteTarget = null"
                                >Cancelar</button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-red-600 px-5 py-2 text-[12px] font-semibold text-white hover:bg-red-700 transition-colors disabled:opacity-50 shadow-sm"
                                    :disabled="deleteLoading"
                                    @click="confirmDelete"
                                >
                                    <span v-if="deleteLoading" class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        Excluindo…
                                    </span>
                                    <span v-else>Excluir definitivamente</span>
                                </button>
                            </div>
                        </template>

                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
