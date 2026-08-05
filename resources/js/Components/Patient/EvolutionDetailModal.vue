<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Modal from '@/Components/UI/Modal.vue'
import AnamnesisSignatureModal from '@/Components/Anamnesis/AnamnesisSignatureModal.vue'
import DriveDisasterRecoveryModal from '@/Components/DriveDisasterRecoveryModal.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    evolution: { type: Object, required: true },
    patientName: { type: String, default: '' },
    clinicName: { type: String, default: '' },
    doctorName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'signed'])

const page = usePage()

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
}) : '—'

// Três estados possíveis pra uma foto de evolução:
// - "uploading": salva localmente, enviando pro Drive em segundo plano
//   (ver UploadEvolutionPhotoJob) — ainda não confirmou sucesso nem falha.
// - "pending": falhou (job ou retry síncrono) — fica guardada localmente pra
//   reenvio, nunca some (ver PatientEvolutionController::store()/retryPhoto()).
// - qualquer outro valor ("active"): subiu com sucesso, tem drive_file_id.
const uploadedPhotos = computed(() => (props.evolution.photos ?? []).filter(p => p.status !== 'pending' && p.status !== 'uploading'))
const uploadingPhotos = computed(() => (props.evolution.photos ?? []).filter(p => p.status === 'uploading'))
const pendingPhotos = computed(() => (props.evolution.photos ?? []).filter(p => p.status === 'pending'))

// ── Retry de fotos pendentes ─────────────────────────────────────────────────
const retryingId = ref(null)
const showRecoveryModal = ref(false)
const recoveryModalRef = ref(null)
const recoveringPhoto = ref(null)

function retryPhoto(photo) {
    // Causa já conhecida (falhou no upload original por estrutura ausente) —
    // vai direto pro fluxo de recriação, sem gastar uma tentativa que já
    // sabemos que vai falhar de novo (ver PatientEvolutionController::store()).
    if (photo.failure_reason === 'drive_structure_missing') {
        recoveringPhoto.value = photo
        showRecoveryModal.value = true
        return
    }

    retryingId.value = photo.id
    router.post(route('patients.evolutions.photos.retry', [props.patientId, photo.id]), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.disaster_recovery_required) {
                recoveringPhoto.value = photo
                showRecoveryModal.value = true
            }
        },
        onFinish: () => { retryingId.value = null },
    })
}

function onConfirmRecovery() {
    if (!recoveringPhoto.value) return
    router.post(
        route('patients.evolutions.photos.retry', [props.patientId, recoveringPhoto.value.id]),
        { authorize_structure_recovery: true },
        {
            preserveScroll: true,
            onSuccess: () => recoveryModalRef.value?.finish(true),
            onError:   () => recoveryModalRef.value?.finish(false),
        }
    )
}

function onRecoveryClosed() {
    showRecoveryModal.value = false
    recoveringPhoto.value = null
}

// ── Assinatura (paciente/responsável, presencial via canvas) ────────────────
const showSignatureModal = ref(false)
const signError = ref('')
const localSignature = ref(props.evolution.signature || null)

watch(() => props.evolution.id, () => {
    localSignature.value = props.evolution.signature || null
})

const isSigned = computed(() => localSignature.value !== null)

async function submitSignature(formData) {
    signError.value = ''
    try {
        const { data } = await axios.post(
            route('patients.evolutions.signature.store', [props.patientId, props.evolution.id]),
            formData
        )
        localSignature.value = data.signature
        showSignatureModal.value = false
        emit('signed')
    } catch (e) {
        signError.value = e?.response?.data?.error || 'Não foi possível registrar a assinatura.'
    }
}
</script>

<template>
<Modal :show="show" max-width="max-w-2xl" title="Evolução clínica" @close="$emit('close')">
    <div class="p-5 space-y-4">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-sm font-semibold text-slate-800">{{ fmtDate(evolution.recorded_at) }}</p>
                <p class="text-xs text-slate-500">{{ evolution.professional?.name || '—' }}</p>
            </div>
            <span v-if="evolution.signature_required && isSigned"
                  class="text-[11px] px-2.5 py-1 rounded-full font-medium bg-teal-50 text-teal-700 border border-teal-200">
                Assinado
            </span>
            <span v-else-if="evolution.signature_required"
                  class="text-[11px] px-2.5 py-1 rounded-full font-medium bg-amber-50 text-amber-700 border border-amber-200">
                Assinatura pendente
            </span>
            <span v-else class="text-[11px] px-2.5 py-1 rounded-full font-medium bg-slate-100 text-slate-500">
                Esta evolução não requereu assinatura
            </span>
        </div>

        <div class="prose prose-sm max-w-none border-t border-slate-100 pt-4" v-html="evolution.content"></div>

        <div v-if="evolution.signature_required" class="border-t border-slate-100 pt-4">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">Assinatura</p>
            <div v-if="isSigned" class="flex items-center gap-3">
                <div class="w-32 h-12 rounded-lg border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center shrink-0">
                    <img v-if="localSignature.signature_url" :src="localSignature.signature_url"
                         alt="Assinatura" class="max-w-full max-h-full object-contain p-1" />
                </div>
                <div class="text-xs text-slate-600 min-w-0">
                    <p class="font-semibold text-slate-800 truncate">{{ localSignature.patient_name }}</p>
                    <p>{{ fmtDateTime(localSignature.signed_at) }}</p>
                </div>
            </div>
            <button v-else type="button" @click="showSignatureModal = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold transition-colors">
                Assinar agora
            </button>
        </div>

        <div v-if="uploadedPhotos.length" class="border-t border-slate-100 pt-4">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">
                Fotos anexadas ({{ uploadedPhotos.length }})
            </p>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                <a v-for="photo in uploadedPhotos" :key="photo.id"
                   :href="route('patients.photos.view', [patientId, photo.id])" target="_blank" rel="noopener"
                   class="block aspect-square rounded-lg overflow-hidden border border-slate-200 hover:opacity-80 transition-opacity">
                    <img :src="route('patients.photos.view', [patientId, photo.id])" :alt="photo.filename"
                         class="w-full h-full object-cover" loading="lazy" />
                </a>
            </div>
        </div>

        <div v-if="uploadingPhotos.length" class="border-t border-slate-100 pt-4">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">
                Enviando ({{ uploadingPhotos.length }})
            </p>
            <ul class="space-y-1.5">
                <li v-for="photo in uploadingPhotos" :key="photo.id"
                    class="flex items-center gap-2 text-xs bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                    <svg class="w-3.5 h-3.5 animate-spin text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span class="truncate text-slate-500">{{ photo.filename }} — enviando para o Google Drive...</span>
                </li>
            </ul>
        </div>

        <div v-if="pendingPhotos.length" class="border-t border-slate-100 pt-4">
            <p class="text-xs font-bold text-amber-500 uppercase tracking-wider mb-2.5">
                Fotos pendentes de envio ({{ pendingPhotos.length }})
            </p>
            <ul class="space-y-1.5">
                <li v-for="photo in pendingPhotos" :key="photo.id"
                    class="flex items-center justify-between gap-2 text-xs bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                    <div class="min-w-0">
                        <span class="truncate text-amber-800 block">{{ photo.filename }}</span>
                        <span v-if="photo.failure_reason === 'drive_structure_missing'" class="text-[10px] text-red-600">
                            Estrutura de pastas do paciente não encontrada no Google Drive.
                        </span>
                        <span v-else-if="photo.failure_reason === 'drive_reauth_required'" class="text-[10px] text-red-600">
                            A conexão com o Google Drive precisa ser renovada.
                        </span>
                    </div>
                    <button v-if="photo.failure_reason === 'drive_structure_missing'"
                            type="button" @click="retryPhoto(photo)"
                            class="shrink-0 font-semibold text-red-700 hover:text-red-900 transition-colors">
                        Recriar estrutura
                    </button>
                    <a v-else-if="photo.failure_reason === 'drive_reauth_required'"
                       :href="route('google.connect')"
                       class="shrink-0 font-semibold text-red-700 hover:text-red-900 transition-colors">
                        Reconectar Drive
                    </a>
                    <button v-else type="button" @click="retryPhoto(photo)" :disabled="retryingId === photo.id"
                            class="shrink-0 font-semibold text-teal-700 hover:text-teal-900 disabled:opacity-50 transition-colors">
                        {{ retryingId === photo.id ? 'Enviando...' : 'Reenviar' }}
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <template #footer>
        <button type="button" @click="$emit('close')"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
            Fechar
        </button>
    </template>
</Modal>

<AnamnesisSignatureModal
    :show="showSignatureModal"
    title="Assinar Evolução Clínica"
    :patient-name="patientName"
    :server-error="signError"
    @close="showSignatureModal = false"
    @signed="submitSignature" />

<DriveDisasterRecoveryModal
    ref="recoveryModalRef"
    :show="showRecoveryModal"
    :clinic-name="clinicName"
    :doctor-name="doctorName"
    :patient-full-name="patientName"
    @close="onRecoveryClosed"
    @confirm="onConfirmRecovery" />
</template>
