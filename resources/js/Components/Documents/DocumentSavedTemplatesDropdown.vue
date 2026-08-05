<script setup>
import { ref } from 'vue'

defineProps({ hasTemplate: Boolean })
const emit = defineEmits(['new', 'duplicate', 'archive', 'delete'])

const open = ref(false)
const trigger = (event) => {
    emit(event)
    open.value = false
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click="open = !open"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-600 hover:bg-slate-50 transition-colors"
        >
            Modelos salvos
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div v-if="open" class="fixed inset-0 z-10" @click="open = false" />

        <div v-if="open" class="absolute right-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white shadow-xl p-1.5">
            <button type="button" @click="trigger('new')" class="w-full text-left px-3 py-2 rounded-lg text-[12px] text-slate-700 hover:bg-slate-50 transition-colors">Novo modelo</button>
            <template v-if="hasTemplate">
                <button type="button" @click="trigger('duplicate')" class="w-full text-left px-3 py-2 rounded-lg text-[12px] text-slate-700 hover:bg-slate-50 transition-colors">Duplicar</button>
                <button type="button" @click="trigger('archive')" class="w-full text-left px-3 py-2 rounded-lg text-[12px] text-slate-700 hover:bg-slate-50 transition-colors">Arquivar</button>
                <div class="h-px bg-slate-100 my-1" />
                <button type="button" @click="trigger('delete')" class="w-full text-left px-3 py-2 rounded-lg text-[12px] text-red-500 hover:bg-red-50 transition-colors">Excluir</button>
            </template>
        </div>
    </div>
</template>
