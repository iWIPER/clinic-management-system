<script setup>
import { computed } from 'vue'

const props = defineProps({
    tooth: String,
    toothData: Object,
    // Tratamentos deste dente (PatientTreatment) — ver PatientTreatment::groupedByTooth()
    treatments: { type: Array, default: () => [] },
})

defineEmits(['close'])

const WS = {
    futuro:       { badge: '✦', label: 'Futuro',        cls: 'bg-purple-100 text-purple-700 border-purple-200' },
    em_andamento: { badge: '●', label: 'Em andamento',  cls: 'bg-orange-100 text-orange-700 border-orange-200' },
    concluido:    { badge: '✓', label: 'Finalizado',    cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : null
const fmtYear = (iso) => iso ? new Date(iso).getFullYear() : null
const fmtCurrency = (v) => v ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : null

const byYear = computed(() => {
    const groups = {}
    for (const t of props.treatments) {
        const year = fmtYear(t.treatment_date) ?? fmtYear(t.completed_at) ?? 'Sem data'
        if (!groups[year]) groups[year] = []
        groups[year].push(t)
    }
    // Sort descending: most recent year first
    return Object.entries(groups).sort(([a], [b]) => {
        const na = Number(a) || 0
        const nb = Number(b) || 0
        return nb - na
    })
})
</script>

<template>
<Teleport to="body">
    <div class="fixed inset-0 z-[9998] flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')" />

        <!-- Modal -->
        <Transition
            appear
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100">
            <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden"
                 style="max-height: min(80vh, 600px)">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 bg-slate-900 text-white shrink-0">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Histórico completo</p>
                        <p class="text-2xl font-black leading-none mt-0.5">Dente {{ tooth }}</p>
                    </div>
                    <button @click="$emit('close')" type="button"
                            class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-5 min-h-0">

                    <!-- Removed -->
                    <div v-if="toothData?.removed" class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
                        <p class="font-bold text-red-700 flex items-center gap-2 mb-2 text-sm">✕ Dente Removido</p>
                        <div class="text-sm text-red-600 space-y-1">
                            <p v-if="toothData.removal_reason">
                                <span class="font-medium">Motivo:</span> {{ toothData.removal_reason }}
                            </p>
                            <p v-if="toothData.removed_at">
                                <span class="font-medium">Data:</span> {{ fmtDate(toothData.removed_at) }}
                            </p>
                            <p v-if="toothData.removed_by">
                                <span class="font-medium">Profissional:</span> {{ toothData.removed_by }}
                            </p>
                        </div>
                    </div>

                    <!-- Timeline by year -->
                    <template v-if="byYear.length">
                        <div v-for="([year, procs]) in byYear" :key="year" class="mb-5 last:mb-0">
                            <!-- Year divider -->
                            <div class="flex items-center gap-3 mb-3">
                                <div class="h-px flex-1 bg-slate-200" />
                                <span class="text-sm font-black text-slate-700 px-1">{{ year }}</span>
                                <div class="h-px flex-1 bg-slate-200" />
                            </div>

                            <!-- Procedures this year -->
                            <div class="space-y-2.5 pl-4 border-l-2 border-slate-200">
                                <div v-for="(t, i) in procs" :key="t.id ?? i"
                                     class="bg-white border border-slate-100 rounded-xl p-3 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between gap-2">
                                        <!-- Status badge -->
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                              :class="WS[t.status]?.cls ?? 'bg-slate-100 text-slate-600 border-slate-200'">
                                            {{ WS[t.status]?.badge ?? '●' }}
                                            {{ WS[t.status]?.label ?? t.status }}
                                        </span>
                                        <span v-if="t.budget_code" class="text-[10px] font-mono text-slate-400">{{ t.budget_code }}</span>
                                    </div>
                                    <!-- Name -->
                                    <p class="text-sm font-semibold text-slate-800 mt-1.5">{{ t.procedure_name }}</p>
                                    <!-- Details -->
                                    <div class="text-xs text-slate-500 mt-1 space-y-0.5">
                                        <p v-if="t.professional?.name">{{ t.professional.name }}</p>
                                        <p v-if="t.treatment_date || t.completed_at">
                                            <template v-if="t.treatment_date">Início: {{ fmtDate(t.treatment_date) }}</template>
                                            <template v-if="t.treatment_date && t.completed_at"> · </template>
                                            <template v-if="t.completed_at">Conclusão: {{ fmtDate(t.completed_at) }}</template>
                                        </p>
                                        <p v-if="t.value_charged" class="font-semibold text-emerald-600">{{ fmtCurrency(t.value_charged) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div v-else-if="!toothData?.removed" class="text-center py-10">
                        <div class="text-4xl mb-3">🦷</div>
                        <p class="text-sm text-slate-400 font-medium">Nenhum tratamento registrado.</p>
                        <p class="text-xs text-slate-300 mt-1">Use "Adicionar Tratamento" para registrar um procedimento neste dente.</p>
                    </div>

                    <!-- General notes -->
                    <div v-if="toothData?.notes?.trim()" class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-amber-700 uppercase tracking-widest mb-1">Observações</p>
                        <p class="text-sm text-amber-800 leading-relaxed">{{ toothData.notes }}</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 px-5 py-3 shrink-0">
                    <button @click="$emit('close')" type="button"
                            class="w-full bg-slate-900 hover:bg-slate-800 text-white rounded-xl py-2 text-sm font-semibold transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</Teleport>
</template>
