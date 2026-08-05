<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AnamnesisBuilderQuestionCard from '@/Components/Anamnesis/AnamnesisBuilderQuestionCard.vue'
import QuestionFormModal from '@/Components/Anamnesis/QuestionFormModal.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    editor: Object,
    bankCategories: Array,
    types: Array,
})

const showModal = ref(false)
const modalMode = ref('create')
const editingQuestion = ref(null)
const expanded = ref({})

const form = useForm({
    name: props.editor?.template?.name || '',
    description: props.editor?.template?.description || '',
    is_active: props.editor?.template?.is_active ?? true,
    question_order: (props.editor?.questions || []).map(q => q.id),
})

if (props.editor?.category_groups) {
    props.editor.category_groups.forEach((g, i) => {
        expanded.value[g.name] = i === 0
    })
}

const saveMeta = () => {
    form.question_order = (props.editor?.questions || []).map(q => q.id)
    form.put(route('anamnesis-templates.update', props.editor.template.id), { preserveScroll: true })
}

const openCreate = () => {
    editingQuestion.value = null
    modalMode.value = 'create'
    showModal.value = true
}

const openEdit = (q) => {
    editingQuestion.value = q
    modalMode.value = 'edit'
    showModal.value = true
}

const toggleCategory = (name) => {
    expanded.value[name] = !expanded.value[name]
}

const toggleActive = async (q) => {
    await axios.post(route('anamnesis-questions.toggle-active', q.id))
    router.reload({ only: ['editor'] })
}

const moveQuestion = (q, direction) => {
    router.post(route('anamnesis-templates.questions.move', [props.editor.template.id, q.id]), {
        direction,
    }, { preserveScroll: true })
}

const detach = (questionId) => {
    if (!confirm('Remover esta pergunta do modelo?')) return
    router.delete(route('anamnesis-templates.questions.detach', [props.editor.template.id, questionId]), {
        preserveScroll: true,
    })
}

const setDefault = () => {
    router.post(route('anamnesis-templates.set-default', props.editor.template.id), {}, { preserveScroll: true })
}

const title = (name) => name.toLowerCase().replace(/\b\w/g, c => c.toUpperCase())
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto pb-10">
            <div class="flex items-center justify-between mb-4">
                <Link :href="route('anamnesis-templates.index')" class="text-xs text-slate-500 hover:text-slate-700">← Modelos</Link>
                <Link :href="route('anamnesis-categories.index')" class="text-xs text-teal-600 hover:text-teal-700">Gerenciar categorias</Link>
            </div>

            <template v-if="editor">
                <div class="rounded-2xl border border-[#E8EDF4] bg-white shadow-sm p-5 mb-5">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <input v-model="form.name" type="text" placeholder="Nome do modelo"
                                   class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm font-semibold focus:border-teal-500 outline-none" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <label class="flex items-center gap-2"><input v-model="form.is_active" type="checkbox" /> Ativo</label>
                            <span v-if="editor.template.is_default" class="text-[10px] rounded-full bg-teal-100 text-teal-700 px-2 py-0.5">Padrão</span>
                            <button v-else type="button" @click="setDefault" class="text-[11px] text-teal-600 hover:underline">Definir padrão</button>
                        </div>
                        <div class="sm:col-span-2">
                            <textarea v-model="form.description" rows="2" placeholder="Descrição"
                                      class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm focus:border-teal-500 outline-none" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Construtor do modelo</h2>
                        <p class="text-[11px] text-slate-500">{{ editor.questions?.length || 0 }} perguntas · {{ editor.category_groups?.length || 0 }} categorias</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="openCreate" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                            + Nova pergunta
                        </button>
                        <button type="button" @click="saveMeta" :disabled="form.processing" class="rounded-xl border border-[#E8EDF4] px-4 py-2 text-sm hover:bg-slate-50">
                            Salvar modelo
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <section
                        v-for="group in editor.category_groups"
                        :key="group.name"
                        class="rounded-2xl border border-[#E8EDF4] bg-slate-50/40 shadow-sm overflow-hidden"
                    >
                        <button
                            type="button"
                            @click="toggleCategory(group.name)"
                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/60 transition-colors"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="text-base" :style="{ color: group.icon_color }">{{ group.icon }}</span>
                                <div class="text-left">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-700">{{ title(group.name) }}</p>
                                    <p class="text-[10px] text-slate-400">{{ group.questions.length }} pergunta(s)</p>
                                </div>
                            </div>
                            <span class="text-slate-400 text-sm">{{ expanded[group.name] ? '▼' : '▶' }}</span>
                        </button>

                        <div v-show="expanded[group.name]" class="px-4 pb-4 space-y-2.5 border-t border-[#E8EDF4] pt-3">
                            <AnamnesisBuilderQuestionCard
                                v-for="(q, idx) in group.questions"
                                :key="q.id"
                                :question="q"
                                :template-id="editor.template.id"
                                :is-first="idx === 0"
                                :is-last="idx === group.questions.length - 1"
                                @edit="openEdit"
                                @toggle-active="toggleActive"
                                @move="moveQuestion"
                                @detach="detach"
                            />
                            <p v-if="!group.questions.length" class="text-xs text-slate-400 text-center py-4">Nenhuma pergunta nesta categoria</p>
                        </div>
                    </section>
                </div>

                <QuestionFormModal
                    :show="showModal"
                    :mode="modalMode"
                    :question="editingQuestion"
                    :template-id="editor.template.id"
                    :categories="bankCategories"
                    :types="types"
                    @close="showModal = false"
                    @saved="showModal = false"
                />
            </template>

            <p v-else class="mt-6 text-sm text-slate-500">Salve o modelo para abrir o construtor.</p>
        </div>
    </AppLayout>
</template>