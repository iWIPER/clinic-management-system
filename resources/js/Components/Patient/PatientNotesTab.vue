<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import InputError from '@/Components/InputError.vue'
import { NOTE_PRIORITIES, notePriorityStyle } from '@/lib/patientNotePriority.js'

const props = defineProps({
    patient:          Object,
    notes:            Array,
    availableMarkers: { type: Array, default: () => [] },
    notesPagination:  { type: Object, default: () => null },
})

const showForm = ref(false)
const editingId = ref(null)

const form = useForm({
    title: '',
    description: '',
    color: '#64748b',
    is_pinned: false,
    is_alert: false,
    priority: 'critico',
    tag_ids: [],
})

const resetForm = () => {
    form.reset()
    editingId.value = null
    showForm.value = false
}

const deleteNote = (note) => {
    if (confirm(`Remover a observação "${note.title}"?`)) {
        router.delete(route('patients.notes.destroy', [props.patient.id, note.id]), { preserveScroll: true })
    }
}

const submit = () => {
    if (editingId.value) {
        form.put(route('patients.notes.update', [props.patient.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: resetForm,
        })
    } else {
        form.post(route('patients.notes.store', props.patient.id), {
            preserveScroll: true,
            onSuccess: resetForm,
        })
    }
}

const editNote = (note) => {
    editingId.value = note.id
    showForm.value = true
    form.title = note.title
    form.description = note.description || ''
    form.color = note.color
    form.is_pinned = note.is_pinned
    form.is_alert = note.is_alert
    form.priority = note.priority || 'critico'
    form.tag_ids = note.tags?.map(t => t.id) || []
}

const toggleTag = (id) => {
    const idx = form.tag_ids.indexOf(id)
    if (idx >= 0) form.tag_ids.splice(idx, 1)
    else form.tag_ids.push(id)
}

const changePage = (page) => {
    router.visit(route('patients.show', props.patient.id), {
        data:           { notes_page: page },
        only:           ['patientNotes', 'notesPagination'],
        preserveState:  true,
        preserveScroll: true,
    })
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Observações</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ notesPagination?.total ?? notes?.length ?? 0 }} registro(s)</p>
            </div>
            <button @click="showForm = !showForm; if (!showForm) resetForm()"
                    class="rounded-lg border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ showForm ? 'Cancelar' : 'Nova observação' }}
            </button>
        </div>

        <form v-if="showForm" @submit.prevent="submit" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
            <input v-model="form.title" type="text" placeholder="Título" required
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <InputError :message="form.errors.title" />
            <textarea v-model="form.description" rows="3" placeholder="Descrição"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
            <InputError :message="form.errors.description" />
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <label class="flex items-center gap-1.5"><input v-model="form.is_pinned" type="checkbox" /> Fixada</label>
                <input v-model="form.color" type="color" class="h-8 w-12 rounded border-0" title="Cor" />
            </div>

            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs cursor-pointer transition-colors w-fit"
                   :class="form.is_alert ? 'border-red-300 bg-red-50 text-red-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                <input v-model="form.is_alert" type="checkbox" class="accent-red-600" />
                <span class="font-semibold">⚠ Alerta importante</span>
            </label>
            <div v-if="form.is_alert" class="flex flex-wrap gap-1.5">
                <button v-for="(meta, key) in NOTE_PRIORITIES" :key="key" type="button"
                        @click="form.priority = key"
                        class="rounded-full px-2.5 py-1 text-xs border transition-colors"
                        :class="form.priority === key ? meta.pillActiveClass : 'bg-white text-slate-500 border-slate-200'">
                    {{ meta.emoji }} {{ meta.label }}
                </button>
            </div>

            <hr v-if="availableMarkers?.length" class="border-slate-200" />

            <div v-if="availableMarkers?.length">
                <p class="text-xs font-medium text-slate-500 mb-1.5">Marcadores</p>
                <div class="flex flex-wrap gap-1.5">
                    <button v-for="marker in availableMarkers" :key="marker.id" type="button"
                            @click="toggleTag(marker.id)"
                            class="rounded-full px-2.5 py-1 text-xs border transition-colors"
                            :class="form.tag_ids.includes(marker.id) ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200'"
                            :style="form.tag_ids.includes(marker.id) ? {} : { borderColor: marker.color, color: marker.color }">
                        {{ marker.name }}
                    </button>
                </div>
            </div>
            <button type="submit" :disabled="form.processing"
                    class="rounded-lg bg-teal-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                {{ editingId ? 'Salvar' : 'Adicionar' }}
            </button>
        </form>

        <div v-if="!notes?.length" class="rounded-xl border border-dashed border-slate-200 py-16 text-center text-sm text-slate-500">
            Nenhuma observação registrada.
        </div>

        <div v-else class="space-y-3">
            <article v-for="note in notes" :key="note.id"
                     class="rounded-xl border p-4"
                     :class="note.is_alert ? notePriorityStyle(note.priority).cardClass : 'border-slate-200 bg-white'"
                     :style="{ borderLeftWidth: '4px', borderLeftColor: note.color }">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-medium text-slate-900">{{ note.title }}</h4>
                            <span v-if="note.is_pinned" class="text-[10px] text-slate-400">📌</span>
                        </div>
                        <p v-if="note.description" class="text-sm text-slate-600 mt-1 whitespace-pre-wrap">{{ note.description }}</p>
                        <p class="text-xs text-slate-400 mt-2">{{ note.author }} · {{ note.date }} {{ note.time }}</p>
                        <div v-if="note.tags?.length" class="flex flex-wrap gap-1 mt-2">
                            <span v-for="tag in note.tags" :key="tag.id"
                                  class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                  :style="{ backgroundColor: tag.color + '20', color: tag.color }">
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <button @click="editNote(note)" class="text-xs text-slate-500 hover:text-slate-700">Editar</button>
                        <button @click="deleteNote(note)"
                                class="text-xs text-red-500 hover:text-red-700">Excluir</button>
                    </div>
                </div>
            </article>
        </div>

        <Pagination v-if="notesPagination"
                    :pagination="notesPagination"
                    @change="changePage" />
    </div>
</template>