<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    href: { type: String, required: true },
    tooltip: { type: String, required: true },
    match: { type: String, default: null },
    active: { type: Boolean, default: false },
})
</script>

<template>
    <div class="group relative flex items-center">
        <Link
            :href="href"
            :aria-label="tooltip"
            class="relative inline-flex cursor-pointer items-center justify-center rounded-lg p-1.5 text-slate-500 transition-all duration-[180ms] ease hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.97]"
            :class="active ? 'bg-slate-50 text-emerald-700' : ''"
        >
            <slot />
        </Link>

        <span
            class="pointer-events-none absolute left-1/2 top-full z-50 mt-1.5 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-800 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-sm transition-all duration-[180ms] ease group-hover:opacity-100 group-focus-within:opacity-100"
            role="tooltip"
        >
            {{ tooltip }}
        </span>
    </div>
</template>