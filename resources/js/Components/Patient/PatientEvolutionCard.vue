<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import AddEvolutionModal from './AddEvolutionModal.vue'
import EvolutionDetailModal from './EvolutionDetailModal.vue'

const props = defineProps({
    patient: { type: Object, required: true },
    evolutionsHub: { type: Object, default: () => ({ data: [], pagination: null }) },
    professionals: { type: Array, default: () => [] },
    isDriveConnected: { type: Boolean, default: false },
    clinicName: { type: String, default: '' },
    doctorName: { type: String, default: '' },
})

const patientFullName = computed(() => `${props.patient.nome} ${props.patient.sobrenome}`.trim())

const showAddModal = ref(false)
const showDetailModal = ref(false)
const expanded = ref(false)

// O card sempre mostra 1 evolução por vez (a mais recente por padrão) — a
// navegação entre as demais é só pela paginação abaixo (per_page=1 já vem
// pronto do backend, ver PatientController::show()).
const current = computed(() => props.evolutionsHub.data[0] ?? null)
const hasAny = computed(() => (props.evolutionsHub.pagination?.total ?? 0) > 0)

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'

// Prévia em texto puro — o conteúdo é HTML (editor rico), então truncar a
// string crua poderia cortar uma tag no meio. Strip simples só pra prévia;
// o modal de detalhe sempre renderiza o HTML completo.
function stripHtml(html) {
    const div = document.createElement('div')
    div.innerHTML = html || ''
    return (div.textContent || div.innerText || '').trim()
}
const preview = computed(() => {
    const text = stripHtml(current.value?.content)
    if (text.length <= 220) return { text, truncated: false }
    return { text: text.slice(0, 220).trim() + '…', truncated: true }
})

const photoCount = computed(() => (current.value?.photos ?? []).filter(p => p.status !== 'pending' && p.status !== 'uploading').length)
const pendingPhotoCount = computed(() => (current.value?.photos ?? []).filter(p => p.status === 'pending').length)
const uploadingPhotoCount = computed(() => (current.value?.photos ?? []).filter(p => p.status === 'uploading').length)

function changePage(page) {
    router.visit(route('patients.show', props.patient.id), {
        data: { evolutions_page: page },
        only: ['evolutionsHub'],
        preserveState: true,
        preserveScroll: true,
    })
}

function onAdded() {
    showAddModal.value = false
    // Volta pra página 1 (a mais recente) — se o usuário estava navegando
    // evoluções antigas quando adicionou uma nova, precisa "pular" pra ela.
    router.reload({ only: ['evolutionsHub'], data: { evolutions_page: 1 }, preserveScroll: true })
}

function onSigned() {
    router.reload({ only: ['evolutionsHub'], preserveScroll: true })
}

// Polling condicional — só liga enquanto a evolução atual tiver foto(s) em
// "uploading" (job em segundo plano, ver UploadEvolutionPhotoJob). Esse card
// aparece em toda ficha de paciente, então deixar o polling sempre ligado
// (como em Consultations/Appointments) seria desperdício; aqui ele liga e
// desliga sozinho conforme o estado muda.
let pollTimer = null

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

watch(uploadingPhotoCount, (count) => {
    if (count > 0 && !pollTimer) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['evolutionsHub'], preserveState: true, preserveScroll: true })
        }, 4000)
    } else if (count === 0) {
        stopPolling()
    }
}, { immediate: true })

onUnmounted(stopPolling)
</script>

<template>
<div class="bg-white rounded-2xl border p-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-slate-900">Evoluções</h3>
        <button v-if="hasAny" type="button" @click="showAddModal = true"
                class="text-xs font-semibold text-teal-600 hover:text-teal-800 transition-colors">
            + Nova evolução
        </button>
    </div>

    <!-- Compacto de propósito (card de sidebar, não uma seção de página) —
         o EmptyState.vue genérico é alto demais aqui (feito pra ocupar uma
         seção inteira, ver TreatmentsTable/PatientAnamnesesTab). -->
    <div v-if="!hasAny" class="text-center py-3">
        <p class="text-xs text-slate-400 mb-3">Você ainda não adicionou nenhuma evolução para este paciente.</p>
        <button type="button" @click="showAddModal = true"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition-colors">
            + Adicionar evolução
        </button>
    </div>

    <template v-else-if="current">
        <button type="button" class="block w-full text-left" @click="showDetailModal = true">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <span class="text-xs font-medium text-slate-500">{{ fmtDate(current.recorded_at) }}</span>
                <span v-if="current.signature_required && current.signature"
                      class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-teal-50 text-teal-700 border border-teal-200">
                    Assinado
                </span>
                <span v-else-if="current.signature_required"
                      class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-amber-50 text-amber-700 border border-amber-200">
                    Assinatura pendente
                </span>
            </div>
            <p class="text-xs text-slate-400 mb-1">{{ current.professional?.name || '—' }}</p>
            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                {{ preview.text }}
                <span v-if="preview.truncated" class="text-teal-600 font-medium">Ver mais</span>
            </p>
            <div class="flex items-center gap-2 mt-2">
                <span v-if="photoCount > 0" class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-600">
                    {{ photoCount }} {{ photoCount === 1 ? 'foto' : 'fotos' }}
                </span>
                <span v-if="uploadingPhotoCount > 0" class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-500">
                    <svg class="w-2.5 h-2.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    {{ uploadingPhotoCount }} enviando
                </span>
                <span v-if="pendingPhotoCount > 0" class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-amber-50 text-amber-700 border border-amber-200">
                    {{ pendingPhotoCount }} pendente{{ pendingPhotoCount > 1 ? 's' : '' }} de envio
                </span>
                <span v-if="!current.signature_required" class="text-[10px] text-slate-400">
                    Não requer assinatura
                </span>
            </div>
        </button>

        <Pagination v-if="evolutionsHub.pagination" :pagination="evolutionsHub.pagination" @change="changePage" />
    </template>
</div>

<AddEvolutionModal :show="showAddModal"
    :patient-id="patient.id"
    :professionals="professionals"
    :is-drive-connected="isDriveConnected"
    @close="showAddModal = false"
    @saved="onAdded" />

<EvolutionDetailModal v-if="current"
    :show="showDetailModal"
    :evolution="current"
    :patient-id="patient.id"
    :patient-name="patientFullName"
    :clinic-name="clinicName"
    :doctor-name="doctorName"
    @close="showDetailModal = false"
    @signed="onSigned" />
</template>
