<script setup>
import { computed } from 'vue'
import { sortByStatusPriority } from './toothStatusPriority.js'

const props = defineProps({
    tooth:        { type: String,  default: null },
    toothData:    { type: Object,  default: () => ({}) },
    // Tratamentos ativos deste dente (PatientTreatment) — {status, faces, ...}[]
    treatments:   { type: Array,   default: () => [] },
    style:        { type: Object,  default: () => ({}) },
    visible:      { type: Boolean, default: false },
    vsLabel:      { type: String,  default: '' },
    vsBadge:      { type: String,  default: null },
    vsBadgeColor: { type: String,  default: '#94a3b8' },
    toothStatuses:{ type: Array,   default: () => [] },
})

const WS_LABEL  = { futuro: 'Futuro', em_andamento: 'Em andamento', concluido: 'Finalizado' }
const WS_BADGE  = { futuro: '✦', em_andamento: '●', concluido: '✓' }
const WS_TCOLOR = { futuro: '#8b5cf6', em_andamento: '#f97316', concluido: '#10b981' }

// Em andamento > Finalizado > Futuro — mesma prioridade usada pra pintar a
// coroa/raiz do dente (ver toothStatusPriority.js, fonte única). Tratamentos
// com o mesmo status mantêm a ordem original entre si (por data).
const sortedTreatments = computed(() => sortByStatusPriority(props.treatments))

const facesLabel = (t) => {
    const n = t.faces?.length ?? 0
    return n > 0 ? `${n} ${n === 1 ? 'face' : 'faces'}` : 'Dente completo'
}

const fmtDate     = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : null
const fmtCurrency = (v)   => v   ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : null
</script>

<template>
<Teleport to="body">
    <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">

        <div v-if="visible && tooth"
             class="fixed z-[9999] pointer-events-none w-64 rounded-xl shadow-2xl overflow-hidden origin-top-left"
             :style="style">
            <div class="bg-slate-900 border border-slate-700 text-white text-xs">

                <div class="px-3 py-2 bg-slate-800 border-b border-slate-700/60 flex items-center justify-between gap-2">
                    <span class="font-bold text-sm">Dente {{ tooth }}</span>
                    <span class="text-[10px] font-semibold shrink-0" :style="`color:${vsBadgeColor}`">
                        {{ vsBadge }} {{ vsLabel }}
                    </span>
                </div>

                <div class="px-3 py-2.5">
                    <template v-if="toothData.removed">
                        <p class="font-semibold text-red-400 flex items-center gap-1.5 mb-2">✕ Removido</p>
                        <div class="space-y-0.5 text-[11px] text-slate-300">
                            <p v-if="toothData.removal_reason">
                                <span class="text-slate-500">Motivo: </span>{{ toothData.removal_reason }}
                            </p>
                            <p v-if="toothData.removed_at">
                                <span class="text-slate-500">Data: </span>{{ fmtDate(toothData.removed_at) }}
                            </p>
                            <p v-if="toothData.removed_by">
                                <span class="text-slate-500">Prof.: </span>{{ toothData.removed_by }}
                            </p>
                        </div>
                    </template>

                    <template v-else-if="treatments.length">
                        <div v-for="(t, i) in sortedTreatments" :key="t.id ?? i"
                             class="pb-2 mb-2 border-b border-slate-700/50 last:border-0 last:pb-0 last:mb-0">
                            <p class="flex items-center gap-1.5 font-semibold text-[11px] leading-snug">
                                <span class="text-[10px] shrink-0"
                                      :style="`color:${WS_TCOLOR[t.status] ?? '#94a3b8'}`">
                                    {{ WS_BADGE[t.status] ?? '●' }}
                                </span>
                                {{ t.procedure_name }}
                            </p>
                            <p class="text-[10px] text-slate-400 pl-4">
                                {{ WS_LABEL[t.status] ?? t.status }}
                            </p>
                            <p class="text-[10px] text-slate-500 pl-4">
                                {{ facesLabel(t) }}
                            </p>
                            <div v-if="t.professional?.name || t.treatment_date || t.value_charged"
                                 class="pl-4 mt-1 space-y-0.5 text-[10px] text-slate-300">
                                <p v-if="t.professional?.name">{{ t.professional.name }}</p>
                                <p v-if="t.treatment_date">
                                    Início: {{ fmtDate(t.treatment_date) }}
                                    <template v-if="t.completed_at"> · {{ fmtDate(t.completed_at) }}</template>
                                </p>
                                <p v-if="t.value_charged" class="text-emerald-400 font-medium">
                                    {{ fmtCurrency(t.value_charged) }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <p class="text-[11px] text-slate-300">
                            {{ toothStatuses.find(s => s.value === toothData.status)?.label ?? 'Saudável' }}
                        </p>
                    </template>

                    <p v-if="toothData.notes?.trim()"
                       class="text-[10px] text-slate-400 border-t border-slate-700/50 mt-2 pt-2 leading-relaxed">
                        {{ toothData.notes }}
                    </p>
                </div>
            </div>
        </div>
    </Transition>
</Teleport>
</template>
