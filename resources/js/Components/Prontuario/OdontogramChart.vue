<script setup>
import { computed } from 'vue'

const props = defineProps({
    teethData: Object,
    fdiTeeth: Array,
    toothStatuses: Array,
    readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:teethData'])

const upperRight = computed(() => props.fdiTeeth.slice(0, 8))
const upperLeft  = computed(() => props.fdiTeeth.slice(8, 16))
const lowerRight = computed(() => props.fdiTeeth.slice(16, 24))
const lowerLeft  = computed(() => props.fdiTeeth.slice(24, 32))

const statusColors = {
    saudavel:    'bg-emerald-100 border-emerald-300 text-emerald-800',
    cariado:     'bg-red-100 border-red-300 text-red-800',
    restaurado:  'bg-blue-100 border-blue-300 text-blue-800',
    ausente:     'bg-slate-100 border-slate-300 text-slate-400',
    endodontia:  'bg-purple-100 border-purple-300 text-purple-800',
    protese:     'bg-orange-100 border-orange-300 text-orange-800',
    implante:    'bg-cyan-100 border-cyan-300 text-cyan-800',
    fraturado:   'bg-yellow-100 border-yellow-300 text-yellow-800',
}

const getStatus = (tooth) => props.teethData?.[tooth]?.status ?? 'saudavel'

const cycleStatus = (tooth) => {
    if (props.readonly) return
    const statuses = props.toothStatuses.map(s => s.value)
    const current = getStatus(tooth)
    const next = statuses[(statuses.indexOf(current) + 1) % statuses.length]
    emit('update:teethData', {
        ...props.teethData,
        [tooth]: { ...props.teethData?.[tooth], status: next, notes: props.teethData?.[tooth]?.notes ?? '' },
    })
}
</script>

<template>
<div class="odontogram-chart">
    <!-- Legenda -->
    <div class="flex flex-wrap gap-2 mb-5">
        <span v-for="s in toothStatuses" :key="s.value"
              class="inline-flex items-center gap-1.5 text-[10px] font-medium px-2 py-1 rounded-full border"
              :class="statusColors[s.value]">
            {{ s.label }}
        </span>
    </div>

    <!-- Arcada superior -->
    <div class="text-center text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-2">Arcada Superior</div>
    <div class="flex justify-center gap-1 mb-1">
        <div class="flex gap-0.5">
            <button v-for="tooth in upperRight" :key="tooth" type="button"
                    @click="cycleStatus(tooth)"
                    class="w-8 h-10 rounded-t-lg border-2 text-[9px] font-bold flex flex-col items-center justify-center transition-all hover:scale-105"
                    :class="[statusColors[getStatus(tooth)], readonly ? 'cursor-default' : 'cursor-pointer']">
                <span>{{ tooth }}</span>
            </button>
        </div>
        <div class="w-px bg-slate-300 mx-1 self-stretch" />
        <div class="flex gap-0.5">
            <button v-for="tooth in upperLeft" :key="tooth" type="button"
                    @click="cycleStatus(tooth)"
                    class="w-8 h-10 rounded-t-lg border-2 text-[9px] font-bold flex flex-col items-center justify-center transition-all hover:scale-105"
                    :class="[statusColors[getStatus(tooth)], readonly ? 'cursor-default' : 'cursor-pointer']">
                <span>{{ tooth }}</span>
            </button>
        </div>
    </div>

    <!-- Linha média -->
    <div class="flex items-center gap-2 my-3">
        <div class="flex-1 border-t border-dashed border-slate-300" />
        <span class="text-[9px] text-slate-400 font-medium">Linha média</span>
        <div class="flex-1 border-t border-dashed border-slate-300" />
    </div>

    <!-- Arcada inferior -->
    <div class="flex justify-center gap-1 mb-1">
        <div class="flex gap-0.5">
            <button v-for="tooth in lowerRight" :key="tooth" type="button"
                    @click="cycleStatus(tooth)"
                    class="w-8 h-10 rounded-b-lg border-2 text-[9px] font-bold flex flex-col items-center justify-center transition-all hover:scale-105"
                    :class="[statusColors[getStatus(tooth)], readonly ? 'cursor-default' : 'cursor-pointer']">
                <span>{{ tooth }}</span>
            </button>
        </div>
        <div class="w-px bg-slate-300 mx-1 self-stretch" />
        <div class="flex gap-0.5">
            <button v-for="tooth in lowerLeft" :key="tooth" type="button"
                    @click="cycleStatus(tooth)"
                    class="w-8 h-10 rounded-b-lg border-2 text-[9px] font-bold flex flex-col items-center justify-center transition-all hover:scale-105"
                    :class="[statusColors[getStatus(tooth)], readonly ? 'cursor-default' : 'cursor-pointer']">
                <span>{{ tooth }}</span>
            </button>
        </div>
    </div>
    <div class="text-center text-[10px] font-semibold text-slate-400 uppercase tracking-widest mt-2">Arcada Inferior</div>

    <p v-if="!readonly" class="text-[10px] text-slate-400 text-center mt-4">
        Clique em um dente para alterar o status. Arquitetura preparada para edição avançada futura.
    </p>
</div>
</template>