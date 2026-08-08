<script setup>
import { computed, reactive, ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    show: { type: Boolean, default: false },
    // null = criando um escopo personalizado novo. { key, name, color,
    // sharing_type, shared_user_ids } (fixo) ou { id, ...os mesmos campos,
    // task_count } (personalizado existente).
    list: { type: Object, default: null },
    teamMembers: { type: Array, default: () => [] },
    // "Minhas tarefas"/"Tarefas da equipe" — nome, compartilhamento e
    // exclusão ficam travados por regra de negócio; só a cor é editável.
    isFixed: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved', 'deleted'])

const SHARING_OPTIONS = [
    { value: 'private', label: 'Só eu' },
    { value: 'team', label: 'Toda equipe' },
    { value: 'selected', label: 'Selecionar profissionais' },
]
const SHARING_LABELS = Object.fromEntries(SHARING_OPTIONS.map((o) => [o.value, o.label]))

const isCreating = computed(() => !props.isFixed && !props.list?.id)
const title = computed(() => isCreating.value ? 'Nova lista' : 'Configurar lista')

const form = reactive({ name: '', color: '#3b82f6', sharing_type: 'private', shared_user_ids: [] })
const errors = ref({})
const processing = ref(false)

watch(() => props.show, (open) => {
    if (!open) return
    Object.assign(form, {
        name: props.list?.name ?? '',
        color: props.list?.color ?? '#3b82f6',
        sharing_type: props.list?.sharing_type ?? 'private',
        shared_user_ids: [...(props.list?.shared_user_ids || [])],
    })
    errors.value = {}
}, { immediate: true })

function toggleMember(id) {
    const i = form.shared_user_ids.indexOf(id)
    if (i === -1) form.shared_user_ids.push(id)
    else form.shared_user_ids.splice(i, 1)
}

async function submit() {
    processing.value = true
    errors.value = {}
    try {
        let data
        if (props.isFixed) {
            ({ data } = await window.axios.put(route('task-lists.update', props.list.key), { color: form.color }))
        } else if (props.list?.id) {
            ({ data } = await window.axios.put(route('task-lists.update-custom', props.list.id), form))
        } else {
            ({ data } = await window.axios.post(route('task-lists.store'), form))
        }
        emit('saved', data)
        toast.success(isCreating.value ? 'Escopo criado.' : 'Lista atualizada.')
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {}
        } else if (e.response?.status === 409) {
            toast.error(e.response.data.message)
        } else {
            toast.error('Não foi possível salvar o escopo.')
        }
    } finally {
        processing.value = false
    }
}

async function destroyList() {
    if (!props.list?.id) return

    const count = props.list.task_count ?? 0
    const message = count > 0
        ? `Excluir "${props.list.name}"? As ${count} tarefa${count > 1 ? 's' : ''} nela serão movidas automaticamente para "Minhas tarefas". Nada será perdido.`
        : `Excluir "${props.list.name}"?`
    if (!confirm(message)) return

    processing.value = true
    try {
        await window.axios.delete(route('task-lists.destroy', props.list.id))
        emit('deleted', props.list.id)
        toast.success('Escopo excluído.')
    } catch {
        toast.error('Não foi possível excluir o escopo.')
    } finally {
        processing.value = false
    }
}
</script>

<template>
<Modal :show="show" :title="title" max-width="max-w-md" @close="$emit('close')">
    <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
                Nome <span v-if="!isFixed" class="text-red-500">*</span>
            </label>
            <input v-if="!isFixed" v-model="form.name" type="text" maxlength="30"
                   class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
            <p v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                {{ list?.name }}
                <span class="ml-1 text-[10px] text-slate-400">(escopo fixo, não pode ser renomeado)</span>
            </p>
            <InputError :message="errors.name?.[0]" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Cor</label>
            <div class="flex items-center gap-2">
                <input v-model="form.color" type="color" class="h-8 w-10 rounded border-0" />
                <span class="text-xs text-slate-400">{{ form.color }}</span>
            </div>
            <InputError :message="errors.color?.[0]" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Compartilhar</label>

            <div v-if="!isFixed" class="space-y-1.5">
                <label v-for="opt in SHARING_OPTIONS" :key="opt.value"
                       class="flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-sm cursor-pointer transition-colors"
                       :class="form.sharing_type === opt.value ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50'">
                    <input v-model="form.sharing_type" type="radio" :value="opt.value" class="text-emerald-600 focus:ring-emerald-500" />
                    {{ opt.label }}
                </label>
            </div>
            <p v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                {{ SHARING_LABELS[list?.sharing_type] ?? list?.sharing_type }}
                <span class="ml-1 text-[10px] text-slate-400">(fixo)</span>
            </p>

            <div v-if="!isFixed && form.sharing_type === 'selected'" class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                <label v-for="m in teamMembers" :key="m.id"
                       class="flex items-center gap-2 rounded-md px-1.5 py-1 text-sm hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" :checked="form.shared_user_ids.includes(m.id)" @change="toggleMember(m.id)"
                           class="rounded text-emerald-600 focus:ring-emerald-500" />
                    {{ m.name }}
                </label>
                <p v-if="teamMembers.length === 0" class="px-1.5 py-1 text-xs text-slate-400">Nenhum outro profissional na clínica.</p>
            </div>
            <InputError :message="errors.sharing_type?.[0]" />
        </div>
    </form>

    <template #footer>
        <div class="flex items-center justify-between gap-2">
            <button v-if="!isFixed && list?.id" type="button" @click="destroyList" :disabled="processing"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50">
                Excluir lista
            </button>
            <div class="ml-auto flex gap-2">
                <button type="button" @click="$emit('close')"
                        class="px-3 py-1.5 border rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" @click="submit" :disabled="processing"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                    Salvar
                </button>
            </div>
        </div>
    </template>
</Modal>
</template>
