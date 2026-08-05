<script setup>
const props = defineProps({
    status: String,
    statusLabel: String,
    statusIcon: { type: String, default: '○' },
    statusColor: { type: String, default: 'slate' },
})

const COLOR_CLASSES = {
    slate:  'bg-slate-100 text-slate-600 border-slate-200',
    blue:   'bg-blue-50 text-blue-700 border-blue-100',
    amber:  'bg-amber-50 text-amber-700 border-amber-100',
    teal:   'bg-teal-50 text-teal-700 border-teal-200',
    red:    'bg-red-50 text-red-700 border-red-100',
}
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold shrink-0"
        :class="COLOR_CLASSES[statusColor] || COLOR_CLASSES.slate"
    >
        <span class="text-[9px]">{{ statusIcon }}</span>
        {{ statusLabel }}
    </span>
</template>
