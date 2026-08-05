<script setup>
import OdontogramChart from './OdontogramChart.vue'

defineProps({
    teethData:     { type: Object, default: () => ({}) },
    toothStatuses: { type: Array,  default: () => [] },
    treatmentsByTooth: { type: Object, default: () => ({}) },
})
defineEmits(['close'])
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
            <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden"
                 style="max-height: min(90vh, 720px)">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 bg-slate-900 text-white shrink-0">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Visualização</p>
                        <p class="text-2xl font-black leading-none mt-0.5">Odontograma</p>
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
                    <OdontogramChart
                        :teeth-data="teethData"
                        :tooth-statuses="toothStatuses"
                        :treatments-by-tooth="treatmentsByTooth"
                        readonly />
                </div>
            </div>
        </Transition>
    </div>
</Teleport>
</template>
