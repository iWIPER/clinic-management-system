<script setup>
import { ref, computed } from 'vue'
import TaskBoardCard from './TaskBoardCard.vue'

const props = defineProps({
    // { inbox: [...], today: [...], upcoming: [...], done: [...] } — o mesmo
    // computed já usado pela Lista (TaskPanel.vue), incluindo busca/
    // prioridade/etiqueta/pin/ordenação de Concluídas por completed_at DESC.
    // O Board não reclassifica nada por conta própria.
    buckets: { type: Object, required: true },
    priorities: { type: Object, required: true },
    statuses: { type: Object, required: true },
    movingTaskIds: { type: Object, default: () => new Set() },
})

const emit = defineEmits(['edit', 'toggle-done', 'toggle-favorite', 'move'])

const COLUMNS = [
    { key: 'inbox', label: 'Entrada' },
    { key: 'today', label: 'Hoje' },
    { key: 'upcoming', label: 'Próximas' },
    { key: 'done', label: 'Concluídas' },
]

// ── Drag and drop nativo (sem lib) — o estado de "quem está sendo
// arrastado" fica aqui, não no dataTransfer, porque tudo acontece dentro do
// mesmo app Vue; dataTransfer só recebe o id pra manter o comportamento
// nativo do navegador consistente (Firefox exige setData pra iniciar).
//
// `dragOverColumn` só é setado via `dragover` (dispara continuamente
// enquanto paira, inclusive sobre os cards filhos, já que bubbla) e só é
// limpo em `drop`/`dragend` — de propósito NÃO usamos `dragleave` pra
// limpar: como ele também dispara ao entrar num card filho (bubbling entre
// pai/filho), limpar nele fazia o destaque da coluna "piscar" a cada card
// sobrevoado. Trocar de coluna já resolve sozinho (o dragover da nova
// coluna sobrescreve o valor). ─────────────────────────────────────────
const draggingTask = ref(null)
const draggingFromColumn = ref(null)
const dragOverColumn = ref(null)

function onCardDragStart(task, fromColumn) {
    draggingTask.value = task
    draggingFromColumn.value = fromColumn
}
function onCardDragEnd() {
    draggingTask.value = null
    draggingFromColumn.value = null
    dragOverColumn.value = null
}

// ── "Próximas" exige uma data futura — nunca aplicamos "amanhã" em
// silêncio; um pequeno popover pede confirmação, já com "amanhã" só como
// valor inicial editável. ───────────────────────────────────────────────
const pendingUpcoming = ref(null)
const pendingUpcomingDate = ref('')
const pendingUpcomingError = ref('')

function tomorrowStr() {
    const d = new Date()
    d.setDate(d.getDate() + 1)
    return d.toISOString().slice(0, 10)
}
const todayStr = () => new Date().toISOString().slice(0, 10)
const minUpcomingDate = computed(() => tomorrowStr())

function cancelPendingUpcoming() {
    pendingUpcoming.value = null
    pendingUpcomingDate.value = ''
    pendingUpcomingError.value = ''
}

function confirmPendingUpcoming() {
    if (!pendingUpcomingDate.value || pendingUpcomingDate.value <= todayStr()) {
        pendingUpcomingError.value = 'Escolha uma data futura.'
        return
    }
    emit('move', pendingUpcoming.value, 'upcoming', pendingUpcomingDate.value)
    cancelPendingUpcoming()
}

function onDrop(columnKey) {
    dragOverColumn.value = null
    const task = draggingTask.value
    const fromColumn = draggingFromColumn.value
    draggingTask.value = null
    draggingFromColumn.value = null
    if (!task || columnKey === fromColumn) return

    if (columnKey === 'upcoming') {
        pendingUpcoming.value = task
        pendingUpcomingDate.value = tomorrowStr()
        pendingUpcomingError.value = ''
        return
    }

    emit('move', task, columnKey, null)
}
</script>

<template>
    <div class="flex h-full gap-4 overflow-x-auto px-6 py-4">
        <!-- `flex-1` com piso (`min-w`) — em telas largas as 4 colunas
             esticam pra preencher o espaço extra do modal (ver TaskPanel.vue,
             modo Board é mais largo); abaixo do piso, a rolagem horizontal do
             container pai assume (comportamento já validado em telas
             menores). -->
        <div v-for="col in COLUMNS" :key="col.key"
             :data-column="col.key"
             class="flex h-full min-w-[272px] flex-1 flex-col rounded-xl transition-colors"
             :class="dragOverColumn === col.key ? 'bg-emerald-50 ring-2 ring-emerald-300' : 'bg-slate-50/70'"
             @dragenter.prevent
             @dragover.prevent="dragOverColumn = col.key"
             @drop.prevent="onDrop(col.key)">
            <div class="flex shrink-0 items-center justify-between px-3 py-2.5">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ col.label }}</h4>
                <span class="min-w-[1.25rem] rounded-full bg-slate-200 px-1.5 py-0.5 text-center text-[10px] font-semibold text-slate-500">
                    {{ (buckets[col.key] || []).length }}
                </span>
            </div>

            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-3">
                <p v-if="!(buckets[col.key] || []).length" class="pt-6 text-center text-xs text-slate-400">
                    Nenhuma tarefa aqui.
                </p>

                <TaskBoardCard
                    v-for="task in buckets[col.key]" :key="task.id"
                    :task="task" :priorities="priorities" :statuses="statuses"
                    :dragging="draggingTask?.id === task.id"
                    :moving="movingTaskIds.has(task.id)"
                    @dragstart="onCardDragStart(task, col.key)"
                    @dragend="onCardDragEnd"
                    @edit="$emit('edit', task)"
                    @toggle-done="$emit('toggle-done', task)"
                    @toggle-favorite="$emit('toggle-favorite', task)" />
            </div>
        </div>

        <!-- Popover de data pra "Próximas" — pequeno, centralizado, some ao
             clicar fora ou cancelar; nada é movido até confirmar. -->
        <div v-if="pendingUpcoming" data-testid="upcoming-date-popover" class="fixed inset-0 z-30 flex items-center justify-center bg-black/20" @click.self="cancelPendingUpcoming">
            <div class="w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                <p class="text-sm font-semibold text-slate-800">Mover para "Próximas"</p>
                <p class="mt-1 text-xs text-slate-500">Escolha a nova data de vencimento — precisa ser uma data futura.</p>
                <input v-model="pendingUpcomingDate" type="date" :min="minUpcomingDate" data-testid="upcoming-date-input"
                       class="mt-3 w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                <p v-if="pendingUpcomingError" class="mt-1 text-xs text-red-500">{{ pendingUpcomingError }}</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="cancelPendingUpcoming" data-testid="upcoming-date-cancel"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100">
                        Cancelar
                    </button>
                    <button type="button" @click="confirmPendingUpcoming" data-testid="upcoming-date-confirm"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-700">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
