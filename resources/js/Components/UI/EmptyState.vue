<script setup>
defineProps({
    icon:  { type: String, default: '🗂️' },
    title: { type: String, default: 'Nada por aqui ainda' },
    description: { type: String, default: '' },
})
</script>

<template>
<div class="rounded-xl border border-dashed border-slate-200 py-14 text-center">
    <div class="text-4xl mb-3">{{ icon }}</div>
    <p class="text-sm font-semibold text-slate-600">{{ title }}</p>
    <p v-if="description" class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
        <slot name="action" />
    </div>
</div>
</template>
