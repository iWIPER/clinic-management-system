<script setup>
import { ref } from 'vue'
import TaskBoardCard from './TaskBoardCard.vue'

const props = defineProps({
    // { todo: [...], doing: [...], done: [...] } — kanbanBuckets computado
    // em TaskPanel.vue a partir do MESMO filteredTasks da Lista (busca/
    // prioridade/etiqueta/pin/ordenação de Feito por completed_at DESC). O
    // Board não reclassifica nada por conta própria, só desenha as 3 colunas.
    buckets: { type: Object, required: true },
    priorities: { type: Object, required: true },
    statuses: { type: Object, required: true },
    movingTaskIds: { type: Object, default: () => new Set() },
})

const emit = defineEmits(['edit', 'toggle-done', 'toggle-favorite', 'update-status'])

// As 3 únicas colunas do Kanban — chave = status real (ver Task::KANBAN_STATUSES
// no backend, única fonte dos labels). Nada de Entrada/Hoje/Próximas/
// Concluídas aqui: esses continuam existindo só como filtros/visões da
// Lista (ver TaskSidebar.vue), não como estrutura do Board.
const COLUMNS = [
    { key: 'todo', label: 'A Fazer' },
    { key: 'doing', label: 'Fazendo' },
    { key: 'done', label: 'Feito' },
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

// Soltar num status já é uma mudança direta — a coluna EXISTE que nem o
// status real (todo/doing/done), sem tradução nenhuma no meio (diferente
// do antigo /move, que traduzia "coluna" pra due_date). Reabrir (Feito →
// Fazendo/A Fazer) já é suportado pelo mesmo endpoint (ver
// TaskController::updateStatus), sem regra extra aqui.
function onDrop(columnKey) {
    dragOverColumn.value = null
    const task = draggingTask.value
    const fromColumn = draggingFromColumn.value
    draggingTask.value = null
    draggingFromColumn.value = null
    if (!task || columnKey === fromColumn) return

    emit('update-status', task, columnKey)
}

// ── Mobile: 1 coluna por vez + tabs, sem depender do Drag and Drop nativo
// (não funciona em touch) — troca de coluna por tabs, mover uma tarefa por
// uma ação explícita "Mover para..." em cada card (ver TaskBoardCard.vue).
// Desktop (`lg:` acima) continua 100% inalterado: mesmas 3 colunas lado a
// lado com drag-and-drop nativo, ver bloco `hidden lg:flex` abaixo. ────────
const mobileActiveColumn = ref('todo')

// Opções pra "Mover para..." de um card no mobile — as OUTRAS colunas, não a
// que ele já está (mesma regra que já existia no onDrop: mover pra própria
// coluna não é uma ação real).
function moveOptionsFor(columnKey) {
    return COLUMNS.filter((c) => c.key !== columnKey)
}
</script>

<template>
    <!-- Desktop (≥lg): 3 colunas lado a lado, drag-and-drop nativo — comportamento
         inalterado da R2/versões anteriores. -->
    <div class="hidden h-full gap-4 overflow-x-auto px-6 py-4 lg:flex">
        <!-- `flex-1` com piso (`min-w`) — as 3 colunas (1fr 1fr 1fr) dividem
             igualmente a largura disponível do modal (mesma largura de
             Lista e Board agora, ver TaskPanel.vue); abaixo do piso, a
             rolagem horizontal do container pai assume (telas menores). -->
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
    </div>

    <!-- Mobile (<lg): 1 coluna por vez + tabs — Drag and Drop nativo não
         funciona em touch, então a troca de coluna é por tab e mover uma
         tarefa é uma ação explícita "Mover para..." em cada card. -->
    <div class="flex h-full flex-col lg:hidden">
        <div class="flex shrink-0 gap-1 border-b px-3 py-2">
            <button v-for="col in COLUMNS" :key="col.key" type="button"
                    @click="mobileActiveColumn = col.key"
                    class="flex-1 rounded-lg py-2 text-xs font-semibold uppercase tracking-wide transition-colors"
                    :class="mobileActiveColumn === col.key ? 'bg-emerald-100 text-emerald-800' : 'text-slate-500 hover:bg-slate-100'">
                {{ col.label }}
                <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                      :class="mobileActiveColumn === col.key ? 'bg-emerald-200 text-emerald-800' : 'bg-slate-200 text-slate-500'">
                    {{ (buckets[col.key] || []).length }}
                </span>
            </button>
        </div>

        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-3">
            <p v-if="!(buckets[mobileActiveColumn] || []).length" class="pt-6 text-center text-xs text-slate-400">
                Nenhuma tarefa aqui.
            </p>

            <TaskBoardCard
                v-for="task in buckets[mobileActiveColumn]" :key="task.id"
                :task="task" :priorities="priorities" :statuses="statuses"
                :draggable="false"
                :moving="movingTaskIds.has(task.id)"
                :move-options="moveOptionsFor(mobileActiveColumn)"
                @edit="$emit('edit', task)"
                @toggle-done="$emit('toggle-done', task)"
                @toggle-favorite="$emit('toggle-favorite', task)"
                @move-to="(status) => $emit('update-status', task, status)" />
        </div>
    </div>
</template>
