<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useToast } from '@/composables/useToast'
import TaskSidebar from './TaskSidebar.vue'
import TaskListView from './TaskListView.vue'
import TaskFormModal from './TaskFormModal.vue'
import TaskListSettingsModal from './TaskListSettingsModal.vue'
import TaskControlPanel from './TaskControlPanel.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const toast = useToast()

// ── Dados (busca própria via axios — não é mais uma página Inertia, então
// precisa carregar e mutar o próprio estado) ────────────────────────────────
const loading = ref(false)
// Erro persistente (não só o toast, que passa despercebido) — sem isso, uma
// falha na busca inicial (sessão expirada, 500, rede) deixa status/prioridade/
// responsável/tarefas vazios pra sempre, sem nenhuma pista visível na tela.
const loadError = ref(false)
const tasks = ref([])
const statuses = ref({})
const priorities = ref({})
const teamMembers = ref([])
const availableLabels = ref([])
const lists = ref({}) // { mine: {key,name,color,...}, team: {...}, custom: [{id,name,color,is_owner,...}] }
const currentUserId = ref(null)

const scope = ref('mine')
// Padrão "Sempre" (sem corte de dias) — a TaskListingService já protege o
// volume trafegado com um teto de linhas (ver comentário lá), então não há
// razão técnica pra esconder histórico por padrão. Só usado pela visão
// "Concluídas".
const completedWindow = ref('all')
const activeView = ref('inbox')
// 'list' | 'board' — o Board é só uma segunda apresentação do mesmo estado
// (buckets), não busca nada por conta própria nem tem sua própria noção de
// escopo/filtro (ver TaskBoard.vue).
const layoutMode = ref('list')

// Board se beneficia de mais espaço horizontal (4 colunas lado a lado) — a
// Lista mantém exatamente a largura de sempre. `transition-[width]` deixa a
// troca suave em vez de "pular"; volta ao tamanho da Lista assim que
// layoutMode muda de novo (nenhum estado próprio, é só uma classe reativa).
const modalSizeClass = computed(() => layoutMode.value === 'board'
    ? 'w-[97vw] max-w-[1800px]'
    : 'w-[92vw] max-w-[1400px]')

// Busca e prioridade são filtros client-side — o painel já carrega tudo do
// escopo ativo de uma vez, então filtrar em cima disso é instantâneo e não
// precisa de round-trip. Só escopo e período de concluídas mudam o conjunto
// de dados buscado no servidor (ver fetchTasks).
const search = ref('')
const priorityFilter = ref('')
const labelFilter = ref('')

async function fetchTasks() {
    loading.value = true
    loadError.value = false
    try {
        const { data } = await window.axios.get(route('tasks.index'), {
            params: { scope: scope.value, days: completedWindow.value },
        })
        tasks.value = data.tasks
        statuses.value = data.statuses
        priorities.value = data.priorities
        teamMembers.value = data.teamMembers
        availableLabels.value = data.availableLabels
        lists.value = data.lists
        currentUserId.value = data.currentUserId
    } catch {
        // 401 (sessão expirada) já é tratado globalmente em bootstrap.js
        // (redireciona pro login); o que sobra aqui são falhas reais (500,
        // rede) — ficam com um estado de erro persistente + botão de tentar
        // de novo, não só um toast que some sozinho.
        loadError.value = true
        toast.error('Não foi possível carregar as tarefas.')
    } finally {
        loading.value = false
    }
}

watch(() => props.show, (open) => { if (open) fetchTasks() })

// Ao trocar de escopo ou de período de concluídas, limpa a lista antes de
// buscar de novo — sem isso os contadores da sidebar ficam mostrando números
// do estado anterior por um instante enquanto o conteúdo já exibe
// "Carregando...", o que é confuso.
function refetch() {
    if (!props.show) return
    tasks.value = []
    fetchTasks()
}
watch(scope, refetch)
watch(completedWindow, refetch)

// ── Filtros client-side + visões — calculados a partir de um único payload,
// tudo instantâneo (sem rede) exceto quando scope/completedWindow mudam. ────
const todayStr = () => new Date().toISOString().slice(0, 10)

function bucketOf(task) {
    if (task.status === 'done') return 'done'
    if (!task.due_date) return 'inbox'
    // "Hoje" absorve atrasadas — nada fica esquecido numa visão que ninguém abre.
    return task.due_date.slice(0, 10) <= todayStr() ? 'today' : 'upcoming'
}

// "Compartilhar → Selecionar profissionais" filtra no cliente, no mesmo
// padrão de prioridade/etiqueta — o painel já carrega todas as tarefas do
// escopo de uma vez, então não precisa de endpoint novo. Só se aplica à
// visão "Tarefas da equipe" quando o usuário configurou esse modo na
// engrenagem (ver TaskListSettingsModal).
const teamList = computed(() => lists.value.team)
const sharingFilterActive = computed(() => scope.value === 'team' && teamList.value?.sharing_type === 'selected')

// Escopo personalizado ativo (null em mine/team) — decide se o formulário
// mostra Paciente (uso pessoal, mesma lógica de "Minhas tarefas") ou
// Responsável (colaborativo, mesma lógica de "Tarefas da equipe").
const activeCustomList = computed(() => {
    if (scope.value === 'mine' || scope.value === 'team') return null
    return (lists.value.custom || []).find((l) => String(l.id) === String(scope.value)) || null
})
const showPatientField = computed(() => scope.value === 'mine' || activeCustomList.value?.sharing_type === 'private')

function taskOwnerId(task) {
    return task.assigned_to ?? task.created_by
}

// Urgente sempre antes de alta, alta antes de média, e assim por diante —
// mesmo que a mais recente tenha sido adicionada por último (ver
// filteredTasks). Chaves espelham Task::PRIORITIES no backend.
const PRIORITY_ORDER = { urgente: 0, alta: 1, media: 2, baixa: 3 }

const filteredTasks = computed(() => {
    let list = tasks.value

    if (sharingFilterActive.value) {
        const allowed = new Set(teamList.value.shared_user_ids || [])
        list = list.filter((t) => allowed.has(taskOwnerId(t)))
    }

    if (priorityFilter.value) {
        list = list.filter((t) => t.priority === priorityFilter.value)
    }

    if (labelFilter.value) {
        list = list.filter((t) => t.labels.some((l) => String(l.id) === String(labelFilter.value)))
    }

    const q = search.value.trim().toLowerCase()
    if (q) {
        list = list.filter((t) => t.title.toLowerCase().includes(q) || (t.description ?? '').toLowerCase().includes(q))
    }

    // Fixadas primeiro; dentro de cada grupo (fixadas / não fixadas), sempre
    // por prioridade — urgente > alta > média > baixa, independente da ordem
    // de criação. Mesma prioridade mantém a ordem que já veio do servidor
    // (mais recente primeiro), já que Array.prototype.sort é estável.
    return [...list].sort((a, b) => {
        const pinDiff = (b.pinned_at ? 1 : 0) - (a.pinned_at ? 1 : 0)
        if (pinDiff !== 0) return pinDiff
        return (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
    })
})

function isOverdueTask(task) {
    if (!task.due_date || task.status === 'done') return false
    return task.due_date.slice(0, 10) < todayStr()
}

// Regra própria pra tarefas atrasadas dentro da aba "Hoje": urgente sempre
// primeiro (não importa a data); as demais por vencimento (mais antiga
// primeiro); prioridade (alta > média > baixa) como desempate de data igual;
// ordem de criação como último desempate (sort é estável). Só entra em jogo
// quando os DOIS lados da comparação estão atrasados — tarefas que vencem
// hoje (não atrasadas) continuam pela regra de prioridade de sempre.
function compareTodayBucket(a, b) {
    const pinDiff = (b.pinned_at ? 1 : 0) - (a.pinned_at ? 1 : 0)
    if (pinDiff !== 0) return pinDiff

    if (isOverdueTask(a) && isOverdueTask(b)) {
        if (a.priority === 'urgente' && b.priority !== 'urgente') return -1
        if (b.priority === 'urgente' && a.priority !== 'urgente') return 1
        if (a.due_date !== b.due_date) return a.due_date < b.due_date ? -1 : 1
        return (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
    }

    return (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
}

// "Concluídas" ignora prioridade por completo — o único critério é o
// momento da conclusão (completed_at DESC), a mais recente primeiro; id DESC
// só entra em caso de empate exato de timestamp, como desempate estável.
function compareDoneBucket(a, b) {
    if (a.completed_at !== b.completed_at) return a.completed_at < b.completed_at ? 1 : -1
    return b.id - a.id
}

const buckets = computed(() => {
    const b = { inbox: [], today: [], upcoming: [], done: [] }
    for (const t of filteredTasks.value) b[bucketOf(t)].push(t)
    b.today = [...b.today].sort(compareTodayBucket)
    b.done = [...b.done].sort(compareDoneBucket)
    return b
})

// Favoritos atravessa os buckets de data (não é "quando vence", é "o que
// importa agora") — junta favoritas de qualquer bucket, já filtradas e
// ordenadas (fixadas primeiro) pelo mesmo pipeline de filteredTasks.
const favoriteTasks = computed(() => filteredTasks.value.filter((t) => t.is_favorite))

const counts = computed(() => ({
    inbox: buckets.value.inbox.length,
    today: buckets.value.today.length,
    upcoming: buckets.value.upcoming.length,
    done: buckets.value.done.length,
    favorites: favoriteTasks.value.length,
}))

const viewTasks = computed(() => activeView.value === 'favorites' ? favoriteTasks.value : (buckets.value[activeView.value] ?? []))

// Contagem por status (A fazer/Em andamento/Aguardando/Concluída) — pro
// popover "Controle de status" no cabeçalho (ver TaskListView.vue). Reaproveita
// `filteredTasks` (já aplica escopo/busca/prioridade/etiqueta) em vez de
// reimplementar o filtro; é só um agrupamento a mais em cima do mesmo dado.
const statusCounts = computed(() => {
    const c = { todo: 0, doing: 0, waiting: 0, done: 0 }
    for (const t of filteredTasks.value) c[t.status] = (c[t.status] ?? 0) + 1
    return c
})

// ── Mutações locais (otimistas — sem novo fetch) ───────────────────────────
// Incrementado a cada mutação (edição, status, pin, favorito, exclusão) —
// o painel "Controle" observa isto pra se manter em dia com o que está
// vendo (ex.: título editado numa tarefa concluída) sem precisar reabrir o
// drawer. Só dispara refetch de fato se o drawer já estiver aberto (ver
// TaskControlPanel.vue).
const controlRefreshKey = ref(0)

function upsertTask(task) {
    const i = tasks.value.findIndex((t) => t.id === task.id)
    if (i === -1) tasks.value.unshift(task)
    else tasks.value[i] = task
    controlRefreshKey.value++
}

async function toggleDone(task) {
    const next = task.status === 'done' ? 'todo' : 'done'
    try {
        const { data } = await window.axios.patch(route('tasks.update-status', task.id), { status: next })
        upsertTask(data)
    } catch {
        toast.error('Não foi possível atualizar a tarefa.')
    }
}

// ── Drag and drop no Board — traduz "soltou na coluna X" pro endpoint
// dedicado (ver TaskController::move), nunca mexe em due_date/status na mão
// aqui. Não otimista de propósito: só mutamos `tasks` (e por consequência os
// buckets/colunas) depois da resposta da API — se falhar, o card já não
// tinha saído do lugar (nada pra "restaurar"), só o toast de erro. ────────
const movingTaskIds = ref(new Set())

async function moveTask(task, column, dueDate) {
    movingTaskIds.value = new Set(movingTaskIds.value).add(task.id)
    try {
        const { data } = await window.axios.patch(route('tasks.move', task.id), { column, due_date: dueDate })
        upsertTask(data)
    } catch (e) {
        toast.error(e.response?.data?.errors?.due_date?.[0] ?? 'Não foi possível mover a tarefa.')
    } finally {
        const next = new Set(movingTaskIds.value)
        next.delete(task.id)
        movingTaskIds.value = next
    }
}

async function togglePin(task) {
    try {
        const { data } = await window.axios.patch(route('tasks.toggle-pin', task.id))
        upsertTask(data)
    } catch {
        toast.error('Não foi possível fixar a tarefa.')
    }
}

async function toggleFavorite(task) {
    try {
        const { data } = await window.axios.patch(route('tasks.toggle-favorite', task.id))
        upsertTask(data)
    } catch {
        toast.error('Não foi possível favoritar a tarefa.')
    }
}

async function deleteTask(task) {
    if (!confirm(`Excluir a tarefa "${task.title}"?`)) return
    try {
        await window.axios.delete(route('tasks.destroy', task.id))
        tasks.value = tasks.value.filter((t) => t.id !== task.id)
        controlRefreshKey.value++
        toast.success('Tarefa removida.')
    } catch {
        toast.error('Não foi possível excluir a tarefa.')
    }
}

// ── Modal de edição completa — todo caminho de criação passa por aqui, sem
// atalho de criação rápida separado (ver TaskListView.vue). ───────────────
const showFormModal = ref(false)
const editingTask = ref(null)
const createDefaultDueDate = ref('')

// Criar a partir da visão "Hoje" já sugere o vencimento de hoje — essa visão
// é literalmente "o que vence hoje", então nascer sem data não faz sentido.
// Nas demais visões o campo continua vazio, como sempre foi.
function openCreate() {
    editingTask.value = null
    createDefaultDueDate.value = activeView.value === 'today' ? todayStr() : ''
    showFormModal.value = true
}
function openEdit(task) { editingTask.value = task; showFormModal.value = true }

function onTaskSaved(task) {
    const wasEditing = !!editingTask.value
    upsertTask(task)
    showFormModal.value = false
    toast.success(wasEditing ? 'Tarefa atualizada.' : 'Tarefa criada.')
}

function onLabelCreated(label) {
    availableLabels.value = [...availableLabels.value, label]
}

function onLabelDeleted(labelId) {
    availableLabels.value = availableLabels.value.filter((l) => l.id !== labelId)
    tasks.value = tasks.value.map((t) => ({ ...t, labels: t.labels.filter((l) => l.id !== labelId) }))
    if (String(labelFilter.value) === String(labelId)) labelFilter.value = ''
}

// ── Configuração de lista (nome/cor/compartilhamento) — engrenagem na
// sidebar, reflete na hora porque `lists` é o mesmo estado que ela lê.
// `editingList` guarda o objeto inteiro (não só uma key) pra cobrir os 3
// casos: fixo (tem `key`), personalizado existente (tem `id`, sem `key`) ou
// criando um novo (null). ───────────────────────────────────────────────
const showListSettings = ref(false)
const editingList = ref(null)
const editingListIsFixed = computed(() => !!editingList.value?.key)

function openListSettings(list) {
    editingList.value = list
    showListSettings.value = true
}

function openCreateList() {
    editingList.value = null
    showListSettings.value = true
}

function onListSaved(data) {
    if (data.key) {
        lists.value = { ...lists.value, [data.key]: data }
    } else {
        const custom = lists.value.custom || []
        const i = custom.findIndex((l) => l.id === data.id)
        lists.value = {
            ...lists.value,
            custom: i === -1 ? [...custom, data] : custom.map((l, idx) => (idx === i ? data : l)),
        }
    }
    showListSettings.value = false
}

function onListDeleted(id) {
    lists.value = { ...lists.value, custom: (lists.value.custom || []).filter((l) => l.id !== id) }
    showListSettings.value = false

    if (String(scope.value) === String(id)) {
        // O escopo excluído não existe mais — volta pro padrão, que já
        // dispara um novo fetch (ver watch(scope, refetch)).
        scope.value = 'mine'
    } else {
        // As tarefas do escopo excluído podem ter caído justamente no
        // escopo que está sendo visto agora (mine/team) — sem refetch elas
        // só apareceriam depois de um refresh manual.
        refetch()
    }
}

// ── Painel "Controle" (resumo de concluídas hoje) — colapsado por padrão,
// abre como um drawer sobreposto à direita (ver TaskControlPanel.vue). ────
const showControlPanel = ref(false)

// ── Teclado: Esc fecha (painel, ou nada se o modal de criação estiver
// aberto — ele tem o próprio fechamento); Enter fora de um campo abre o
// modal de criação com o cursor já no Título (fluxo "Enter inicia, Enter
// conclui", como Linear/Notion/Todoist). ───────────────────────────────────
function close() {
    if (showFormModal.value) return
    if (showControlPanel.value) { showControlPanel.value = false; return }
    emit('close')
}

function onKeydown(e) {
    if (!props.show) return

    if (e.key === 'Escape') { close(); return }

    if (e.key === 'Enter' && !showFormModal.value) {
        const tag = document.activeElement?.tagName
        const isEditable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || document.activeElement?.isContentEditable
        if (!isEditable) { e.preventDefault(); openCreate() }
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
                <Transition
                    appear
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100">
                    <div class="relative flex h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl transition-[width] duration-200 ease-out"
                         :class="modalSizeClass">
                        <TaskSidebar
                            :active-view="activeView"
                            :scope="scope"
                            :counts="counts"
                            :lists="lists"
                            @update:active-view="activeView = $event"
                            @update:scope="scope = $event"
                            @open-list-settings="openListSettings"
                            @create-list="openCreateList"
                            @close="close" />

                        <TaskListView
                            :tasks="viewTasks"
                            :view="activeView"
                            :layout-mode="layoutMode"
                            :buckets="buckets"
                            :moving-task-ids="movingTaskIds"
                            :loading="loading"
                            :load-error="loadError"
                            :search="search"
                            :priority-filter="priorityFilter"
                            :label-filter="labelFilter"
                            :completed-window="completedWindow"
                            :priorities="priorities"
                            :statuses="statuses"
                            :status-counts="statusCounts"
                            :available-labels="availableLabels"
                            :control-panel-open="showControlPanel"
                            @update:search="search = $event"
                            @update:priority-filter="priorityFilter = $event"
                            @update:label-filter="labelFilter = $event"
                            @update:completed-window="completedWindow = $event"
                            @update:layout-mode="layoutMode = $event"
                            @create="openCreate"
                            @edit="openEdit"
                            @toggle-done="toggleDone"
                            @toggle-pin="togglePin"
                            @toggle-favorite="toggleFavorite"
                            @delete="deleteTask"
                            @retry="fetchTasks"
                            @move="moveTask"
                            @open-control-panel="showControlPanel = true" />

                        <TaskControlPanel :show="showControlPanel" :refresh-key="controlRefreshKey" @close="showControlPanel = false" />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <TaskFormModal
        :show="showFormModal"
        :task="editingTask"
        :statuses="statuses"
        :priorities="priorities"
        :team-members="teamMembers"
        :available-labels="availableLabels"
        :default-due-date="createDefaultDueDate"
        :show-patient-field="showPatientField"
        :active-list-id="activeCustomList?.id ?? null"
        @close="showFormModal = false"
        @saved="onTaskSaved"
        @label-created="onLabelCreated"
        @label-deleted="onLabelDeleted" />

    <TaskListSettingsModal
        :show="showListSettings"
        :list="editingList"
        :is-fixed="editingListIsFixed"
        :team-members="teamMembers"
        @close="showListSettings = false"
        @saved="onListSaved"
        @deleted="onListDeleted" />
</template>
