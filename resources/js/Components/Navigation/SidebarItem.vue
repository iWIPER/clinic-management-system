<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

// Prop chamada `routeName` (não `route`) de propósito — `route()` é o
// helper global do Ziggy usado no template abaixo; um prop chamado `route`
// sombrearia essa função dentro deste componente.
const props = defineProps({
    label: { type: String, required: true },
    routeName: { type: String, required: true },
    match: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    badge: { type: String, default: null },
    // Leve destaque tipográfico pros itens mais frequentes (Agenda,
    // Pacientes) — só o tamanho da fonte do rótulo muda, peso e cor
    // continuam uniformes com os demais itens.
    emphasized: { type: Boolean, default: false },
})

const emit = defineEmits(['navigate'])

const page = usePage()

const isActive = computed(() => {
    if (!props.match) return false
    const url = page.url.split('?')[0]
    return url.startsWith(props.match)
})
</script>

<template>
    <Link
        :href="route(routeName)"
        :aria-current="isActive ? 'page' : undefined"
        class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold leading-normal tracking-normal antialiased transition-colors duration-[180ms] ease focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1"
        :class="isActive
            ? 'bg-emerald-50 text-emerald-900'
            : 'text-emerald-800 hover:bg-slate-100 hover:text-emerald-900'"
        @click="emit('navigate')"
    >
        <span
            v-if="isActive"
            class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-emerald-600"
        />
        <component
            :is="icon"
            v-if="icon"
            class="h-[18px] w-[18px] shrink-0"
            stroke-width="2"
            :class="isActive ? 'text-emerald-900' : 'text-emerald-800 group-hover:text-emerald-900'"
        />
        <span class="truncate" :class="emphasized ? 'text-[15px]' : ''">{{ label }}</span>
        <span
            v-if="badge"
            class="ml-auto shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-700"
        >
            {{ badge }}
        </span>
    </Link>
</template>
