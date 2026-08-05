<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import DocumentStatusBadge from '@/Components/Documents/DocumentStatusBadge.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    patient: Object,
    documentHub: Object,
})

const showNew = ref(false)
const form = useForm({ template_id: '', treatment_id: '' })

const issueDocument = () => {
    form.post(route('patients.documents.store', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => { showNew.value = false; form.reset() },
    })
}

const changePage = (page) => {
    router.visit(route('patients.show', props.patient.id), {
        data: { documents_page: page, tab: 'documents' },
        only: ['documentHub'],
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
                <h3 class="text-base font-bold text-slate-900">Documentos</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ documentHub?.pagination?.total ?? documentHub?.documents?.length ?? 0 }} documento(s) emitido(s)</p>
            </div>
            <button
                @click="showNew = !showNew"
                class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition-colors shadow-sm"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Emitir Documento
            </button>
        </div>

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
                    <option value="">Escolha um modelo de documento…</option>
                    <option v-for="t in documentHub?.templates" :key="t.id" :value="t.id">
                        {{ t.category ? `${t.category} — ` : '' }}{{ t.name }}
                    </option>
                </select>
                <InputError :message="form.errors.template_id" />
                <label v-if="documentHub?.treatments?.length" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Vincular a um tratamento (opcional)</label>
                <select
                    v-if="documentHub?.treatments?.length"
                    v-model="form.treatment_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm mb-3 outline-none focus:border-teal-400 bg-white"
                >
                    <option value="">Nenhum</option>
                    <option v-for="t in documentHub.treatments" :key="t.id" :value="t.id">{{ t.nome }}</option>
                </select>
                <InputError :message="form.errors.treatment_id" />
                <div class="flex gap-2">
                    <button
                        @click="issueDocument"
                        :disabled="!form.template_id || form.processing"
                        class="rounded-xl bg-teal-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 hover:bg-teal-700 transition-colors"
                    >Emitir</button>
                    <button
                        @click="showNew = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors"
                    >Cancelar</button>
                </div>
            </div>
        </Transition>

        <!-- Empty state -->
        <div v-if="!documentHub?.documents?.length" class="rounded-2xl border border-dashed border-slate-200 py-16 text-center">
            <div class="text-3xl mb-3">🗂️</div>
            <p class="text-sm font-medium text-slate-600">Nenhum documento emitido.</p>
            <p class="text-[11px] text-slate-400 mt-1">Clique em Emitir Documento para começar.</p>
        </div>

        <!-- Document list -->
        <div v-else class="space-y-2.5">
            <div
                v-for="item in documentHub.documents"
                :key="item.id"
                class="group rounded-2xl border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm transition-all duration-150 overflow-hidden"
            >
                <div class="px-4 py-3.5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-slate-900 text-sm truncate">{{ item.template_name }}</p>
                                <span v-if="item.category" class="text-[9px] text-slate-500 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded font-medium">{{ item.category }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                {{ item.professional || '—' }} · {{ item.issued_at }} · {{ item.document_code }}
                            </p>
                        </div>

                        <DocumentStatusBadge
                            :status="item.status"
                            :status-label="item.status_label"
                            :status-icon="item.status_icon"
                            :status-color="item.status_color"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap gap-0 border-t border-slate-100 bg-slate-50/50">
                    <a
                        :href="route('patients.documents.show', [patient.id, item.id])"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100"
                    >Ver detalhes</a>
                    <a
                        v-if="item.pdf_available"
                        :href="route('patients.documents.pdf', [patient.id, item.id])"
                        target="_blank"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors"
                    >PDF</a>
                    <button
                        v-else
                        @click="router.get(route('patients.documents.pdf', [patient.id, item.id]))"
                        class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors"
                    >Gerar PDF</button>
                </div>
            </div>
        </div>

        <Pagination v-if="documentHub?.pagination" :pagination="documentHub.pagination" @change="changePage" />
    </div>
</template>
