<script setup>
defineProps({
    show: { type: Boolean, default: false },
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-300" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show"
                 class="fixed inset-0 z-[65] flex items-center justify-center bg-white/80 backdrop-blur-sm">
                <div class="text-center px-6">
                    <svg class="w-10 h-10 animate-spin text-teal-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-800">Verificando estrutura do armazenamento...</p>
                    <p class="text-xs text-slate-500 mt-2">Analisando pastas, arquivos, permissões e espaço disponível.</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>