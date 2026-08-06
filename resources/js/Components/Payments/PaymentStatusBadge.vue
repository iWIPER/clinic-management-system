<script setup>
const props = defineProps({
    status: { type: String, required: true },
    overdue: { type: Boolean, default: false },
})

const CONFIG = {
    pendente:  { label: 'Pendente',  badge: '○', cls: 'bg-slate-100 text-slate-600 border-slate-200' },
    parcial:   { label: 'Parcial',   badge: '◐', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    pago:      { label: 'Recebido',  badge: '✓', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    cancelado: { label: 'Cancelado', badge: '✕', cls: 'bg-slate-100 text-slate-400 border-slate-200' },
}

// "Em atraso" não é um status persistido — sobrepõe a exibição de
// pendente/parcial quando a parcela já venceu (ver PatientPayment::isOverdue()).
const display = () => {
    if (props.overdue && (props.status === 'pendente' || props.status === 'parcial')) {
        return { label: 'Em atraso', badge: '!', cls: 'bg-red-50 text-red-700 border-red-200' }
    }
    return CONFIG[props.status] ?? { label: props.status, badge: '•', cls: 'bg-slate-100 text-slate-500 border-slate-200' }
}
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold shrink-0"
          :class="display().cls">
        <span class="text-[10px]">{{ display().badge }}</span>
        {{ display().label }}
    </span>
</template>
