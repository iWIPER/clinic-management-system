<script setup>
import { computed } from 'vue'
import AnamnesisQuestionField from './AnamnesisQuestionField.vue'
import { categoryMeta, categorySlug } from '@/composables/useAnamnesisCategories'
import { filterRenderableQuestions } from '@/composables/useAnamnesisQuestions'

const props = defineProps({
    category: { type: Object, required: true },
    answers: { type: Object, required: true },
    disabledIds: { type: Object, default: () => new Set() },
    // ATENÇÃO — dependência entre módulos, ver docs/PATIENT_INVITATIONS_BRD.md §18:
    // esta prop foi adicionada pelo módulo de Convites de Cadastro (Fase 4)
    // para reaproveitar este componente no wizard público do paciente, sem
    // duplicar a renderização de categoria/pergunta. Não é uma funcionalidade
    // nova do hub de Anamnese — é uma adaptação necessária para reuso fora
    // do contexto staff. Esconde o toggle ON/OFF por pergunta e o botão
    // "Adicionar pergunta" (affordances de staff, sem sentido para o
    // paciente). Default false preserva o comportamento atual em Edit.vue.
    // Este arquivo ainda não foi commitado (hub de Anamnese como um todo é
    // dependência documentada, não commit pré-requisito — decisão registrada
    // no BRD). Quando o hub for commitado, esta prop precisa ser preservada
    // — não substituir por uma versão "limpa" vinda de outro lugar.
    readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['change', 'toggle', 'add-question'])

const meta = computed(() => ({
    ...categoryMeta(props.category.name),
    icon: props.category.icon || categoryMeta(props.category.name).icon,
    description: props.category.description || categoryMeta(props.category.name).description,
}))

const slug = computed(() => categorySlug(props.category.name))

const questions = computed(() => filterRenderableQuestions(props.category.questions))

const titleLabel = computed(() =>
    props.category.name.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
)

const isDisabled = (id) => props.disabledIds.has(id)
</script>

<template>
    <section
        v-if="questions.length"
        :id="'anamnesis-cat-' + slug"
        class="rounded-xl border border-[#E8EDF4] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)] scroll-mt-24"
    >
        <!-- Category header -->
        <header class="px-4 pt-3 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-[15px] leading-none shrink-0" aria-hidden="true">{{ meta.icon }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-[11px] font-bold uppercase tracking-wide text-slate-700 leading-none">{{ titleLabel }}</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-snug truncate">{{ meta.description }}</p>
                </div>
            </div>
            <div class="mt-2.5 border-t border-[#E8EDF4]" />
        </header>

        <!-- Questions -->
        <div class="px-3 pb-3 space-y-1.5">
            <div
                v-for="question in questions"
                :key="question.id"
                class="relative group"
                :class="isDisabled(question.id) ? 'opacity-40' : ''"
            >
                <!-- Toggle switch (top-right, visible on hover or always) -->
                <button
                    v-if="!readonly"
                    type="button"
                    class="absolute top-2 right-2 z-10 flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium transition-all duration-150"
                    :class="isDisabled(question.id)
                        ? 'bg-slate-100 text-slate-400 opacity-100'
                        : 'bg-slate-50 text-slate-400 opacity-0 group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-600'"
                    :title="isDisabled(question.id) ? 'Habilitar pergunta' : 'Desabilitar pergunta'"
                    @click.stop="emit('toggle', question.id)"
                >
                    <span
                        class="inline-block w-5 h-2.5 rounded-full transition-colors duration-200 relative"
                        :class="isDisabled(question.id) ? 'bg-slate-300' : 'bg-teal-500'"
                    >
                        <span
                            class="absolute top-0.5 w-1.5 h-1.5 rounded-full bg-white shadow-sm transition-all duration-200"
                            :class="isDisabled(question.id) ? 'left-0.5' : 'left-[11px]'"
                        />
                    </span>
                    <span class="leading-none">{{ isDisabled(question.id) ? 'OFF' : 'ON' }}</span>
                </button>

                <AnamnesisQuestionField
                    :question="question"
                    v-model="answers[question.id]"
                    :is-disabled="isDisabled(question.id)"
                    @change="emit('change')"
                />
            </div>
        </div>

        <!-- Add question button -->
        <div v-if="!readonly" class="px-3 pb-3">
            <button
                type="button"
                class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-dashed border-slate-200 py-1.5 text-[11px] text-slate-400 transition-all duration-150 hover:border-teal-300 hover:text-teal-600 hover:bg-teal-50/50"
                @click="emit('add-question', category.name)"
            >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Adicionar pergunta
            </button>
        </div>
    </section>
</template>
