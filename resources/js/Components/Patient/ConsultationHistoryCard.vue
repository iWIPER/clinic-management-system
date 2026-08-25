<script setup>
import { ref, computed } from 'vue'

// `consultations` vem de PatientHubService::consultationsGrouped() —
// já é 100% derivado de Appointment (Consultation não entra aqui, ver
// arquitetura aprovada: Appointment é a fonte de verdade da consulta,
// Consultation é só registro operacional interno de check-in). Junta os
// três status "passado" (completed/cancelled/no_show); "upcoming" fica de
// fora de propósito — a próxima consulta já aparece em Próximas Ações.
const props = defineProps({
    consultations: {
        type: Object,
        default: () => ({ completed: [], upcoming: [], cancelled: [], no_show: [] }),
    },
})

const STATUS_LABELS = {
    completed: 'Finalizada',
    cancelled: 'Cancelada',
    no_show: 'Falta',
}

const STATUS_COLORS = {
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-50 text-red-700 border-red-200',
    no_show: 'bg-amber-50 text-amber-700 border-amber-200',
}

const statusFilter = ref('all')

const history = computed(() => {
    return [
        ...(props.consultations.completed ?? []),
        ...(props.consultations.cancelled ?? []),
        ...(props.consultations.no_show ?? []),
    ].sort((a, b) => new Date(b.start) - new Date(a.start))
})

const filteredHistory = computed(() => {
    if (statusFilter.value === 'all') return history.value
    return history.value.filter((c) => c.status === statusFilter.value)
})

const availableStatuses = computed(() => {
    const present = new Set(history.value.map((c) => c.status))
    return Object.keys(STATUS_LABELS).filter((s) => present.has(s))
})

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('pt-BR')
}

function fmtTime(iso) {
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Consultas</p>

            <select v-if="history.length" v-model="statusFilter"
                    class="text-xs border border-slate-200 rounded-md px-2 py-1 text-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <option value="all">Todos os status</option>
                <option v-for="s in availableStatuses" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
            </select>
        </div>

        <p v-if="!history.length" class="text-sm text-slate-400 py-2">
            Este paciente ainda não possui histórico de consultas.
        </p>

        <ul v-else class="space-y-2 max-h-72 overflow-y-auto pr-1">
            <li v-for="c in filteredHistory" :key="c.id"
                class="flex items-center justify-between gap-2 text-sm border-b border-slate-50 last:border-b-0 pb-2 last:pb-0">
                <div class="min-w-0">
                    <p class="text-slate-700">
                        {{ fmtDate(c.start) }} às {{ fmtTime(c.start) }}
                    </p>
                    <p class="text-xs text-slate-400 truncate">{{ c.professional || 'Profissional não informado' }}</p>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                          :class="STATUS_COLORS[c.status] || 'bg-slate-50 text-slate-600 border-slate-200'">
                        {{ STATUS_LABELS[c.status] || c.status }}
                    </span>

                    <span v-if="c.notes" class="group relative inline-flex">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold cursor-default">!</span>
                        <span class="pointer-events-none absolute right-0 top-full mt-1 w-56 max-w-[70vw] opacity-0 group-hover:opacity-100 transition-opacity duration-150 bg-slate-800 text-white text-[11px] leading-snug rounded-lg px-3 py-2 whitespace-pre-line shadow-lg z-20">
                            {{ c.notes }}
                        </span>
                    </span>
                </div>
            </li>
        </ul>
    </section>
</template>
