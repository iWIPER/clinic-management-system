<script setup>
// Mesmo padrão visual do HolidayNoticeModal (ícone + título + mensagem +
// botão "Entendi") — cobre os outros dois motivos de bloqueio administrativo
// da Agenda (fora do horário / dia sem atendimento) sem duplicar esse
// componente pra cada caso, já que a estrutura é idêntica, só muda o texto.
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Fora do horário de atendimento' },
    message: { type: String, default: '' },
})

defineEmits(['close'])
</script>

<template>
<Modal :show="show" max-width="max-w-sm" @close="$emit('close')">
    <div class="p-6 flex flex-col items-center text-center gap-3">
        <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center">
            <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <h3 class="font-semibold text-slate-900">{{ title }}</h3>
            <p class="text-sm text-slate-500 mt-1 leading-relaxed">{{ message }}</p>
        </div>
        <button type="button" @click="$emit('close')"
                class="mt-1 px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
            Entendi
        </button>
    </div>
</Modal>
</template>
