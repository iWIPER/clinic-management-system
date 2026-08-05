<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import DocumentSignatureModal from './DocumentSignatureModal.vue'

const props = defineProps({
    patientId: [Number, String],
    documentId: [Number, String],
    patientName: { type: String, default: '' },
    patientPhone: { type: String, default: '' },
    professionalName: { type: String, default: '' },
    canSignProfessional: { type: Boolean, default: false },
    pdfUrl: { type: String, default: null },
})

const emit = defineEmits(['updated'])

const loading = ref(true)
const panel = ref(null)
const copyFeedback = ref('')
const signingRole = ref(null)
const modalRef = ref(null)

const ROLE_LABELS = { patient: 'Paciente', professional: 'Profissional', responsible: 'Responsável', witness: 'Testemunha' }

const fetchPanel = async () => {
    loading.value = true
    const { data } = await axios.get(route('patients.documents.signature-panel', [props.patientId, props.documentId]))
    panel.value = data
    loading.value = false
    emit('updated', data)
}

onMounted(fetchPanel)

const copyLink = async () => {
    if (!panel.value?.sign_url) return
    await navigator.clipboard.writeText(panel.value.sign_url)
    copyFeedback.value = 'Link copiado!'
    setTimeout(() => (copyFeedback.value = ''), 2000)
}

const ensureLink = async () => {
    if (panel.value?.sign_url) return panel.value.sign_url
    const { data } = await axios.post(route('patients.documents.signature-panel.generate-link', [props.patientId, props.documentId]))
    await fetchPanel()
    return data.sign_url
}

const sendWhatsapp = async () => {
    const url = await ensureLink()
    const phone = (props.patientPhone || '').replace(/\D/g, '')
    const message = encodeURIComponent(`Olá! Segue o link para assinatura do seu documento: ${url}`)
    const wa = phone ? `https://wa.me/55${phone}?text=${message}` : `https://wa.me/?text=${message}`
    axios.post(route('patients.documents.signature-panel.log-whatsapp', [props.patientId, props.documentId])).catch(() => {})
    window.open(wa, '_blank')
}

const sendEmail = async () => {
    await axios.post(route('patients.documents.signature-panel.send-email', [props.patientId, props.documentId]))
    await fetchPanel()
}

const resend = async () => {
    await axios.post(route('patients.documents.signature-panel.generate-link', [props.patientId, props.documentId]))
    await fetchPanel()
}

const cancelRequest = async () => {
    if (!confirm('Cancelar a solicitação de assinatura deste documento?')) return
    await axios.post(route('patients.documents.signature-panel.cancel', [props.patientId, props.documentId]), {
        reason: 'Cancelado pelo usuário no painel de assinaturas.',
    })
    await fetchPanel()
}

const openDocument = () => {
    if (props.pdfUrl) window.open(props.pdfUrl, '_blank')
}

const signNow = (role) => {
    signingRole.value = role
}

const onSigned = async (payload) => {
    try {
        await axios.post(route('patients.documents.sign', [props.patientId, props.documentId, signingRole.value]), payload)
        signingRole.value = null
        await fetchPanel()
    } catch (e) {
        modalRef.value?.setError(e?.response?.data?.error || 'Erro ao registrar assinatura.')
    }
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-900">Painel de assinaturas</h3>
            <span v-if="panel" class="text-[11px] font-semibold" :class="panel.is_fully_signed ? 'text-teal-600' : 'text-amber-600'">
                {{ panel.status_label }}
            </span>
        </div>

        <div v-if="loading" class="text-[12px] text-slate-400 py-6 text-center">Carregando…</div>

        <template v-else-if="panel">
            <!-- Signers list -->
            <div class="space-y-2 mb-4">
                <div v-for="signer in panel.signers" :key="signer.role" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-2.5">
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-slate-800">{{ ROLE_LABELS[signer.role] || signer.role_label }}</p>
                        <p class="text-[11px] text-slate-400">
                            <template v-if="signer.status === 'signed'">{{ signer.signer_name }} · {{ signer.signed_at }} · {{ signer.method }}</template>
                            <template v-else>Aguardando assinatura</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span
                            class="text-[10px] font-semibold rounded-full px-2 py-0.5 border"
                            :class="signer.status === 'signed' ? 'bg-teal-50 text-teal-700 border-teal-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >{{ signer.status === 'signed' ? '✓ Assinado' : '⌛ Pendente' }}</span>
                        <button
                            v-if="signer.status !== 'signed' && (signer.role !== 'professional' || canSignProfessional)"
                            @click="signNow(signer.role)"
                            class="text-[11px] font-semibold text-teal-700 hover:text-teal-800"
                        >Assinar agora</button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2">
                <button @click="copyLink" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    {{ copyFeedback || 'Copiar link' }}
                </button>
                <button @click="sendWhatsapp" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">Enviar WhatsApp</button>
                <button @click="sendEmail" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">Enviar e-mail</button>
                <button v-if="pdfUrl" @click="openDocument" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">Abrir documento</button>
                <button @click="resend" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">Reenviar</button>
                <button v-if="!panel.is_fully_signed" @click="cancelRequest" class="rounded-lg border border-red-100 px-3 py-1.5 text-[11px] font-medium text-red-500 hover:bg-red-50 transition-colors">Cancelar solicitação</button>
            </div>
        </template>

        <DocumentSignatureModal
            ref="modalRef"
            :show="!!signingRole"
            :role="signingRole || 'patient'"
            :role-label="ROLE_LABELS[signingRole] || ''"
            :default-name="signingRole === 'patient' ? patientName : ''"
            @close="signingRole = null"
            @signed="onSigned"
        />
    </div>
</template>
