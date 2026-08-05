<script setup>
import Modal from '@/Components/UI/Modal.vue'
import TreatmentStatusBadge from './TreatmentStatusBadge.vue'

const props = defineProps({
    show: Boolean,
    treatment: { type: Object, default: null },
})

defineEmits(['close'])

const ACTION_LABELS = {
    created: 'Criado',
    updated: 'Editado',
    cost_changed: 'Custo alterado',
    professional_changed: 'Profissional alterado',
    completed: 'Concluído',
    cancelled: 'Cancelado',
    duplicated: 'Duplicado',
}

const fmtDate = (v) => v ? new Date(v).toLocaleString('pt-BR') : '—'
const fmtCurrency = (v) => v ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : null

// Evento "completed" mostra quem CONCLUIU o tratamento (profissional
// selecionado no modal de finalizar), não quem estava logado ao clicar
// (log.user — autor da ação, correto pros demais eventos como "Criado").
// Prioridade: snapshot salvo no momento da conclusão (metadata.professional_name)
// → professional_id atual do tratamento (já sobrescrito pelo finalize pra
// logs antigos sem esse snapshot) → autor da ação como último recurso.
const eventProfessional = (log) => {
    if (log.action === 'completed') {
        return log.metadata?.professional_name ?? props.treatment?.professional?.name ?? log.user?.name ?? null
    }
    return log.user?.name ?? null
}
</script>

<template>
<Modal :show="show" max-width="max-w-lg" :title="`Histórico · ${treatment?.budget_code ?? ''}`" @close="$emit('close')">
    <div v-if="treatment" class="p-5 space-y-4">
        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <p class="font-semibold text-slate-800">{{ treatment.procedure_name }}</p>
                <TreatmentStatusBadge :status="treatment.status" />
            </div>
            <div class="text-xs text-slate-500 space-y-0.5">
                <p v-if="treatment.tooth">Dente {{ treatment.tooth }}<template v-if="treatment.faces?.length"> · Faces: {{ treatment.faces.join(', ') }}</template></p>
                <p v-if="treatment.professional?.name">Profissional: {{ treatment.professional.name }}</p>
                <p v-if="treatment.convenio?.nome">Convênio: {{ treatment.convenio.nome }}</p>
                <p>Valor: {{ fmtCurrency(treatment.value_charged) }} · Custo: {{ fmtCurrency(treatment.cost) }}</p>
            </div>
        </div>

        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Linha do tempo</p>
            <div v-if="treatment.audit_logs?.length" class="space-y-2.5 pl-3 border-l-2 border-slate-100">
                <div v-for="log in treatment.audit_logs" :key="log.id" class="text-xs">
                    <p class="font-semibold text-slate-700">{{ ACTION_LABELS[log.action] ?? log.action }}</p>
                    <p class="text-slate-400">{{ fmtDate(log.created_at) }} <template v-if="eventProfessional(log)"> · {{ eventProfessional(log) }}</template></p>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">Sem eventos registrados.</p>
        </div>
    </div>

    <template #footer>
        <button type="button" @click="$emit('close')"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
            Fechar
        </button>
    </template>
</Modal>
</template>
