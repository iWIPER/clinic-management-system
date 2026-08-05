<script setup>
import { computed, ref } from 'vue'

const props = defineProps({ placeholders: { type: Array, default: () => [] } })
const emit = defineEmits(['insert'])

const open = ref(false)

const groups = computed(() => {
    const map = {}
    for (const p of props.placeholders) {
        if (!map[p.group]) map[p.group] = []
        map[p.group].push(p)
    }
    return map
})

const insert = (key) => {
    emit('insert', key)
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
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/>
            </svg>
            Inserir placeholder
        </button>

        <div v-if="open" class="fixed inset-0 z-10" @click="open = false" />

        <Transition
            enter-active-class="transition-all duration-150 ease-out"
            leave-active-class="transition-all duration-100 ease-in"
            enter-from-class="opacity-0 -translate-y-1"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute z-20 mt-2 w-72 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl p-2"
            >
                <div v-for="(items, group) in groups" :key="group" class="mb-1.5 last:mb-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-2 py-1">{{ group }}</div>
                    <button
                        v-for="p in items"
                        :key="p.key"
                        type="button"
                        @click="insert(p.key)"
                        class="w-full text-left px-2 py-1.5 rounded-lg text-[12px] text-slate-700 hover:bg-teal-50 hover:text-teal-700 transition-colors flex items-center justify-between group"
                    >
                        <span>{{ p.label }}</span>
                        <span class="text-[10px] text-slate-300 group-hover:text-teal-400 font-mono">%{{ p.key }}%</span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
