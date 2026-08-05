<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    tooth: String,
    toothData: Object,
    toothStatuses: Array,
    // Tratamentos ativos deste dente (somente leitura aqui) — a gestão
    // completa (adicionar/editar/finalizar) acontece no módulo Tratamentos,
    // ver PatientTreatment::groupedByTooth().
    treatments: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'save', 'open-history', 'open-treatments'])

const localData = ref(null)
const showRemoveForm = ref(false)

const init = () => {
    localData.value = {
        status:          props.toothData.status          ?? 'saudavel',
        notes:           props.toothData.notes           ?? '',
        removed:         props.toothData.removed         ?? false,
        removed_at:      props.toothData.removed_at      ?? '',
        removed_by:      props.toothData.removed_by      ?? '',
        removal_reason:  props.toothData.removal_reason  ?? '',
    }
    showRemoveForm.value = false
}

watch(() => props.tooth, init, { immediate: true })

const confirmRemove = () => {
    localData.value.removed = true
    showRemoveForm.value = false
}

const undoRemove = () => {
    localData.value.removed = false
    localData.value.removed_at = ''
    localData.value.removed_by = ''
    localData.value.removal_reason = ''
}

const save = () => {
    const data = {
        ...localData.value,
        removed_at:     localData.value.removed_at     || null,
        removed_by:     localData.value.removed_by     || null,
        removal_reason: localData.value.removal_reason || null,
    }
    emit('save', data)
}

// Styling helpers
const clinicalColors = {
    saudavel:   'bg-emerald-50 text-emerald-700 border-emerald-200',
    cariado:    'bg-red-50 text-red-700 border-red-200',
    restaurado: 'bg-blue-50 text-blue-700 border-blue-200',
    ausente:    'bg-slate-50 text-slate-500 border-slate-200',
    endodontia: 'bg-purple-50 text-purple-700 border-purple-200',
    protese:    'bg-orange-50 text-orange-700 border-orange-200',
    implante:   'bg-cyan-50 text-cyan-700 border-cyan-200',
    fraturado:  'bg-yellow-50 text-yellow-700 border-yellow-200',
}

const wsStyle = {
    futuro:       { bg: 'bg-purple-100 text-purple-700',  badge: '✦', label: 'Futuro' },
    em_andamento: { bg: 'bg-orange-100 text-orange-700',  badge: '●', label: 'Em andamento' },
    concluido:    { bg: 'bg-emerald-100 text-emerald-700', badge: '✓', label: 'Finalizado' },
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtCurrency = (v) => v ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : null
</script>

<template>
<div v-if="localData"
     class="w-full md:w-72 shrink-0 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden flex flex-col"
     style="max-height: 520px">

    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 bg-teal-700 text-white shrink-0">
        <div>
            <p class="text-[10px] font-bold text-teal-200 uppercase tracking-widest">Dente</p>
            <p class="text-2xl font-black leading-none mt-0.5">{{ tooth }}</p>
        </div>
        <button @click="$emit('close')" type="button"
                class="text-teal-200 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Scrollable body -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4 min-h-0">

        <!-- Removed banner -->
        <div v-if="localData.removed" class="bg-red-50 border border-red-200 rounded-xl p-3">
            <p class="text-red-700 font-bold text-sm flex items-center gap-1.5 mb-1.5">
                <span>✕</span> Dente Removido
            </p>
            <div class="text-xs text-red-600 space-y-0.5">
                <p v-if="localData.removal_reason">{{ localData.removal_reason }}</p>
                <p v-if="localData.removed_at">Data: {{ fmtDate(localData.removed_at) }}</p>
                <p v-if="localData.removed_by">Prof.: {{ localData.removed_by }}</p>
            </div>
            <button @click="undoRemove" type="button"
                    class="mt-2 text-[10px] text-red-500 hover:text-red-700 underline underline-offset-2">
                Desfazer remoção
            </button>
        </div>

        <template v-if="!localData.removed">
            <!-- Clinical status -->
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Status clínico</p>
                <div class="flex flex-wrap gap-1">
                    <button v-for="s in toothStatuses" :key="s.value" type="button"
                            @click="localData.status = s.value"
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold border transition-all"
                            :class="[
                                clinicalColors[s.value],
                                localData.status === s.value
                                    ? 'ring-2 ring-teal-500 ring-offset-1'
                                    : 'opacity-50 hover:opacity-80',
                            ]">
                        {{ s.label }}
                    </button>
                </div>
            </div>

            <!-- Treatments summary (read-only — gestão completa no módulo Tratamentos) -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Tratamentos</p>
                    <button @click="$emit('open-treatments')" type="button"
                            class="text-[10px] font-semibold text-teal-600 hover:text-teal-800 flex items-center gap-0.5 transition-colors">
                        <span class="text-sm leading-none">+</span> Adicionar
                    </button>
                </div>

                <div v-if="treatments.length" class="space-y-2 mb-2">
                    <div v-for="t in treatments" :key="t.id"
                         class="border border-slate-100 rounded-lg p-2.5 bg-slate-50/60">
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full"
                                  :class="wsStyle[t.status]?.bg ?? 'bg-slate-100 text-slate-600'">
                                {{ wsStyle[t.status]?.badge }} {{ wsStyle[t.status]?.label }}
                            </span>
                        </div>
                        <p class="text-xs font-semibold text-slate-800 truncate">{{ t.procedure_name }}</p>
                        <div class="text-[10px] text-slate-400 mt-0.5 flex flex-wrap gap-x-1.5">
                            <span v-if="t.professional?.name">{{ t.professional.name }}</span>
                            <span v-if="t.treatment_date">{{ fmtDate(t.treatment_date) }}</span>
                            <span v-if="t.value_charged" class="text-emerald-600 font-medium">{{ fmtCurrency(t.value_charged) }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-[10px] text-slate-400 mb-2">Nenhum tratamento registrado.</p>

                <button @click="$emit('open-treatments')" type="button"
                        class="w-full text-[10px] text-teal-600 hover:text-teal-800 border border-teal-200 hover:border-teal-400 rounded-lg py-1.5 font-medium transition-colors">
                    Ver tratamentos deste dente
                </button>
            </div>

            <!-- Notes -->
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Observações</p>
                <textarea v-model="localData.notes" rows="2"
                          placeholder="Anotações sobre este dente..."
                          class="w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-teal-400 placeholder-slate-400" />
            </div>

            <!-- Remove tooth -->
            <div class="border-t border-slate-100 pt-3">
                <button @click="showRemoveForm = !showRemoveForm" type="button"
                        class="text-[10px] text-red-500 hover:text-red-700 font-medium underline underline-offset-2 transition-colors">
                    {{ showRemoveForm ? 'Cancelar remoção' : 'Marcar como removido' }}
                </button>
                <div v-if="showRemoveForm" class="mt-2.5 bg-red-50 border border-red-100 rounded-xl p-3 space-y-2">
                    <p class="text-[10px] font-bold text-red-600 uppercase tracking-widest">Remoção do dente</p>
                    <div>
                        <label class="text-[10px] text-slate-600 font-medium">Motivo</label>
                        <input v-model="localData.removal_reason" type="text"
                               placeholder="Ex: Extração por cárie avançada"
                               class="mt-0.5 w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-400 bg-white" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-slate-600 font-medium">Data</label>
                            <input v-model="localData.removed_at" type="date"
                                   class="mt-0.5 w-full text-xs border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-400 bg-white" />
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-600 font-medium">Profissional</label>
                            <input v-model="localData.removed_by" type="text" placeholder="Nome"
                                   class="mt-0.5 w-full text-xs border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-400 bg-white" />
                        </div>
                    </div>
                    <button @click="confirmRemove" type="button"
                            class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg py-1.5 text-xs font-semibold transition-colors">
                        Confirmar remoção
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Footer -->
    <div class="border-t border-slate-100 p-3 flex gap-2 shrink-0">
        <button @click="$emit('open-history')" type="button"
                class="flex-1 text-xs text-teal-600 hover:text-teal-800 border border-teal-200 hover:border-teal-400 rounded-lg py-1.5 font-medium transition-colors">
            Ver histórico
        </button>
        <button @click="save" type="button"
                class="flex-1 bg-teal-600 hover:bg-teal-700 text-white rounded-lg py-1.5 text-xs font-semibold transition-colors">
            Salvar
        </button>
    </div>
</div>
</template>
