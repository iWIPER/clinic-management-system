<script setup>
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'

defineProps({
    modelValue: { type: String, default: null },
})
defineEmits(['update:modelValue'])

// Espelha PatientMarkerService::PALETTE (PHP) — front e back não compartilham
// esse array, mas o backend é quem valida de verdade (Rule::in na paleta).
const PALETTE = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']
</script>

<template>
    <NavbarDropdown align="left" width="w-40">
        <template #trigger>
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs hover:bg-slate-50">
                <span v-if="modelValue" class="w-3.5 h-3.5 rounded-full shrink-0" :style="{ backgroundColor: modelValue }"></span>
                <span :class="modelValue ? 'text-slate-600' : 'text-slate-400'">
                    {{ modelValue ? 'Cor selecionada' : 'Selecionar uma cor' }}
                </span>
                <span class="text-slate-300">▾</span>
            </button>
        </template>
        <template #default="{ close }">
            <div class="p-3 flex flex-wrap gap-2">
                <button v-for="color in PALETTE" :key="color" type="button"
                        @click="$emit('update:modelValue', color); close()"
                        :aria-label="color"
                        class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                        :class="modelValue === color ? 'border-slate-700 scale-110' : 'border-transparent hover:scale-105'"
                        :style="{ backgroundColor: color }">
                    <span v-if="modelValue === color" class="text-white text-[10px] leading-none">✓</span>
                </button>
            </div>
        </template>
    </NavbarDropdown>
</template>
