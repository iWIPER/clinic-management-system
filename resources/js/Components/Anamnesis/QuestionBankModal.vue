<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: Boolean,
    templateId: Number,
    types: Array,
    categories: Array,
    editQuestion: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const tab = ref('search')
const search = ref('')
const filterCategory = ref('')
const filterType = ref('')
const results = ref([])
const loading = ref(false)

const newForm = ref({
    text: '',
    description: '',
    category: 'GERAL',
    type: 'yes_no_unknown_text',
    has_alert: false,
    alert_text: '',
    show_on_patient_card: true,
    is_required: false,
    is_active: true,
})

const editForm = ref(null)
const createErrors = ref({})
const editErrors = ref({})

const extractErrors = (err) => {
    const serverErrors = err.response?.data?.errors ?? {}
    return Object.fromEntries(
        Object.entries(serverErrors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
    )
}

watch(() => props.show, (v) => {
    createErrors.value = {}
    editErrors.value = {}
    if (!v) {
        editForm.value = null
        tab.value = 'search'
        return
    }

    if (props.editQuestion) {
        openEdit(props.editQuestion)
    } else {
        tab.value = 'search'
        runSearch()
    }
})

const runSearch = async () => {
    loading.value = true
    try {
        const { data } = await axios.get(route('anamnesis-questions.index'), {
            params: { q: search.value, category: filterCategory.value, type: filterType.value },
        })
        results.value = data.questions
    } finally {
        loading.value = false
    }
}

const attach = (questionId) => {
    router.post(route('anamnesis-templates.questions.attach', props.templateId), {
        question_id: questionId,
    }, { preserveScroll: true, onSuccess: () => emit('close') })
}

const createAndAttach = async () => {
    createErrors.value = {}
    try {
        const { data } = await axios.post(route('anamnesis-questions.store'), newForm.value)
        attach(data.question.id)
    } catch (err) {
        createErrors.value = extractErrors(err)
    }
}

const saveEdit = async () => {
    editErrors.value = {}
    try {
        await axios.put(route('anamnesis-questions.update', editForm.value.id), editForm.value)
        await runSearch()
        editForm.value = null
        router.reload({ only: ['editor'] })
    } catch (err) {
        editErrors.value = extractErrors(err)
    }
}

const openEdit = (q) => {
    editForm.value = { ...q, has_alert: !!q.has_alert }
    tab.value = 'edit'
}

const duplicate = async (id) => {
    const { data } = await axios.post(route('anamnesis-questions.duplicate', id))
    attach(data.question.id)
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="emit('close')">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-xl bg-white shadow-xl flex flex-col">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h3 class="font-semibold text-slate-900">{{ editForm ? 'Editar pergunta' : 'Adicionar pergunta' }}</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <div class="flex gap-1 border-b px-4 pt-2">
                <button v-for="t in [{id:'search',l:'Banco'},{id:'create',l:'Nova'},{id:'edit',l:'Editar'}]" :key="t.id"
                        @click="tab = t.id"
                        class="px-3 py-2 text-xs font-medium border-b-2 -mb-px"
                        :class="tab === t.id ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500'">
                    {{ t.l }}
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <template v-if="tab === 'search'">
                    <div class="flex gap-2 mb-3">
                        <input v-model="search" @keyup.enter="runSearch" type="text" placeholder="Buscar pergunta…"
                               class="flex-1 rounded-md border px-3 py-2 text-sm" />
                        <select v-model="filterCategory" @change="runSearch" class="rounded-md border px-2 py-2 text-sm">
                            <option value="">Todas categorias</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <select v-model="filterType" @change="runSearch" class="rounded-md border px-2 py-2 text-sm">
                            <option value="">Todos tipos</option>
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <button @click="runSearch" class="rounded-md bg-slate-800 px-3 py-2 text-sm text-white">Buscar</button>
                    </div>
                    <div v-if="loading" class="text-sm text-slate-400 py-8 text-center">Buscando…</div>
                    <div v-else class="space-y-2">
                        <div v-for="q in results" :key="q.id" class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800 truncate">{{ q.text }}</p>
                                <p class="text-[10px] text-slate-400">{{ q.category }} · {{ q.type_label }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button @click="openEdit(q)" class="text-xs text-slate-500 px-2 py-1 border rounded">Editar</button>
                                <button @click="duplicate(q.id)" class="text-xs text-slate-500 px-2 py-1 border rounded">Duplicar</button>
                                <button @click="attach(q.id)" class="text-xs text-teal-700 px-2 py-1 border border-teal-200 rounded bg-teal-50">Adicionar</button>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="tab === 'create'">
                    <div class="space-y-3">
                        <input v-model="newForm.text" type="text" placeholder="Texto da pergunta" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none" />
                        <InputError :message="createErrors.text" />
                        <input v-model="newForm.description" type="text" placeholder="Descrição (opcional)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none" />
                        <InputError :message="createErrors.description" />
                        <select v-model="newForm.category" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none">
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            <option value="GERAL">GERAL</option>
                        </select>
                        <select v-model="newForm.type" class="w-full rounded-md border px-3 py-2 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm"><input v-model="newForm.has_alert" type="checkbox" /> Possui alerta</label>
                        <input v-if="newForm.has_alert" v-model="newForm.alert_text" type="text" placeholder="Texto do alerta" class="w-full rounded-md border px-3 py-2 text-sm" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="newForm.show_on_patient_card" type="checkbox" /> Visível na ficha</label>
                        <button @click="createAndAttach" class="rounded-md bg-teal-600 px-4 py-2 text-sm text-white">Criar e adicionar</button>
                    </div>
                </template>

                <template v-else-if="tab === 'edit' && editForm">
                    <div class="space-y-3">
                        <div>
                            <label class="text-[11px] text-slate-500 mb-1 block">Texto</label>
                            <input v-model="editForm.text" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none" />
                            <InputError :message="editErrors.text" />
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-500 mb-1 block">Descrição</label>
                            <input v-model="editForm.description" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none" />
                            <InputError :message="editErrors.description" />
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-500 mb-1 block">Categoria</label>
                            <select v-model="editForm.category" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none">
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-500 mb-1 block">Tipo</label>
                            <select v-model="editForm.type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/25 outline-none">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_required" type="checkbox" /> Obrigatória</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="editForm.has_alert" type="checkbox" /> Possui alerta</label>
                        <input v-if="editForm.has_alert" v-model="editForm.alert_text" type="text" class="w-full rounded-md border px-3 py-2 text-sm" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="editForm.show_on_patient_card" type="checkbox" /> Visível na ficha</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="editForm.is_active" type="checkbox" /> Ativa</label>
                        <button @click="saveEdit" class="rounded-md bg-teal-600 px-4 py-2 text-sm text-white">Salvar pergunta</button>
                    </div>
                </template>
                <p v-else-if="tab === 'edit'" class="text-sm text-slate-500">Selecione uma pergunta na aba Banco para editar.</p>
            </div>
        </div>
    </div>
</template>