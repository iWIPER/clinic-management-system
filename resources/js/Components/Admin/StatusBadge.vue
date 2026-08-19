<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    // Mapa opcional { valor: { label, tone } } — quando ausente, usa um
    // conjunto padrão que cobre os status já usados no backoffice
    // (clínica: active/trial/suspended/cancelled; usuário: ativo/inativo).
    map: { type: Object, default: null },
})

const DEFAULT_MAP = {
    active:    { label: 'Ativa',    tone: 'emerald' },
    ativo:     { label: 'Ativo',    tone: 'emerald' },
    trial:     { label: 'Trial',    tone: 'blue' },
    suspended: { label: 'Bloqueada', tone: 'red' },
    inativo:   { label: 'Bloqueado', tone: 'red' },
    cancelled: { label: 'Cancelada', tone: 'slate' },
    expired:   { label: 'Expirada', tone: 'slate' },
    paused:    { label: 'Pausada',  tone: 'amber' },
    sem_assinatura: { label: 'Sem assinatura', tone: 'slate' },
}

const TONES = {
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    red:     'bg-red-50 text-red-700 border-red-200',
    blue:    'bg-blue-50 text-blue-700 border-blue-200',
    amber:   'bg-amber-50 text-amber-700 border-amber-200',
    slate:   'bg-slate-100 text-slate-600 border-slate-200',
}

const resolved = computed(() => {
    const entry = (props.map || DEFAULT_MAP)[props.status]
        ?? { label: props.status, tone: 'slate' }
    return entry
})
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
          :class="TONES[resolved.tone] || TONES.slate">
        {{ resolved.label }}
    </span>
</template>
