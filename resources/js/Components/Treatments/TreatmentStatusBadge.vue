<script setup>
defineProps({
    status: { type: String, required: true },
})

const CONFIG = {
    futuro:       { label: 'Futuro',       badge: '✦', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
    em_andamento: { label: 'Em andamento', badge: '●', cls: 'bg-orange-50 text-orange-700 border-orange-200' },
    concluido:    { label: 'Finalizado',   badge: '✓', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
}
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold shrink-0"
          :class="CONFIG[status]?.cls ?? 'bg-slate-100 text-slate-500 border-slate-200'">
        <span class="text-[10px]">{{ CONFIG[status]?.badge ?? '•' }}</span>
        {{ CONFIG[status]?.label ?? status }}
    </span>
</template>
