<script setup>
import TreatmentStatusBadge from './TreatmentStatusBadge.vue'
import TreatmentActionsMenu from './TreatmentActionsMenu.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import SkeletonRow from '@/Components/UI/SkeletonRow.vue'

defineProps({
    treatments: { type: Array, default: () => [] },
    loading:    { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'edit', 'cost', 'finalize', 'delete', 'view', 'duplicate', 'history'])

const FACE_LABELS = { M: 'M', D: 'D', V: 'V', L: 'L', O: 'O' }

const fmtDate = (v) => v ? new Date(v).toLocaleDateString('pt-BR') : '—'
const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

// Mostra o title nativo só quando o texto está de fato truncado (reticências ativas)
function showTitleIfTruncated(e, text) {
    const el = e.currentTarget
    if (el.scrollWidth > el.clientWidth) {
        el.setAttribute('title', text)
    } else {
        el.removeAttribute('title')
    }
}
</script>

<template>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/60 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3">Tratamento</th>
                    <th class="px-4 py-3">Profissional</th>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Dente</th>
                    <th class="px-4 py-3">Face(s)</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Conclusão</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <template v-if="loading">
                    <SkeletonRow v-for="i in 5" :key="i" :columns="9" />
                </template>
                <tr v-else-if="!treatments.length">
                    <td colspan="9" class="px-4 py-2">
                        <EmptyState icon="🦷" title="Nenhum tratamento registrado"
                                    description="Clique em “Adicionar Tratamento” para começar o histórico odontológico deste paciente.">
                            <template #action>
                                <button type="button" @click="emit('add')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition-colors">
                                    + Adicionar Tratamento
                                </button>
                            </template>
                        </EmptyState>
                    </td>
                </tr>
                <tr v-for="t in treatments" :key="t.id" class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-4 py-3 max-w-[220px]">
                        <p class="font-medium text-slate-800 flex items-center gap-1.5">
                            <span class="truncate min-w-0" @mouseenter="showTitleIfTruncated($event, t.procedure_name)">{{ t.procedure_name }}</span>
                            <span v-if="t.notes" :title="t.notes"
                                  class="inline-flex items-center justify-center w-4 h-4 shrink-0 rounded-full bg-slate-200 text-slate-500 text-[10px] font-bold cursor-help select-none">
                                ✎
                            </span>
                        </p>
                        <p v-if="t.budget_code" class="text-xs text-slate-400 truncate mt-1 leading-snug font-mono">{{ t.budget_code }}</p>
                    </td>
                    <td class="px-4 py-3 max-w-[180px]">
                        <p class="text-slate-700 truncate">{{ t.professional?.name ?? '—' }}</p>
                        <p v-if="t.convenio?.nome" class="text-xs text-slate-400 truncate mt-1 leading-snug">
                            {{ t.convenio.nome }}
                        </p>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ fmtDate(t.treatment_date) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ t.tooth ?? '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500 text-[11px]">
                        {{ (t.faces ?? []).map(f => FACE_LABELS[f] ?? f).join(', ') || '—' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-slate-700">{{ fmtCurrency(t.value_charged) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap"><TreatmentStatusBadge :status="t.status" /></td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ t.completed_at ? fmtDate(t.completed_at) : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <TreatmentActionsMenu
                            :treatment="t"
                            @edit="emit('edit', t)"
                            @cost="emit('cost', t)"
                            @finalize="emit('finalize', t)"
                            @delete="emit('delete', t)"
                            @view="emit('view', t)"
                            @duplicate="emit('duplicate', t)"
                            @history="emit('history', t)" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</template>
