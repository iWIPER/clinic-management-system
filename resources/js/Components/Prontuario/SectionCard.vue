<script setup>
defineProps({
    id: String,
    title: String,
    subtitle: String,
    icon: { type: String, default: '' },
})
</script>

<template>
<section :id="id" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden scroll-mt-24">
    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-teal-50 to-white border-b border-teal-100 border-l-4 border-l-teal-600">
        <div v-if="icon" class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-600 text-white shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">{{ title }}</h2>
            <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5">{{ subtitle }}</p>
        </div>
    </div>
    <div class="p-6">
        <slot />
    </div>
</section>
</template>