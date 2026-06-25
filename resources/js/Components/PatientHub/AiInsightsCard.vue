<script setup>
defineProps({ insights: Array });

const priorityStyle = {
    high: 'border-l-red-500 bg-red-50',
    medium: 'border-l-amber-500 bg-amber-50',
    low: 'border-l-blue-500 bg-blue-50',
};
</script>

<template>
    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border border-indigo-200 p-5">
        <div class="flex items-center gap-2 mb-3">
            <div class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-indigo-900">Insights da IA</h3>
                <p class="text-xs text-indigo-600">Sugestões inteligentes para decisão clínica</p>
            </div>
        </div>
        <ul v-if="insights?.length" class="space-y-2">
            <li v-for="(insight, i) in insights" :key="i"
                class="text-sm text-slate-700 pl-3 border-l-4 rounded-r-lg py-2 px-3"
                :class="priorityStyle[insight.priority] ?? priorityStyle.low">
                {{ insight.message }}
            </li>
        </ul>
        <p v-else class="text-sm text-indigo-600/70">Nenhum insight no momento. Paciente em dia!</p>
    </div>
</template>