<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'

// Confirmação antes de remover um atalho personalizado — reaproveita o
// mesmo endpoint do modal de personalização (PATCH /profile/quick-actions),
// só reenviando a lista atual sem a chave removida. Nenhum endpoint novo.
const props = defineProps({
    show: { type: Boolean, default: false },
    action: { type: Object, default: null }, // { key, label, icon, route }
    currentActions: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])
const removing = ref(false)

function confirm() {
    if (!props.action) return
    removing.value = true
    router.patch(route('profile.quick-actions.update'), {
        quick_actions: props.currentActions.filter((key) => key !== props.action.key),
    }, {
        preserveScroll: true,
        onFinish: () => { removing.value = false },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Modal :show="show" title="Remover atalho?" max-width="max-w-sm" @close="emit('close')">
        <div class="px-5 py-4">
            <p class="text-sm text-slate-600">
                Tem certeza de que deseja remover
                <span class="font-medium text-slate-900">"{{ action?.label }}"</span>
                dos seus atalhos?
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="removing"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-60 transition-colors"
                    @click="confirm"
                >
                    Remover
                </button>
            </div>
        </template>
    </Modal>
</template>
