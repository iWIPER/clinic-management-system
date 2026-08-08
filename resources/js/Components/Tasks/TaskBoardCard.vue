<script setup>
import { Link } from '@inertiajs/vue3'
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline'
import { StarIcon as StarSolid, MapPinIcon as PinSolid } from '@heroicons/vue/24/solid'
import {
    cardPriorityClass, priorityTextClass, isOverdueTask, formatTaskDate, patientDisplayName,
    statusIconClass, statusIconFor,
} from './taskPresentation'

const props = defineProps({
    task: { type: Object, required: true },
    priorities: { type: Object, required: true },
    statuses: { type: Object, required: true },
    // Card sendo arrastado agora (opacidade reduzida) ou aguardando resposta
    // da API depois de solto (leve pulso) — dois estados visuais distintos,
    // nunca ao mesmo tempo na prática.
    dragging: { type: Boolean, default: false },
    moving: { type: Boolean, default: false },
})

defineEmits(['edit', 'toggle-done', 'toggle-favorite', 'dragstart', 'dragend'])

function onDragStart(e) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(props.task.id))
}
</script>

<template>
    <div
        draggable="true"
        data-testid="board-card"
        :data-task-id="task.id"
        @dragstart="(e) => { onDragStart(e); $emit('dragstart') }"
        @dragend="$emit('dragend')"
        class="group relative flex cursor-grab flex-col gap-2 rounded-xl border p-3 shadow-sm transition-all duration-150 hover:shadow-md active:cursor-grabbing"
        :class="[cardPriorityClass(task), dragging ? 'opacity-40' : '', moving ? 'animate-pulse' : '']">
        <div class="flex items-start gap-1.5">
            <button type="button" @click.stop="$emit('toggle-done')"
                    class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-150 hover:scale-110 active:scale-95"
                    :class="task.status === 'done' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white hover:border-emerald-500'"
                    :title="task.status === 'done' ? 'Reabrir tarefa' : 'Concluir tarefa'">
                <svg v-if="task.status === 'done'" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </button>

            <button type="button" class="flex min-w-0 flex-1 items-center gap-1 text-left" @click="$emit('edit')">
                <PinSolid v-if="task.pinned_at" class="h-3 w-3 shrink-0 text-amber-500" />
                <p class="truncate text-sm font-medium" :class="task.status === 'done' ? 'text-slate-400 line-through' : 'text-slate-800'">
                    {{ task.title }}
                </p>
            </button>

            <button type="button" @click.stop="$emit('toggle-favorite')" :title="task.is_favorite ? 'Remover dos favoritos' : 'Favoritar'"
                    class="shrink-0 rounded-lg p-1 transition-opacity hover:bg-black/5"
                    :class="task.is_favorite ? 'text-amber-500 opacity-100' : 'text-slate-300 opacity-0 group-hover:opacity-100 hover:text-amber-500'">
                <StarSolid v-if="task.is_favorite" class="h-3.5 w-3.5" />
                <StarOutline v-else class="h-3.5 w-3.5" />
            </button>

            <!-- Ícone de status — só indicador visual, sempre visível (não
                 segue o fade-in do favorito, que é uma ação); mudar o status
                 continua exclusivamente pelo modal de edição. Mesmo padrão
                 de tooltip CSS-only da Lista (ver TaskListItem.vue). -->
            <span class="group/status relative shrink-0 rounded-lg p-1">
                <component :is="statusIconFor(task)" class="h-3.5 w-3.5" :class="statusIconClass(task)" />
                <div class="pointer-events-none absolute right-0 top-full z-20 mt-1 hidden whitespace-nowrap rounded-md bg-slate-800 px-2 py-1 text-[11px] text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/status:block group-hover/status:opacity-100">
                    {{ statuses[task.status] ?? task.status }}
                </div>
            </span>
        </div>

        <span v-if="task.assignee" class="w-fit shrink-0 rounded-md bg-white/80 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-600 shadow-sm ring-1 ring-slate-200">
            {{ task.assignee.name }}
        </span>

        <button type="button" class="block w-full text-left" @click="$emit('edit')">
            <p v-if="task.description" class="line-clamp-2 text-xs" :class="task.status === 'done' ? 'text-slate-300' : 'text-slate-500'">
                {{ task.description }}
            </p>

            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="shrink-0 text-[11px] font-semibold" :class="priorityTextClass(task)">
                    {{ priorities[task.priority] ?? task.priority }}
                </p>
                <span v-if="isOverdueTask(task)" class="shrink-0 truncate text-[11px] font-semibold text-red-500">
                    Atrasada · {{ formatTaskDate(task.due_date) }}
                </span>
                <span v-else-if="task.due_date" class="shrink-0 text-[11px] text-slate-400">
                    {{ formatTaskDate(task.due_date) }}
                </span>
            </div>

            <div v-if="task.labels.length" class="mt-1.5 flex flex-wrap items-center gap-1">
                <span v-for="l in task.labels" :key="l.id"
                      class="rounded-full border px-1.5 py-0.5 text-[10px] font-medium"
                      :class="task.status === 'done' ? 'border-slate-200 bg-slate-100 text-slate-500' : 'bg-white'"
                      :style="task.status === 'done' ? {} : { color: l.color, borderColor: l.color }">
                    {{ l.name }}
                </span>
            </div>
        </button>

        <div v-if="task.patient" class="text-[11px] text-slate-400">
            <Link :href="route('patients.prontuario', task.patient.id)"
                  class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-emerald-700 hover:underline"
                  @click.stop>
                👤 {{ patientDisplayName(task.patient) }}
            </Link>
        </div>
    </div>
</template>
