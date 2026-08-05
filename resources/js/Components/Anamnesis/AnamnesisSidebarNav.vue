<script setup>
import { categorySlug } from '@/composables/useAnamnesisCategories'

const props = defineProps({
    categories: { type: Array, required: true },
    activeSlug: { type: String, default: '' },
    answers: { type: Object, default: null },
    disabledIds: { type: Object, default: () => new Set() },
})

const emit = defineEmits(['navigate'])

const title = (name) =>
    name.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())

const answeredIn = (cat) => {
    if (!props.answers) return 0
    return (cat.questions || []).filter((q) => {
        if (props.disabledIds.has(q.id)) return false
        const a = props.answers[q.id]
        return a && (a.value || a.supplementary_text)
    }).length
}

const totalIn = (cat) =>
    (cat.questions || []).filter((q) => !props.disabledIds.has(q.id)).length
</script>

<template>
    <nav class="overflow-y-auto pb-6" style="max-height: calc(100vh - 9rem)">
        <button
            v-for="cat in categories"
            :key="cat.name"
            type="button"
            @click="emit('navigate', categorySlug(cat.name))"
            class="group relative w-full flex items-center gap-2 rounded-md py-[5px] pr-2.5 text-left transition-colors duration-150 border-l-2 mb-0.5"
            :class="activeSlug === categorySlug(cat.name)
                ? 'border-teal-500 bg-teal-50/70 pl-2 text-teal-800'
                : 'border-transparent pl-2.5 text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
        >
            <span
                class="text-[13px] leading-none shrink-0 transition-all duration-150"
                :style="{ color: cat.icon_color || '#64748b' }"
            >{{ cat.icon || '📄' }}</span>

            <span
                class="flex-1 min-w-0 truncate text-[11px] leading-tight transition-colors duration-150"
                :class="activeSlug === categorySlug(cat.name)
                    ? 'font-semibold text-teal-800'
                    : 'font-medium text-slate-600 group-hover:text-slate-800'"
            >{{ title(cat.name) }}</span>

            <span
                class="shrink-0 min-w-[18px] text-center rounded-full px-1 py-px text-[10px] font-semibold tabular-nums leading-none transition-colors duration-150"
                :class="answeredIn(cat) > 0
                    ? 'bg-teal-100 text-teal-700'
                    : 'bg-slate-100 text-slate-400'"
            >{{ totalIn(cat) }}</span>
        </button>
    </nav>
</template>
