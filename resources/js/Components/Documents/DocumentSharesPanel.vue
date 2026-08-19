<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    patientId: { type: [Number, String], required: true },
    documentId: { type: [Number, String], required: true },
    refreshKey: { type: [Number, String], default: 0 },
})

const shares = ref([])
const loading = ref(false)
const revokingId = ref(null)

// Fase B5: geração/envio (PDF protegido + S3 + e-mail) agora roda num job —
// generation_status reflete isso enquanto status (revoked/expirado/etc)
// continua sendo o ciclo de vida do link em si.
const statusLabel = (share) => {
    if (share.generation_status === 'processing') return 'Processando…'
    if (share.generation_status === 'failed') return 'Falha ao enviar'
    if (share.status === 'revoked') return 'Revogado'
    if (new Date(share.expires_at) < new Date()) return 'Expirado'
    if (share.password_revealed_at) return 'Senha visualizada'
    return 'Aguardando'
}

const statusClass = (share) => {
    const label = statusLabel(share)
    if (label === 'Falha ao enviar') return 'bg-red-50 text-red-700'
    if (label === 'Processando…') return 'bg-blue-50 text-blue-700'
    if (label === 'Revogado' || label === 'Expirado') return 'bg-slate-100 text-slate-500'
    if (label === 'Senha visualizada') return 'bg-teal-50 text-teal-700'
    return 'bg-amber-50 text-amber-700'
}

const canRevoke = (share) => share.status !== 'revoked' && new Date(share.expires_at) > new Date()

// Fase B5: enquanto algum compartilhamento está "processing" (job ainda não
// terminou), reconsulta a cada 3s pra sair sozinho do estado "Processando…"
// assim que o job concluir — sem isso o usuário só veria a atualização
// recarregando a página. Para sozinho quando não sobra nenhum em processing.
let _pollTimer = null

const hasProcessing = () => shares.value.some(s => s.generation_status === 'processing')

const schedulePoll = () => {
    clearTimeout(_pollTimer)
    if (! hasProcessing()) return
    _pollTimer = setTimeout(fetchShares, 3000)
}

const fetchShares = async () => {
    loading.value = true
    try {
        const { data } = await axios.get(route('patients.documents.shares', [props.patientId, props.documentId]))
        shares.value = data.shares
        schedulePoll()
    } catch (e) {
        // painel é informativo — falha silenciosa não deve travar a tela do documento
    } finally {
        loading.value = false
    }
}

const revoke = async (share) => {
    if (! confirm(`Revogar o compartilhamento com ${share.recipient_email}? Isso impede novos acessos ao link, mas não apaga um PDF que a pessoa já tenha baixado.`)) return

    revokingId.value = share.id
    try {
        await axios.post(route('patients.documents.shares.revoke', [props.patientId, props.documentId, share.id]))
        await fetchShares()
    } catch (e) {
        alert('Não foi possível revogar. Tente novamente.')
    } finally {
        revokingId.value = null
    }
}

onMounted(fetchShares)
onUnmounted(() => clearTimeout(_pollTimer))
watch(() => props.refreshKey, fetchShares)
</script>

<template>
    <div v-if="shares.length || loading" class="rounded-2xl border border-slate-200 bg-white p-5 mt-5">
        <h3 class="text-sm font-bold text-slate-900 mb-3">Compartilhamentos</h3>

        <div v-if="loading && ! shares.length" class="text-[12px] text-slate-400">Carregando…</div>

        <div v-for="share in shares" :key="share.id" class="flex items-center justify-between gap-3 py-2.5 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="text-[13px] font-medium text-slate-800 truncate">{{ share.recipient_email }}</p>
                <p class="text-[11px] text-slate-400">
                    Enviado {{ share.sent_at ? new Date(share.sent_at).toLocaleString('pt-BR') : '—' }}
                    · Expira {{ new Date(share.expires_at).toLocaleDateString('pt-BR') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusClass(share)">{{ statusLabel(share) }}</span>
                <button
                    v-if="canRevoke(share)"
                    @click="revoke(share)"
                    :disabled="revokingId === share.id"
                    class="text-[11px] font-medium text-red-500 hover:bg-red-50 rounded-lg px-2.5 py-1 disabled:opacity-50"
                >Revogar</button>
            </div>
        </div>
    </div>
</template>
