<script setup>
import Modal from '@/Components/UI/Modal.vue'

defineProps({
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['acknowledge'])
</script>

<template>
    <Modal :show="show" max-width="max-w-md" @close="emit('acknowledge')">
        <div class="p-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50">
                <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 4.556-3.16 8.89-9 10.5C6.16 20.89 3 16.556 3 12V6.108a3.375 3.375 0 012.665-3.3A48.184 48.184 0 0112 2.25c2.163 0 4.29.187 6.335.558A3.375 3.375 0 0121 6.108V12z" />
                </svg>
            </div>

            <h2 class="mt-4 text-lg font-semibold text-slate-900">Acesso de Administrador do Sistema</h2>

            <p class="mt-3 text-sm text-slate-600">
                Você está entrando no painel administrativo da Wildental.
            </p>
            <p class="mt-2 text-sm text-slate-600">
                Este ambiente possui acesso global aos dados e configurações da plataforma.
            </p>
            <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                As ações administrativas realizadas aqui são registradas para auditoria.
            </p>
        </div>

        <template #footer>
            <button type="button" @click="emit('acknowledge')" autofocus
                    class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                Continuar para o painel
            </button>
        </template>
    </Modal>
</template>
