<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    report: Object,
    provider: String,
});

const emit = defineEmits(['close']);

const healthScore = computed(() => props.report?.health_score ?? 0);

function checkIcon(status) {
    if (status === 'ok') return '✔';
    if (status === 'warning') return '⚠';
    return '❌';
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show"
                 class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                 @click.self="emit('close')">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                    <div class="px-6 py-4 border-b flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-900">Teste de Integração</h2>
                        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <div class="px-6 py-5">
                        <div v-if="!report" class="text-center py-8">
                            <svg class="w-8 h-8 animate-spin text-teal-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            <p class="text-sm text-slate-600">Testando conexão...</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-medium">{{ report.provider_name ?? provider }}</span>
                                <span class="text-2xl font-bold"
                                      :class="report.success ? 'text-emerald-600' : 'text-red-600'">
                                    {{ healthScore }}%
                                </span>
                            </div>

                            <div class="space-y-1.5 mb-4">
                                <div v-for="check in report.checks" :key="check.key"
                                     class="flex items-center gap-2 text-sm text-slate-700">
                                    <span>{{ checkIcon(check.status) }}</span>
                                    <span>{{ check.label }}</span>
                                    <span v-if="check.message" class="text-slate-400 text-xs">— {{ check.message }}</span>
                                </div>
                            </div>

                            <ul class="text-xs text-slate-600 space-y-1 border-t pt-3">
                                <li v-for="(tip, i) in report.recommendations" :key="i">• {{ tip }}</li>
                            </ul>
                        </template>
                    </div>

                    <div class="px-6 py-4 border-t bg-slate-50 flex justify-end">
                        <button @click="emit('close')"
                                class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-medium">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>