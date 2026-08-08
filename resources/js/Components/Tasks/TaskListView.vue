<script setup>
import { computed } from 'vue'
import { ClipboardDocumentCheckIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
import NavbarIconButton from '@/Components/Navbar/NavbarIconButton.vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import TaskListItem from './TaskListItem.vue'
import TaskBoard from './TaskBoard.vue'
import { statusIconFor, statusIconClass } from './taskPresentation'

// Só os status "em aberto" — Concluída não entra aqui: esse popover é sobre
// o andamento do que ainda está em curso, "Concluída" já tem lugar próprio
// (a aba/coluna Concluídas, ordenada por completed_at).
const STATUS_ORDER = ['todo', 'doing', 'waiting']

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    view: { type: String, required: true },
    // 'list' | 'board' — Board ignora `tasks` (visão de um bucket só) e usa
    // `buckets` (os 4 de uma vez), já filtrados/ordenados do mesmo jeito.
    layoutMode: { type: String, default: 'list' },
    buckets: { type: Object, default: () => ({ inbox: [], today: [], upcoming: [], done: [] }) },
    movingTaskIds: { type: Object, default: () => new Set() },
    loading: { type: Boolean, default: false },
    loadError: { type: Boolean, default: false },
    search: { type: String, default: '' },
    priorityFilter: { type: String, default: '' },
    labelFilter: { type: String, default: '' },
    completedWindow: { type: String, default: 'all' },
    priorities: { type: Object, required: true },
    statuses: { type: Object, required: true },
    statusCounts: { type: Object, default: () => ({ todo: 0, doing: 0, waiting: 0, done: 0 }) },
    availableLabels: { type: Array, default: () => [] },
    // Só pra destacar o ícone quando o drawer já está aberto (mesmo padrão
    // de `:active` do NavbarIconButton lá no menu superior) — quem guarda o
    // estado de verdade é o TaskPanel.vue.
    controlPanelOpen: { type: Boolean, default: false },
})

const emit = defineEmits([
    'update:search', 'update:priority-filter', 'update:label-filter', 'update:completed-window', 'update:layout-mode',
    'create', 'edit', 'toggle-done', 'delete', 'retry', 'toggle-pin', 'toggle-favorite', 'move', 'open-control-panel',
])

// Meta por visão — texto e dica mudam conforme a visão ativa (ou "Board",
// quando layoutMode é 'board' — ver meta computed abaixo).
const VIEW_META = {
    inbox:     { title: 'Entrada',    hint: 'Sem data de vencimento — organize ou agende quando puder.', empty: 'Tudo organizado por aqui.' },
    today:     { title: 'Hoje',       hint: 'Vencendo hoje ou atrasadas.',                                empty: 'Nada para hoje.' },
    upcoming:  { title: 'Próximas',   hint: 'Vencendo nos próximos dias.',                                empty: 'Nenhuma tarefa agendada.' },
    done:      { title: 'Concluídas', hint: 'Histórico de tarefas concluídas.',                           empty: 'Nenhuma tarefa concluída neste período.' },
    favorites: { title: 'Favoritos',  hint: 'Suas tarefas marcadas como favoritas.',                      empty: 'Nenhuma tarefa favoritada ainda.' },
}

const COMPLETED_WINDOW_OPTIONS = [
    { value: '7',   label: '7 dias' },
    { value: '30',  label: '30 dias' },
    { value: '90',  label: '90 dias' },
    { value: '365', label: '1 ano' },
    { value: 'all', label: 'Sempre' },
]

const meta = computed(() => {
    if (props.layoutMode === 'board') return { title: 'Board', hint: 'Arraste os cards entre as colunas.' }
    return VIEW_META[props.view] ?? VIEW_META.inbox
})

// O período de Concluídas vale pra coluna "Concluídas" do Board também
// (mesmo filtro, já aplicado nos buckets vindos do TaskPanel) — por isso o
// seletor some do texto genérico e aparece também em modo Board.
const showPeriodSelect = computed(() => props.view === 'done' || props.layoutMode === 'board')

// ── Faixa de filtros ativos — cada chip sabe limpar só o próprio filtro. ──
const activeFilterChips = computed(() => {
    const chips = []

    if (props.search.trim()) {
        chips.push({ key: 'search', label: `Busca: "${props.search.trim()}"`, clear: () => emit('update:search', '') })
    }
    if (props.priorityFilter) {
        chips.push({
            key: 'priority',
            label: `Prioridade: ${props.priorities[props.priorityFilter] ?? props.priorityFilter}`,
            clear: () => emit('update:priority-filter', ''),
        })
    }
    if (props.labelFilter) {
        const found = props.availableLabels.find((l) => String(l.id) === String(props.labelFilter))
        chips.push({ key: 'label', label: `Etiqueta: ${found?.name ?? '—'}`, clear: () => emit('update:label-filter', '') })
    }
    if (showPeriodSelect.value && props.completedWindow !== 'all') {
        const opt = COMPLETED_WINDOW_OPTIONS.find((o) => o.value === props.completedWindow)
        chips.push({ key: 'period', label: `Período: ${opt?.label ?? props.completedWindow}`, clear: () => emit('update:completed-window', 'all') })
    }

    return chips
})

const hasActiveFilter = computed(() => activeFilterChips.value.length > 0)

const emptyMessage = computed(() => hasActiveFilter.value ? 'Nenhuma tarefa encontrada.' : meta.value.empty)

function clearFilters() {
    activeFilterChips.value.forEach((chip) => chip.clear())
}
</script>

<template>
    <section class="flex min-w-0 flex-1 flex-col">
        <header class="flex items-center justify-between gap-4 border-b px-6 py-4">
            <div>
                <h3 class="text-base font-semibold text-slate-800">{{ meta.title }}</h3>
                <p v-if="!showPeriodSelect" class="text-xs text-slate-400">{{ meta.hint }}</p>
                <!-- `p-0` do padrão "hint" removia o espaço reservado pra seta
                     nativa (vinda do @tailwindcss/forms), fazendo ela cair em
                     cima do texto — `pr-6` devolve um respiro pequeno,
                     suficiente pra nunca sobrepor, sem afastar demais. -->
                <select v-else :value="completedWindow" @change="$emit('update:completed-window', $event.target.value)"
                        class="-ml-1 border-0 bg-transparent py-0 pl-1 pr-6 text-xs text-slate-400 focus:ring-0">
                    <option v-for="opt in COMPLETED_WINDOW_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <input
                        :value="search"
                        @input="$emit('update:search', $event.target.value)"
                        type="text"
                        placeholder="Buscar..."
                        class="w-40 rounded-lg border-slate-200 pl-8 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                </div>

                <select :value="priorityFilter" @change="$emit('update:priority-filter', $event.target.value)"
                        class="rounded-lg border-slate-200 text-sm text-slate-600 transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Todas</option>
                    <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
                </select>

                <select v-if="availableLabels.length" :value="labelFilter" @change="$emit('update:label-filter', $event.target.value)"
                        class="rounded-lg border-slate-200 text-sm text-slate-600 transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Todas as etiquetas</option>
                    <option v-for="l in availableLabels" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>

                <div class="flex items-center rounded-lg border p-0.5 text-xs font-medium text-slate-500">
                    <button type="button" @click="$emit('update:layout-mode', 'list')"
                            class="rounded-md px-2.5 py-1 transition-colors"
                            :class="layoutMode === 'list' ? 'bg-white text-slate-800 shadow-sm' : 'hover:text-slate-700'">
                        Lista
                    </button>
                    <button type="button" @click="$emit('update:layout-mode', 'board')"
                            class="rounded-md px-2.5 py-1 transition-colors"
                            :class="layoutMode === 'board' ? 'bg-white text-slate-800 shadow-sm' : 'hover:text-slate-700'">
                        Board
                    </button>
                </div>

                <!-- Acesso ao painel "Controle" — mesmo componente/ícone/
                     tooltip usados no menu superior do sistema (ver
                     NavbarIconButton.vue), só que abrindo o drawer já
                     existente (TaskControlPanel.vue) em vez de navegar. -->
                <NavbarIconButton tooltip="Painel de controle" :active="controlPanelOpen" @click="$emit('open-control-panel')">
                    <ClipboardDocumentCheckIcon class="h-5 w-5" stroke-width="1.8" />
                </NavbarIconButton>

                <!-- "Controle de status" — popover pequeno (mesma mecânica de
                     toggle/clique-fora/Esc do NavbarDropdown já usada no "?"
                     de ajuda, ver TaskSidebar.vue), não o drawer grande. Só
                     um resumo rápido de quantas tarefas há em cada status,
                     reaproveitando os mesmos ícone/cor por status dos cards
                     (taskPresentation.js) — nada de regra nova. -->
                <NavbarDropdown align="right" width="w-56">
                    <template #trigger>
                        <NavbarIconButton tooltip="Controle de status">
                            <Squares2X2Icon class="h-5 w-5" stroke-width="1.8" />
                        </NavbarIconButton>
                    </template>
                    <template #default>
                        <div class="p-3">
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Status das tarefas</p>
                            <ul class="space-y-1.5">
                                <li v-for="key in STATUS_ORDER" :key="key" class="flex items-center justify-between gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2 text-slate-600">
                                        <component :is="statusIconFor({ status: key })" class="h-4 w-4 shrink-0" :class="statusIconClass({ status: key })" />
                                        <span class="truncate">{{ statuses[key] ?? key }}</span>
                                    </span>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">
                                        {{ statusCounts[key] ?? 0 }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </template>
                </NavbarDropdown>
            </div>
        </header>

        <div v-if="activeFilterChips.length" class="flex flex-wrap items-center gap-1.5 border-b bg-slate-50/70 px-6 py-2">
            <span class="text-xs text-slate-400">Filtrando por:</span>
            <span v-for="chip in activeFilterChips" :key="chip.key"
                  class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white py-0.5 pl-2 pr-1 text-xs font-medium text-slate-600">
                {{ chip.label }}
                <button type="button" @click="chip.clear" class="rounded-full p-0.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700">
                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
            <button type="button" @click="clearFilters" class="ml-auto text-xs font-medium text-emerald-700 hover:text-emerald-800">
                Limpar tudo
            </button>
        </div>

        <!-- CTA de criação — alinhado à esquerda, junto da sidebar; Enter (em
             qualquer lugar do painel fora de um campo) abre o mesmo modal,
             ver TaskPanel.vue. -->
        <div class="flex items-center justify-between gap-3 border-b px-6 py-3">
            <button type="button" @click="$emit('create')"
                    class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
                <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Pressione Enter para criar uma nova tarefa
            </button>
            <button type="button" @click="$emit('create')"
                    class="shrink-0 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700">
                Adicionar
            </button>
        </div>

        <!-- Coluna de leitura colada à sidebar — teto de largura só pra
             linhas não voltarem a esticar de ponta a ponta do workspace.
             Board usa rolagem horizontal (colunas) em vez de vertical. -->
        <div class="flex-1" :class="layoutMode === 'board' ? 'overflow-x-auto overflow-y-hidden' : 'overflow-y-auto'">
            <!-- Carregando/vazio centralizam no espaço todo — não são uma
                 "lista", então não faz sentido prendê-los na coluna estreita. -->
            <div v-if="loading" class="flex h-[50vh] items-center justify-center text-sm text-slate-400">
                Carregando...
            </div>

            <div v-else-if="loadError" class="flex h-[50vh] flex-col items-center justify-center gap-2 text-center">
                <svg class="h-8 w-8 text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <p class="text-sm font-medium text-slate-500">Não foi possível carregar as tarefas.</p>
                <button type="button" @click="$emit('retry')"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50">
                    Tentar novamente
                </button>
            </div>

            <TaskBoard v-else-if="layoutMode === 'board'"
                       :buckets="buckets" :priorities="priorities" :statuses="statuses" :moving-task-ids="movingTaskIds"
                       @edit="$emit('edit', $event)"
                       @toggle-done="$emit('toggle-done', $event)"
                       @toggle-favorite="$emit('toggle-favorite', $event)"
                       @move="(task, column, dueDate) => $emit('move', task, column, dueDate)" />

            <div v-else-if="tasks.length === 0" class="flex h-[50vh] flex-col items-center justify-center gap-2 text-center">
                <svg class="h-8 w-8 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium text-slate-500">{{ emptyMessage }}</p>
                <button v-if="hasActiveFilter" type="button" @click="clearFilters"
                        class="text-xs font-medium text-emerald-700 hover:text-emerald-800">
                    Limpar filtros
                </button>
            </div>

            <TransitionGroup v-else name="task-card" tag="div" class="max-w-2xl space-y-2 px-6 py-3">
                <TaskListItem
                    v-for="task in tasks"
                    :key="task.id"
                    :task="task"
                    :priorities="priorities"
                    :statuses="statuses"
                    @edit="$emit('edit', task)"
                    @delete="$emit('delete', task)"
                    @toggle-done="$emit('toggle-done', task)"
                    @toggle-pin="$emit('toggle-pin', task)"
                    @toggle-favorite="$emit('toggle-favorite', task)" />
            </TransitionGroup>
        </div>
    </section>
</template>

<style scoped>
.task-card-enter-active, .task-card-leave-active { transition: all 0.2s ease; }
.task-card-enter-from, .task-card-leave-to { opacity: 0; transform: translateY(-4px); }
.task-card-move { transition: transform 0.2s ease; }
</style>
