<script setup>
import { computed } from 'vue'
import { STATUS_CONFIG } from '@/composables/useAppointmentStatus'

const props = defineProps({
    status:       { type: String, required: true },
    delayMinutes: { type: Number, default: 0 },
    // 'sm' = 8px dot (cards, listas)  |  'md' = 10px dot (badges maiores)
    size:         { type: String, default: 'sm' },
    // mostra label junto ao dot
    showLabel:    { type: Boolean, default: false },
})

const cfg = computed(() => STATUS_CONFIG[props.status] ?? STATUS_CONFIG.scheduled)

const tooltipText = computed(() => {
    if (props.status === 'late' && props.delayMinutes > 0)
        return `${cfg.value.label} · ${props.delayMinutes} min`
    return cfg.value.label
})

const dotClass = computed(() =>
    props.size === 'md' ? 'w-2.5 h-2.5' : 'w-2 h-2'
)

// Pulsação apenas para estados ativos urgentes
const pulse = computed(() =>
    ['em_atendimento', 'late'].includes(props.status)
)
</script>

<template>
<div class="relative inline-flex items-center gap-1.5 group/si">

  <!-- Dot colorido -->
  <span class="relative flex flex-shrink-0" :class="dotClass">
    <span v-if="pulse"
          class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-60"
          :class="cfg.color" />
    <span class="relative inline-flex rounded-full"
          :class="[cfg.color, dotClass]" />
  </span>

  <!-- Label opcional (modo badge) -->
  <span v-if="showLabel" class="text-xs font-medium" :class="cfg.text">
    {{ tooltipText }}
  </span>

  <!-- Tooltip (CSS puro, sem dependências) -->
  <div v-if="!showLabel"
       class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5
              opacity-0 group-hover/si:opacity-100 transition-opacity duration-150
              bg-slate-800 text-white text-[10px] font-medium rounded-md px-2 py-1
              whitespace-nowrap z-[200] shadow-lg">
    {{ tooltipText }}
    <!-- seta -->
    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800" />
  </div>

</div>
</template>
