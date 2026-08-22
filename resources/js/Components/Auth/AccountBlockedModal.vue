<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { NoSymbolIcon } from '@heroicons/vue/24/outline'
import Modal from '@/Components/UI/Modal.vue'

// Sem prop `title`/slot `header` de propósito — sem isso o Modal não
// desenha o X de fechar (ver Modal.vue), então a única saída é o botão
// "Sair". O backend é quem decide mostrar esta tela (ver middleware
// EnsureAccountIsActive), então não há conteúdo real por trás para revelar
// mesmo que o overlay fosse clicável.
const loggingOut = ref(false)

function logout() {
    loggingOut.value = true
    router.post(route('logout'))
}
</script>

<template>
<Modal :show="true" max-width="max-w-sm">
    <div class="p-6 sm:p-8 flex flex-col items-center text-center gap-4">
        <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center shrink-0">
            <NoSymbolIcon class="w-7 h-7 text-red-600" />
        </div>
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Acesso bloqueado</h3>
            <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                Sua conta está temporariamente bloqueada e, por isso, não é possível acessar o sistema neste momento.
            </p>
            <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                Se você acredita que isso aconteceu por engano ou precisa de mais informações, entre em contato com o suporte.
            </p>
        </div>
        <button
            type="button"
            :disabled="loggingOut"
            class="mt-1 w-full sm:w-auto px-6 py-2.5 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-60 transition-colors"
            @click="logout"
        >
            Sair
        </button>
    </div>
</Modal>
</template>
