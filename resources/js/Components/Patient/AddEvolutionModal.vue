<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import DocumentRichEditor from '@/Components/Documents/DocumentRichEditor.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    professionals: { type: Array, default: () => [] },
    isDriveConnected: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved'])

const blank = () => ({
    professional_id: '',
    recorded_at: new Date().toISOString().slice(0, 10),
    content: '',
    signature_required: false,
    photos: [],
})

const form = useForm(blank())
const fileInputEl = ref(null)

watch(() => props.show, (visible) => {
    if (!visible) return
    form.reset()
    form.clearErrors()
    Object.assign(form, blank())
    if (fileInputEl.value) fileInputEl.value.value = ''
})

const MAX_PHOTOS = 5

function onFilesSelected(e) {
    const newFiles = Array.from(e.target.files ?? [])
    form.photos = [...form.photos, ...newFiles].slice(0, MAX_PHOTOS)
    e.target.value = ''
}

function removePhotoAt(index) {
    form.photos = form.photos.filter((_, i) => i !== index)
    if (fileInputEl.value) fileInputEl.value.value = ''
}

function submit() {
    form.post(route('patients.evolutions.store', props.patientId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    })
}
</script>

<template>
<Modal :show="show" :keep-mounted="true" max-width="max-w-2xl" title="Adicionar evolução" @close="emit('close')">
    <div class="p-5 space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Profissional <span class="text-red-500">*</span></label>
                <select v-model="form.professional_id"
                        class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white"
                        :class="form.errors.professional_id ? 'border-red-400' : 'border-slate-200'">
                    <option value="">Selecione...</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <InputError :message="form.errors.professional_id" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Data <span class="text-red-500">*</span></label>
                <input v-model="form.recorded_at" type="date"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="form.errors.recorded_at" />
            </div>
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Evolução clínica <span class="text-red-500">*</span></label>
            <DocumentRichEditor v-model="form.content" min-height="220px" />
            <InputError :message="form.errors.content" />
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Fotos</label>
            <p v-if="isDriveConnected" class="text-[11px] text-slate-400 mb-1.5">
                Selecione uma ou várias fotos de uma vez — dá pra repetir a seleção depois para adicionar mais (máximo {{ MAX_PHOTOS }} por evolução).
            </p>
            <input v-if="isDriveConnected && form.photos.length < MAX_PHOTOS" ref="fileInputEl" type="file" multiple accept="image/*"
                   @change="onFilesSelected"
                   class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-teal-50 file:text-teal-700 file:text-xs file:font-semibold" />
            <p v-else-if="isDriveConnected" class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                Limite de {{ MAX_PHOTOS }} fotos atingido — remova alguma para adicionar outra.
            </p>
            <p v-else class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                Conecte o Google Drive da clínica para anexar fotos a esta evolução.
            </p>
            <ul v-if="form.photos.length" class="mt-2 space-y-1">
                <li v-for="(file, i) in form.photos" :key="i"
                    class="flex items-center justify-between gap-2 text-xs text-slate-600 bg-slate-50 rounded-lg px-2.5 py-1.5">
                    <span class="truncate">{{ file.name }}</span>
                    <button type="button" @click="removePhotoAt(i)" class="text-slate-400 hover:text-red-600 shrink-0">✕</button>
                </li>
            </ul>
            <InputError :message="form.errors.photos" />
        </div>

        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3.5 py-2.5 cursor-pointer">
            <span>
                <span class="text-sm text-slate-700 font-medium block">Exigir assinatura para este documento</span>
                <span class="text-xs text-slate-400">O paciente (ou responsável) recebe um link para assinar remotamente.</span>
            </span>
            <button type="button" role="switch" :aria-checked="form.signature_required"
                    @click="form.signature_required = !form.signature_required"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0"
                    :class="form.signature_required ? 'bg-teal-600' : 'bg-slate-300'">
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                      :class="form.signature_required ? 'translate-x-[19px]' : 'translate-x-1'" />
            </button>
        </label>
    </div>

    <template #footer>
        <div class="flex gap-2">
            <button type="button" @click="emit('close')"
                    class="flex-1 border border-slate-200 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="submit" :disabled="form.processing"
                    class="flex-1 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                {{ form.processing ? 'Salvando...' : 'Adicionar evolução' }}
            </button>
        </div>
    </template>
</Modal>
</template>
