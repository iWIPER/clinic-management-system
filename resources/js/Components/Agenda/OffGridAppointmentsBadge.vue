<script setup>
// Pílula discreta pro final/topo da coluna quando existem agendamentos além
// do teto/piso absoluto da grade (ver GRID_FLOOR_HOUR/GRID_CEIL_HOUR em
// useEffectiveSchedule.js) — a grade NUNCA cresce pra mostrá-los, mas eles
// continuam 100% acessíveis por aqui. Reaproveita o popover de detalhes já
// existente (@select só repassa o appt pro pai abrir, sem view própria).
import { ref, onMounted, onUnmounted } from 'vue'

defineProps({
    appointments: { type: Array, default: () => [] },
    label: { type: String, default: 'fora da grade' },
    formatTime: { type: Function, required: true },
    patientName: { type: Function, required: true },
})

const emit = defineEmits(['select'])

const open = ref(false)
const rootRef = ref(null)

function pick(appt, event) {
    open.value = false
    // Repassa o evento nativo também — openPopover() (Index/Fullscreen)
    // usa e.currentTarget pra posicionar o popover, não dá pra abrir sem ele.
    emit('select', appt, event)
}

// Mesmo padrão de fechar-ao-clicar-fora já usado no resto da Agenda (ver
// onOutsideClick/onOutsideSettings em Index.vue) — projeto não tem uma
// diretiva v-click-outside própria.
function onOutsideClick(e) {
    if (open.value && rootRef.value && !rootRef.value.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('mousedown', onOutsideClick))
onUnmounted(() => document.removeEventListener('mousedown', onOutsideClick))
</script>

<template>
<div v-if="appointments.length" ref="rootRef" class="relative">
    <button type="button" @click.stop="open = !open"
            class="w-full flex items-center justify-center gap-1 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border-t border-amber-200/70 transition-colors">
        <svg class="w-2.5 h-2.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        {{ appointments.length }} {{ label }}
    </button>

    <div v-if="open"
         class="absolute z-40 left-0 right-0 mt-1 bg-white rounded-lg shadow-xl border border-slate-200 overflow-hidden text-left">
        <button v-for="appt in appointments" :key="appt.id" type="button" @click.stop="pick(appt, $event)"
                class="w-full flex flex-col px-2.5 py-1.5 hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-b-0 text-left">
            <span class="text-[10px] font-semibold text-slate-700 tabular-nums">
                {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
            </span>
            <span class="text-[10px] text-slate-500 truncate">{{ patientName(appt) }}</span>
        </button>
    </div>
</div>
</template>
