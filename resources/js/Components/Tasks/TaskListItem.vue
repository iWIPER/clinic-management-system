<script setup>
import { Link } from '@inertiajs/vue3'
import { MapPinIcon as PinOutline, StarIcon as StarOutline } from '@heroicons/vue/24/outline'
import { MapPinIcon as PinSolid, StarIcon as StarSolid } from '@heroicons/vue/24/solid'
import {
    cardPriorityClass as cardClass, priorityTextClass, isOverdueTask as isOverdue,
    formatTaskDate as fmtDate, patientDisplayName as patientName,
    statusIconClass, statusIconFor,
} from './taskPresentation'

defineProps({
    task: { type: Object, required: true },
    priorities: { type: Object, required: true },
    statuses: { type: Object, required: true },
})

defineEmits(['edit', 'delete', 'toggle-done', 'toggle-pin', 'toggle-favorite'])

// Vencimento sempre na linha da prioridade agora (atrasada, hoje, futura ou
// concluída — ver template), consistente em todas as visões. Só sobra
// paciente na linha de baixo.
const hasSecondaryMeta = (task) => !!task.patient
</script>

<template>
    <div class="group relative flex items-start gap-3 rounded-xl border px-4 py-3 transition-all duration-150 hover:border-slate-200 hover:shadow-sm"
         data-testid="task-list-item" :data-task-id="task.id"
         :class="cardClass(task)">
        <button type="button" @click="$emit('toggle-done')"
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-150 hover:scale-110 active:scale-95"
                :class="task.status === 'done' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white hover:border-emerald-500'"
                :title="task.status === 'done' ? 'Reabrir tarefa' : 'Concluir tarefa'">
            <svg v-if="task.status === 'done'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </button>

        <div class="min-w-0 flex-1">
            <!-- Título + badge do responsável: mesma linha, extremidades opostas —
                 o responsável fica sempre no mesmo lugar do card, nunca "perdido"
                 no meio do texto (só existe em tarefas de equipe, que têm
                 assignee; "Minhas tarefas" nunca mostra esse campo). -->
            <div class="flex items-start justify-between gap-2">
                <button type="button" class="flex min-w-0 items-center gap-1.5 text-left" @click="$emit('edit')">
                    <PinSolid v-if="task.pinned_at" class="h-3 w-3 shrink-0 text-amber-500" />
                    <p class="truncate text-sm font-medium" :class="task.status === 'done' ? 'text-slate-400 line-through' : 'text-slate-800'">
                        {{ task.title }}
                    </p>
                </button>

                <span v-if="task.assignee"
                      class="shrink-0 rounded-md bg-white/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 shadow-sm ring-1 ring-slate-200">
                    {{ task.assignee.name }}
                </span>
            </div>

            <button type="button" class="block w-full text-left" @click="$emit('edit')">
                <div v-if="task.description" class="group/desc relative mt-1 w-fit max-w-full">
                    <p class="line-clamp-2 text-xs text-slate-500">{{ task.description }}</p>
                    <div class="pointer-events-none absolute left-0 top-full z-20 mt-1 hidden w-64 rounded-md bg-slate-800 px-2.5 py-1.5 text-[11px] leading-snug text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/desc:block group-hover/desc:opacity-100">
                        {{ task.description }}
                    </div>
                </div>

                <!-- Prioridade, vencimento e etiquetas na mesma linha —
                     prioridade sempre o primeiro elemento à esquerda, ~1cm
                     de respiro antes do vencimento (atrasada em vermelho, ou
                     hoje/futura/concluída em cinza — mesmo espaçamento nos
                     dois casos, pro visual ficar consistente em todas as
                     visões), e etiquetas com seu próprio `ml-auto` — ficam
                     coladas à direita do card, com um respiro flexível (não
                     fixo) até a data, nunca "grudadas" nem jogadas pro
                     centro. flex-wrap só entra em ação em casos extremos
                     (evita overflow sem aumentar a altura do card no caso
                     comum). -->
                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="shrink-0 text-[11px] font-semibold" :class="priorityTextClass(task)">
                        {{ priorities[task.priority] ?? task.priority }}
                    </p>
                    <span v-if="isOverdue(task)" class="ml-[1cm] shrink-0 truncate text-[11px] font-semibold text-red-500">
                        Atrasada · {{ fmtDate(task.due_date) }}
                    </span>
                    <span v-else-if="task.due_date" class="ml-[1cm] shrink-0 text-[11px] text-slate-400">
                        {{ fmtDate(task.due_date) }}
                    </span>

                    <div v-if="task.labels.length" class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                        <!-- Concluída: etiquetas em versão neutra/cinza — não
                             faz sentido chamar atenção com cor num card que
                             já é propositalmente neutro. A cor original da
                             etiqueta não é alterada, só a apresentação aqui.
                             Nas demais visões, fundo branco sólido (não
                             tingido pela própria cor) — cores claras ficavam
                             ilegíveis em fundo translúcido. Mesmo padrão de
                             "bg-white + borda/texto na cor" já usado no
                             seletor de etiquetas do formulário
                             (TaskFormModal.vue). -->
                        <span v-for="l in task.labels" :key="l.id"
                              class="rounded-full border px-1.5 py-0.5 text-[11px] font-medium"
                              :class="task.status === 'done' ? 'border-slate-200 bg-slate-100 text-slate-500' : 'bg-white'"
                              :style="task.status === 'done' ? {} : { color: l.color, borderColor: l.color }">
                            {{ l.name }}
                        </span>
                    </div>
                </div>

                <div v-if="hasSecondaryMeta(task)" class="mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                    <Link v-if="task.patient" :href="route('patients.prontuario', task.patient.id)"
                          class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-emerald-700 hover:underline"
                          @click.stop>
                        👤 {{ patientName(task.patient) }}
                    </Link>
                </div>
            </button>
        </div>

        <div class="flex shrink-0 items-start gap-0.5">
            <!-- Ícone de status — só um indicador visual (percebido de
                 relance, sem competir com prioridade/data/etiquetas na linha
                 de baixo); mudar o status continua exclusivamente pelo modal
                 de edição. Sempre visível (não segue o fade-in no hover dos
                 outros ícones, que são ações). Tooltip no mesmo padrão
                 CSS-only já usado pela descrição do card, abaixo. -->
            <span class="group/status relative mt-0.5 rounded-lg p-1.5">
                <component :is="statusIconFor(task)" class="h-3.5 w-3.5" :class="statusIconClass(task)" />
                <div class="pointer-events-none absolute right-0 top-full z-20 mt-1 hidden whitespace-nowrap rounded-md bg-slate-800 px-2 py-1 text-[11px] text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/status:block group-hover/status:opacity-100">
                    {{ statuses[task.status] ?? task.status }}
                </div>
            </span>

            <button type="button" @click="$emit('toggle-favorite')" :title="task.is_favorite ? 'Remover dos favoritos' : 'Favoritar'"
                    class="mt-0.5 rounded-lg p-1.5 transition-opacity hover:bg-black/5"
                    :class="task.is_favorite ? 'text-amber-500 opacity-100' : 'text-slate-300 opacity-0 group-hover:opacity-100 hover:text-amber-500'">
                <StarSolid v-if="task.is_favorite" class="h-3.5 w-3.5" />
                <StarOutline v-else class="h-3.5 w-3.5" />
            </button>

            <button type="button" @click="$emit('toggle-pin')" :title="task.pinned_at ? 'Desafixar' : 'Fixar no topo'"
                    class="mt-0.5 rounded-lg p-1.5 transition-opacity hover:bg-black/5"
                    :class="task.pinned_at ? 'text-amber-500 opacity-100' : 'text-slate-300 opacity-0 group-hover:opacity-100 hover:text-amber-500'">
                <PinSolid v-if="task.pinned_at" class="h-3.5 w-3.5" />
                <PinOutline v-else class="h-3.5 w-3.5" />
            </button>

            <button type="button" @click="$emit('delete')" title="Excluir tarefa"
                    class="mt-0.5 rounded-lg p-1.5 text-slate-300 opacity-0 transition-opacity hover:bg-black/5 hover:text-red-500 group-hover:opacity-100">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</template>
