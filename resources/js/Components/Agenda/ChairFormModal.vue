<script setup>
import { computed, reactive, ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    show: { type: Boolean, default: false },
    // null = criando uma cadeira nova. { id, name, color } = editando uma existente.
    chair: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved', 'deleted'])

const isCreating = computed(() => !props.chair?.id)
const title = computed(() => isCreating.value ? 'Nova cadeira' : 'Editar cadeira')

const form = reactive({ name: '', color: '#0d9488' })
const errors = ref({})
const processing = ref(false)

watch(() => props.show, (open) => {
    if (!open) return
    Object.assign(form, {
        name: props.chair?.name ?? '',
        color: props.chair?.color ?? '#0d9488',
    })
    errors.value = {}
}, { immediate: true })

async function submit() {
    processing.value = true
    errors.value = {}
    try {
        let data
        if (props.chair?.id) {
            ({ data } = await window.axios.put(route('chairs.update', props.chair.id), form))
        } else {
            ({ data } = await window.axios.post(route('chairs.store'), form))
        }
        emit('saved', data)
        toast.success(isCreating.value ? 'Cadeira criada.' : 'Cadeira atualizada.')
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {}
        } else {
            toast.error('Não foi possível salvar a cadeira.')
        }
    } finally {
        processing.value = false
    }
}

async function destroyChair() {
    if (!props.chair?.id) return

    processing.value = true
    try {
        await window.axios.delete(route('chairs.destroy', props.chair.id))
        emit('deleted', props.chair.id)
        toast.success('Cadeira excluída.')
    } catch (e) {
        if (e.response?.status === 409) {
            // Cadeira em uso — avisa quantos agendamentos seriam
            // desvinculados (não excluídos) e pede confirmação explícita
            // antes de refazer a chamada com force=true.
            const count = e.response.data.usage_count ?? 0
            const message = `"${props.chair.name}" tem ${count} agendamento${count > 1 ? 's' : ''} vinculado${count > 1 ? 's' : ''}.\n\n` +
                'Eles NÃO serão excluídos — só ficarão sem cadeira. Deseja continuar?'
            if (!confirm(message)) { processing.value = false; return }

            try {
                await window.axios.delete(route('chairs.destroy', props.chair.id), { params: { force: true } })
                emit('deleted', props.chair.id)
                toast.success('Cadeira excluída.')
            } catch {
                toast.error('Não foi possível excluir a cadeira.')
            }
        } else {
            toast.error('Não foi possível excluir a cadeira.')
        }
    } finally {
        processing.value = false
    }
}
</script>

<template>
<Modal :show="show" :title="title" max-width="max-w-sm" @close="$emit('close')">
    <form @submit.prevent="submit" class="p-5 space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" maxlength="30" placeholder="Ex: Cadeira 03"
                   class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
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
    </form>

    <template #footer>
        <div class="flex items-center justify-between gap-2">
            <button v-if="chair?.id" type="button" @click="destroyChair" :disabled="processing"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50">
                Excluir cadeira
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
