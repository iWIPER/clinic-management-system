<script setup>
import { computed } from 'vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    holidayName: { type: String, default: '' },
    dateLabel: { type: String, default: '' },
})

defineEmits(['close'])

// Mensagem por NOME do feriado (não por data — as datas continuam vivendo
// só em BrazilianHolidayService, isto aqui é puramente o tom de cada um).
// Feriados de luto/reflexão (Finados, Sexta-feira Santa) não recebem tom
// festivo de propósito.
const MESSAGES = {
    'Confraternização Universal': 'Um novo ano começando — hoje é dia de descanso antes da correria recomeçar.',
    'Sexta-feira Santa': 'Um dia de reflexão e recolhimento.',
    'Tiradentes': 'Hoje lembramos quem lutou pela liberdade do Brasil.',
    'Dia do Trabalho': 'Hoje o descanso é o trabalho mais importante. Aproveite o dia!',
    'Independência do Brasil': 'Feliz Independência! Hoje é dia de comemorar o Brasil.',
    'Nossa Senhora Aparecida': 'Dia da padroeira do Brasil — um dia de fé e gratidão.',
    'Finados': 'Um dia de memória e saudade de quem já se foi.',
    'Proclamação da República': 'Hoje celebramos a Proclamação da República.',
    'Dia Nacional de Zumbi e da Consciência Negra': 'Um dia de memória, consciência e luta por igualdade.',
    'Natal': 'Hora de estar perto de quem a gente ama. Feliz Natal!',
}
const DEFAULT_MESSAGE = 'Hoje é feriado nacional.'

const message = computed(() => MESSAGES[props.holidayName] || DEFAULT_MESSAGE)
</script>

<template>
<Modal :show="show" max-width="max-w-sm" @close="$emit('close')">
    <div class="p-6 flex flex-col items-center text-center gap-3">
        <div class="h-12 w-12 rounded-full bg-amber-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m6.364 1.136l-1.06 1.06M21 12h-1.5m-1.136 6.364l-1.06-1.06M12 19.5V21m-6.364-1.136l1.06-1.06M3 12h1.5m1.136-6.364l1.06 1.06M12 8a4 4 0 100 8 4 4 0 000-8z" />
            </svg>
        </div>
        <div>
            <h3 class="font-semibold text-slate-900">{{ dateLabel }} é {{ holidayName }}</h3>
            <p class="text-sm text-slate-500 mt-1 leading-relaxed">
                {{ message }} A clínica não atende hoje.
            </p>
        </div>
        <button type="button" @click="$emit('close')"
                class="mt-1 px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
            Escolher outro dia
        </button>
    </div>
</Modal>
</template>
