<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import { CUSTOMIZABLE_QUICK_ACTIONS, MAX_CUSTOM_QUICK_ACTIONS } from '@/Navigation/quickActions'

const props = defineProps({
    show: { type: Boolean, default: false },
    currentActions: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const selected = ref([...props.currentActions])
const saving = ref(false)

// Reabrir o modal sempre parte do estado persistido atual — não do que
// ficou selecionado (sem salvar) na última vez que foi aberto.
watch(() => props.show, (visible) => {
    if (visible) selected.value = [...props.currentActions]
})

const limitReached = computed(() => selected.value.length >= MAX_CUSTOM_QUICK_ACTIONS)

function toggle(key) {
    if (selected.value.includes(key)) {
        selected.value = selected.value.filter((k) => k !== key)
    } else if (!limitReached.value) {
        selected.value = [...selected.value, key]
    }
}

function save() {
    saving.value = true
    router.patch(route('profile.quick-actions.update'), { quick_actions: selected.value }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Modal :show="show" title="Personalizar atalhos rápidos" max-width="max-w-md" @close="emit('close')">
        <div class="px-5 py-4">
            <p class="text-sm text-slate-500">Escolha até 2 atalhos para deixar sempre à mão.</p>

            <div class="mt-4 flex flex-col gap-1.5">
                <button
                    v-for="action in CUSTOMIZABLE_QUICK_ACTIONS"
                    :key="action.key"
                    type="button"
                    :disabled="!selected.includes(action.key) && limitReached"
                    class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm font-medium transition-colors duration-[180ms] ease disabled:cursor-not-allowed disabled:opacity-40"
                    :class="selected.includes(action.key)
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                    @click="toggle(action.key)"
                >
                    <component :is="action.icon" class="h-[18px] w-[18px] shrink-0" stroke-width="2"
                               :class="selected.includes(action.key) ? 'text-emerald-700' : 'text-slate-400'" />
                    <span class="flex-1 truncate">{{ action.label }}</span>
                    <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                        :class="selected.includes(action.key) ? 'border-emerald-600 bg-emerald-600' : 'border-slate-300'"
                    >
                        <svg v-if="selected.includes(action.key)" class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                </button>
            </div>

            <p v-if="limitReached" class="mt-3 text-xs text-amber-700">
                Você já possui o limite de 2 atalhos personalizados.
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100" @click="emit('close')">
                    Cancelar
                </button>
                <button type="button" :disabled="saving" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60" @click="save">
                    Salvar
                </button>
            </div>
        </template>
    </Modal>
</template>
