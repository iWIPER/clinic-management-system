<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import MarkerColorPicker from '@/Components/Patient/MarkerColorPicker.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    markers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const systemMarkers = computed(() => props.markers.filter((m) => m.is_system))
const clinicMarkers = computed(() => props.markers.filter((m) => !m.is_system))

const pluralPatients = (count) => (count === 1 ? '1 paciente' : `${count} pacientes`)

// Sem cor pré-selecionada: obriga escolha consciente, evita marcador vermelho
// criado só porque o usuário esqueceu de trocar a cor.
const newMarkerForm = useForm({ name: '', color: '' })
const canCreate = computed(() => newMarkerForm.name.trim().length >= 2 && !!newMarkerForm.color)

const editingId = ref(null)
const editingName = ref('')
const editingColor = ref('')

// Evita reabrir o modal com um campo de edição ou o form de criação presos
// de uma sessão anterior.
watch(() => props.show, (visible) => {
    if (!visible) {
        editingId.value = null
        newMarkerForm.reset()
        newMarkerForm.clearErrors()
    }
})

const createMarker = () => {
    newMarkerForm.post(route('markers.store'), {
        preserveScroll: true,
        preserveState: true,
        only: ['availableMarkers'],
        onSuccess: () => newMarkerForm.reset(),
    })
}

const startEdit = (marker) => {
    editingId.value = marker.id
    editingName.value = marker.name
    editingColor.value = marker.color
}

const cancelEdit = () => {
    editingId.value = null
}

const saveEdit = (marker) => {
    const payload = { color: editingColor.value }
    if (!marker.is_system) payload.name = editingName.value

    router.put(route('markers.update', marker.id), payload, {
        preserveScroll: true,
        preserveState: true,
        only: ['availableMarkers', 'patientMarkers'],
        onSuccess: () => { editingId.value = null },
    })
}

const deleteMarker = (marker) => {
    const message = marker.patients_count > 0
        ? `Este marcador está sendo utilizado por ${pluralPatients(marker.patients_count)}. Deseja realmente excluir?`
        : `Excluir o marcador "${marker.name}"?`

    if (!confirm(message)) return

    router.delete(route('markers.destroy', marker.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['availableMarkers', 'patientMarkers'],
    })
}
</script>

<template>
    <Modal :show="show" title="Gerenciar Marcadores" max-width="max-w-md" @close="$emit('close')">
        <div class="p-5 space-y-7">
            <form @submit.prevent="createMarker" class="space-y-3">
                <p class="text-sm font-semibold text-slate-800">Novo marcador</p>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Nome *</label>
                    <input v-model="newMarkerForm.name" type="text" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="newMarkerForm.errors.name" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Cor *</label>
                    <MarkerColorPicker v-model="newMarkerForm.color" />
                    <InputError :message="newMarkerForm.errors.color" />
                </div>
                <button type="submit" :disabled="!canCreate || newMarkerForm.processing"
                        class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm text-white disabled:opacity-50">
                    Criar
                </button>
            </form>

            <div class="border-t border-slate-100 pt-5 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Marcadores do sistema</p>
                <p v-if="!systemMarkers.length" class="text-xs text-slate-400 py-2">Nenhum.</p>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="marker in systemMarkers" :key="marker.id" class="flex items-center gap-2 py-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: marker.color }"></span>
                        <span class="flex-1 min-w-0 truncate text-sm text-slate-700">{{ marker.name }}</span>
                        <span class="shrink-0 w-16 text-right text-[10px] text-slate-400">{{ pluralPatients(marker.patients_count) }}</span>

                        <template v-if="editingId === marker.id">
                            <MarkerColorPicker v-model="editingColor" />
                            <button type="button" @click="saveEdit(marker)" class="shrink-0 text-xs text-teal-600 hover:text-teal-700">Salvar</button>
                            <button type="button" @click="cancelEdit" class="shrink-0 text-xs text-slate-400 hover:text-slate-600">Cancelar</button>
                        </template>
                        <template v-else>
                            <span class="shrink-0 text-xs" :title="'Marcador do sistema.\nNão pode ser renomeado nem removido.'">🔒</span>
                            <button type="button" @click="startEdit(marker)" title="Alterar cor" class="shrink-0 text-xs text-slate-400 hover:text-slate-700">🎨</button>
                        </template>
                    </li>
                </ul>
            </div>

            <div class="border-t border-slate-100 pt-5 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Marcadores da clínica</p>
                <p v-if="!clinicMarkers.length" class="text-xs text-slate-400 py-2">Nenhum marcador próprio cadastrado ainda.</p>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="marker in clinicMarkers" :key="marker.id" class="flex items-center gap-2 py-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: marker.color }"></span>

                        <input v-if="editingId === marker.id" v-model="editingName" type="text" autofocus
                               class="flex-1 min-w-0 rounded border border-slate-200 px-2 py-1 text-sm"
                               @keyup.esc.stop="cancelEdit" />
                        <span v-else class="flex-1 min-w-0 truncate text-sm text-slate-700">{{ marker.name }}</span>

                        <span class="shrink-0 w-16 text-right text-[10px] text-slate-400">{{ pluralPatients(marker.patients_count) }}</span>

                        <template v-if="editingId === marker.id">
                            <MarkerColorPicker v-model="editingColor" />
                            <button type="button" @click="saveEdit(marker)" class="shrink-0 text-xs text-teal-600 hover:text-teal-700">Salvar</button>
                            <button type="button" @click="cancelEdit" class="shrink-0 text-xs text-slate-400 hover:text-slate-600">Cancelar</button>
                        </template>
                        <template v-else>
                            <button type="button" @click="startEdit(marker)" title="Editar" class="shrink-0 text-xs text-slate-400 hover:text-slate-700">✏ editar</button>
                            <button type="button" @click="deleteMarker(marker)" title="Excluir" class="shrink-0 text-xs text-red-400 hover:text-red-600">🗑 excluir</button>
                        </template>
                    </li>
                </ul>
            </div>
        </div>
    </Modal>
</template>
