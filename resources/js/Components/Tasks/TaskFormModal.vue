<script setup>
import { reactive, ref, computed, watch, nextTick } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import PatientCombobox from './PatientCombobox.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
    // Mapa completo (inclui o status legado 'waiting') — só usado pra
    // rotular a opção extra abaixo quando necessário, nunca pra montar a
    // lista principal do dropdown (ver statusOptions).
    statuses: { type: Object, required: true },
    // As 3 opções reais oferecidas na criação/edição — ver Task::KANBAN_STATUSES.
    kanbanStatuses: { type: Object, required: true },
    priorities: { type: Object, required: true },
    teamMembers: { type: Array, default: () => [] },
    availableLabels: { type: Array, default: () => [] },
    // "Minhas tarefas" (e escopos personalizados "só eu") já pertencem a
    // quem está criando — Responsável não faz sentido, então vira campo
    // Paciente (prepara "tarefas relacionadas" no prontuário). Escopos
    // colaborativos (Tarefas da equipe, personalizados team/selected)
    // continuam com Responsável, que é como se delega trabalho.
    showPatientField: { type: Boolean, default: true },
    // Escopo personalizado ativo (id) — null em mine/team. Vai junto no
    // payload como task_list_id.
    activeListId: { type: [Number, String], default: null },
    // Vencimento pré-preenchido ao criar (ex.: abrir "Nova tarefa" a partir da
    // visão "Hoje" já sugere a data de hoje — não faz sentido nascer vazia
    // numa visão que é literalmente "o que vence hoje").
    defaultDueDate: { type: String, default: '' },
})

const emit = defineEmits(['close', 'saved', 'label-created', 'label-deleted'])

// Dropdown de status: só as 3 opções reais do Kanban (A Fazer/Fazendo/
// Feito) — exceto quando editando uma tarefa que já tem um status legado
// (ex.: 'waiting'/Aguardando, ver Task::STATUSES no backend): nesse caso a
// opção atual entra também, senão o <select> ficaria sem nenhuma opção
// batendo com o valor da tarefa. Nunca é oferecida pra tarefa nova nem
// depois que o usuário troca pra um dos 3 status reais.
const statusOptions = computed(() => {
    const opts = { ...props.kanbanStatuses }
    if (props.task && !(props.task.status in opts)) {
        opts[props.task.status] = props.statuses[props.task.status] ?? props.task.status
    }
    return opts
})

const initialForm = () => ({
    title: '',
    description: '',
    status: 'todo',
    // Vazio (não "media" pré-selecionado) — igual ao "—" do campo Paciente,
    // reforça que é uma escolha real do usuário, não um valor que já vem
    // preenchido escondido atrás do asterisco de obrigatório.
    priority: '',
    assigned_to: '',
    due_date: props.defaultDueDate,
    label_ids: [],
})

const form = reactive(initialForm())
// Paciente vive fora do `form` reativo simples porque o combobox trabalha
// com o objeto inteiro (pra já nascer mostrando o nome), não só o id — o id
// é extraído dele só na hora de montar o payload (submit()).
const patient = ref(null)
const errors = ref({})
const processing = ref(false)
const titleInput = ref(null)

// Etiquetas visíveis no picker — cópia local do prop para o rótulo recém
// criado aparecer selecionável na hora, sem esperar o round-trip do painel.
const labels = ref([...props.availableLabels])
watch(() => props.availableLabels, (v) => { labels.value = [...v] })

// "Urgente" não faz sentido pra uma tarefa agendada pra depois de hoje (cai
// em "Próximas") — é uma contradição: se é urgente, é pra hoje. Mesmo corte
// de data que já classifica a tarefa como "Próximas" no painel (bucketOf em
// TaskPanel.vue): devido de hoje ou sem data = ok; depois de hoje = bloqueia.
const todayStr = () => new Date().toISOString().slice(0, 10)
const dueDateIsUpcoming = () => !!form.due_date && form.due_date > todayStr()

watch(() => form.due_date, () => {
    if (form.priority === 'urgente' && dueDateIsUpcoming()) {
        form.priority = ''
        toast.error('Prioridade urgente não está disponível para tarefas em "Próximas" — escolha outra prioridade.')
    }
})

watch(() => props.show, (open) => {
    if (!open) return

    if (props.task) {
        Object.assign(form, {
            title: props.task.title,
            description: props.task.description || '',
            status: props.task.status,
            priority: props.task.priority,
            assigned_to: props.task.assigned_to || '',
            due_date: props.task.due_date ? props.task.due_date.slice(0, 10) : '',
            label_ids: props.task.labels?.map((l) => l.id) || [],
        })
        patient.value = props.task.patient || null
    } else {
        Object.assign(form, initialForm())
        patient.value = null
    }
    errors.value = {}
    showNewLabel.value = false

    // Cursor já cai no Título — cria (Linear/Notion) ou edita, o campo mais
    // provável de ser o próximo passo é sempre esse.
    nextTick(() => titleInput.value?.focus())
}, { immediate: true })

const MAX_LABELS_PER_TASK = 2
const MAX_LABELS_PER_CLINIC = 10

function toggleLabel(id) {
    const i = form.label_ids.indexOf(id)
    if (i !== -1) { form.label_ids.splice(i, 1); return }

    if (form.label_ids.length >= MAX_LABELS_PER_TASK) {
        toast.error(`Uma tarefa pode possuir no máximo ${MAX_LABELS_PER_TASK} etiquetas.`)
        return
    }
    form.label_ids.push(id)
}

async function submit() {
    processing.value = true
    errors.value = {}

    try {
        const payload = {
            ...form,
            assigned_to: props.showPatientField ? null : (form.assigned_to || null),
            patient_id: props.showPatientField ? (patient.value?.id ?? null) : null,
            task_list_id: props.activeListId || null,
            due_date: form.due_date || null,
        }
        const { data } = props.task
            ? await window.axios.put(route('tasks.update', props.task.id), payload)
            : await window.axios.post(route('tasks.store'), payload)

        emit('saved', data)
    } catch (e) {
        // 401/419 (sessão/CSRF) já são tratados globalmente em bootstrap.js
        // (redireciona pro login). 422 mostra os erros inline nos campos.
        // Qualquer outra falha (500, rede) precisa de um aviso visível — sem
        // isso o modal só ficava parado e a tarefa parecia "não salvar",
        // sem nenhuma pista do porquê.
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {}
        } else {
            toast.error(props.task ? 'Não foi possível salvar a tarefa.' : 'Não foi possível criar a tarefa.')
        }
    } finally {
        processing.value = false
    }
}

// ── Criar etiqueta sem sair do formulário ──────────────────────────────────
const showNewLabel = ref(false)
const newLabelName = ref('')
const newLabelColor = ref('#0d9488')
const creatingLabel = ref(false)

async function createLabel() {
    const name = newLabelName.value.trim()
    if (!name) return

    creatingLabel.value = true
    try {
        const { data: label } = await window.axios.post(route('task-labels.store'), {
            name, color: newLabelColor.value,
        })
        labels.value.push(label)
        if (form.label_ids.length < MAX_LABELS_PER_TASK) {
            form.label_ids.push(label.id)
        } else {
            toast.error(`Etiqueta criada, mas não selecionada: uma tarefa pode possuir no máximo ${MAX_LABELS_PER_TASK} etiquetas.`)
        }
        emit('label-created', label)
        newLabelName.value = ''
        showNewLabel.value = false
    } catch (e) {
        if (e.response?.status === 409) {
            toast.error(e.response.data.message)
        }
    } finally {
        creatingLabel.value = false
    }
}

// ── Excluir etiqueta da clínica (não é só desmarcar da tarefa atual) ──────
function applyLabelRemoval(id) {
    labels.value = labels.value.filter((l) => l.id !== id)
    form.label_ids = form.label_ids.filter((lid) => lid !== id)
    emit('label-deleted', id)
}

async function removeLabel(label) {
    try {
        await window.axios.delete(route('task-labels.destroy', label.id))
        applyLabelRemoval(label.id)
    } catch (e) {
        if (e.response?.status !== 409) return

        const count = e.response.data.usage_count
        const confirmed = confirm(
            `Esta etiqueta está sendo utilizada por ${count} tarefa${count > 1 ? 's' : ''}.\n\n` +
            'Deseja realmente removê-la? Ela será removida também dessas tarefas.'
        )
        if (!confirmed) return

        await window.axios.delete(route('task-labels.destroy', label.id), { params: { force: true } })
        applyLabelRemoval(label.id)
    }
}
</script>

<template>
<Modal :show="show" :title="task ? 'Editar tarefa' : 'Nova tarefa'" max-width="max-w-lg" @close="$emit('close')">
    <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Título <span class="text-red-500">*</span></label>
            <input ref="titleInput" v-model="form.title" type="text" maxlength="40"
                   class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
            <div class="mt-1 flex items-center justify-between">
                <InputError :message="errors.title?.[0]" />
                <span class="ml-auto text-[10px]" :class="form.title.length >= 40 ? 'text-red-500' : 'text-slate-300'">
                    {{ form.title.length }}/40
                </span>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Descrição <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" rows="3" maxlength="3000"
                      class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            <div class="mt-1 flex items-center justify-between">
                <InputError :message="errors.description?.[0]" />
                <span class="ml-auto text-[10px]" :class="form.description.length >= 3000 ? 'text-red-500' : 'text-slate-300'">
                    {{ form.description.length }}/3000
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select v-model="form.status" class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option v-for="(l, key) in statusOptions" :key="key" :value="key">{{ l }}</option>
                </select>
                <InputError :message="errors.status?.[0]" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Prioridade <span class="text-red-500">*</span></label>
                <select v-model="form.priority" class="w-full rounded-lg border-slate-300 text-sm text-slate-800 transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="" disabled>—</option>
                    <option v-for="(l, key) in priorities" :key="key" :value="key" :disabled="key === 'urgente' && dueDateIsUpcoming()">
                        {{ l }}{{ key === 'urgente' && dueDateIsUpcoming() ? ' (indisponível em Próximas)' : '' }}
                    </option>
                </select>
                <InputError :message="errors.priority?.[0]" />
                <p v-if="dueDateIsUpcoming()" class="mt-1 text-[10px] text-slate-400">
                    Urgente fica indisponível pra vencimentos futuros (Próximas).
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div v-if="showPatientField">
                <label class="block text-xs font-medium text-slate-600 mb-1">Paciente</label>
                <PatientCombobox v-model="patient" />
                <InputError :message="errors.patient_id?.[0]" />
            </div>
            <div v-else>
                <label class="block text-xs font-medium text-slate-600 mb-1">Responsável</label>
                <select v-model="form.assigned_to" class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Sem responsável</option>
                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <InputError :message="errors.assigned_to?.[0]" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Vencimento</label>
                <input v-model="form.due_date" type="date" class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
                <InputError :message="errors.due_date?.[0]" />
            </div>
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label class="text-xs font-medium text-slate-600">Etiquetas</label>
                <span class="text-[10px] text-slate-300">{{ form.label_ids.length }}/{{ MAX_LABELS_PER_TASK }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <span v-for="l in labels" :key="l.id"
                      class="inline-flex items-center gap-1 rounded-full border py-1 pl-2.5 pr-1 text-xs transition-opacity"
                      :class="[
                          form.label_ids.includes(l.id) ? 'text-white border-transparent' : 'bg-white',
                          !form.label_ids.includes(l.id) && form.label_ids.length >= MAX_LABELS_PER_TASK ? 'opacity-40' : '',
                      ]"
                      :style="form.label_ids.includes(l.id) ? { backgroundColor: l.color } : { color: l.color, borderColor: l.color }">
                    <button type="button" @click="toggleLabel(l.id)" class="cursor-pointer">{{ l.name }}</button>
                    <button type="button" @click="removeLabel(l)" title="Excluir etiqueta"
                            class="rounded-full p-0.5 opacity-50 transition-opacity hover:opacity-100"
                            :class="form.label_ids.includes(l.id) ? 'hover:bg-white/20' : 'hover:bg-slate-100'">
                        <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>

                <button v-if="!showNewLabel && labels.length < MAX_LABELS_PER_CLINIC" type="button" @click="showNewLabel = true"
                        class="rounded-full border border-dashed px-2.5 py-1 text-xs text-slate-400 transition-colors hover:border-slate-400 hover:text-slate-600">
                    + Nova
                </button>
                <span v-else-if="!showNewLabel" title="Exclua uma etiqueta existente para criar outra."
                      class="cursor-not-allowed rounded-full border border-dashed px-2.5 py-1 text-xs text-slate-300 opacity-60">
                    Limite de etiquetas atingido
                </span>
            </div>

            <div v-if="showNewLabel" class="mt-2">
                <div class="flex items-center gap-1.5">
                    <input v-model="newLabelColor" type="color" class="h-7 w-8 rounded border-0" title="Cor" />
                    <input v-model="newLabelName" @keydown.enter.prevent="createLabel" @keyup.esc.stop="showNewLabel = false"
                           type="text" placeholder="Nome da etiqueta" maxlength="15" autofocus
                           class="flex-1 rounded-lg border-slate-300 px-2.5 py-1 text-xs transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
                    <button type="button" @click="createLabel" :disabled="creatingLabel"
                            class="rounded-lg border px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50">
                        Criar
                    </button>
                </div>
                <p class="mt-1 text-right text-[10px]" :class="newLabelName.length >= 15 ? 'text-red-500' : 'text-slate-300'">
                    {{ newLabelName.length }}/15
                </p>
            </div>
            <InputError :message="errors.label_ids?.[0]" />
        </div>
    </form>

    <template #footer>
        <div class="flex justify-end gap-2">
            <button type="button" @click="$emit('close')"
                    class="px-3 py-1.5 border rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="submit" :disabled="processing"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                {{ task ? 'Salvar' : 'Criar tarefa' }}
            </button>
        </div>
    </template>
</Modal>
</template>
