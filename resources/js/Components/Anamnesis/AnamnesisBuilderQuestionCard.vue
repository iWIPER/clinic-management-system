<script setup>
import { ref } from 'vue'
import AnamnesisQuestionField from './AnamnesisQuestionField.vue'

defineProps({
    question: Object,
    templateId: Number,
    isFirst: Boolean,
    isLast: Boolean,
})

const emit = defineEmits(['edit', 'toggle-active', 'move', 'detach'])

const previewAnswer = ref({ value: '', supplementary_text: '' })
</script>

<template>
    <div
        class="relative rounded-xl border border-[#E8EDF4] bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-all duration-[180ms] hover:border-slate-300/90"
        :class="!question.is_active ? 'opacity-60' : ''"
    >
        <div class="absolute top-2 right-2 flex items-center gap-1.5 z-10">
            <button type="button" @click="emit('edit', question)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-slate-700" title="Editar">✏️</button>
            <button type="button" @click="emit('toggle-active', question)" class="rounded-lg px-2 py-1 text-[10px] font-medium border"
                    :class="question.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'">
                {{ question.is_active ? 'Ativa' : 'Inativa' }}
            </button>
            <button v-if="!isFirst" type="button" @click="emit('move', question, 'up')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50" title="Subir">↑</button>
            <button v-if="!isLast" type="button" @click="emit('move', question, 'down')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50" title="Descer">↓</button>
            <button type="button" @click="emit('detach', question.id)" class="rounded-lg p-1 text-red-400 hover:bg-red-50" title="Remover do modelo">✕</button>
        </div>

        <div class="p-4 pt-10 pointer-events-none select-none">
            <AnamnesisQuestionField :question="question" v-model="previewAnswer" />
        </div>
    </div>
</template>