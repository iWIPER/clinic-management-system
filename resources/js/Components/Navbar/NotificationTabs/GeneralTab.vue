<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    items: { type: Array, default: () => [] },
    close: { type: Function, default: () => {} },
})

const notifDotColor = {
    success: 'bg-emerald-500',
    error:   'bg-red-500',
    warning: 'bg-amber-400',
    info:    'bg-blue-500',
}
</script>

<template>
    <div v-if="items.length === 0" class="px-4 py-6 text-center text-xs text-slate-400">
        Nenhuma notificação no momento.
    </div>

    <div v-else class="max-h-72 divide-y overflow-y-auto">
        <div
            v-for="(item, i) in items"
            :key="i"
            class="flex cursor-default items-start gap-3 px-4 py-3 transition-colors duration-[180ms] ease hover:bg-slate-50"
        >
            <div class="mt-0.5 h-2 w-2 flex-shrink-0 rounded-full" :class="notifDotColor[item.type]" />
            <span class="text-xs leading-snug text-slate-700">{{ item.text }}</span>
        </div>
    </div>

    <div class="border-t px-4 py-2">
        <Link
            :href="route('consultations.index')"
            class="cursor-pointer text-xs font-medium text-emerald-600 transition-colors duration-[180ms] ease hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35"
            @click="close"
        >
            Ver consultas ativas →
        </Link>
    </div>
</template>
