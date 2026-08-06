<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: Number, default: 0 },
    // 'neutral' (padrão), 'positive' (Recebido), 'warning' (A receber), 'critical' (Em atraso)
    // — mesmo vocabulário de cor já usado em SummaryCards.vue (Visão Geral) para
    // os mesmos conceitos (emerald=pago, amber=pendente).
    tone: { type: String, default: 'neutral' },
})

const TONE_CLASS = {
    neutral:  'text-slate-900',
    positive: 'text-emerald-700',
    warning:  'text-amber-700',
    critical: 'text-red-700',
}

const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
</script>

<template>
    <div class="rounded-xl border border-slate-200 p-4 sm:p-5">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">{{ label }}</p>
        <p class="text-xl font-bold leading-tight" :class="TONE_CLASS[tone] ?? TONE_CLASS.neutral">
            {{ fmtCurrency(value) }}
        </p>
    </div>
</template>
