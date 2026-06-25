<script setup>
defineProps({ alerts: Array });
</script>

<template>
    <div v-if="alerts?.length" class="rounded-xl border-2 border-red-300 bg-red-50 px-4 py-3">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span class="text-sm font-bold text-red-800 uppercase tracking-wide">Alertas Clínicos</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <div v-for="alert in alerts" :key="alert.key"
                 class="inline-flex items-center gap-2 bg-white border border-red-200 rounded-lg px-3 py-1.5 text-sm">
                <span class="font-semibold text-red-700">{{ alert.label }}</span>
                <span v-if="alert.detail" class="text-red-600">{{ alert.detail }}</span>
            </div>
        </div>
    </div>
</template>