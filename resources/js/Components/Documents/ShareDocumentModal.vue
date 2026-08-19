<script setup>
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    patientId: { type: [Number, String], required: true },
    documentId: { type: [Number, String], required: true },
    defaultEmail: { type: String, default: '' },
    defaultName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'shared'])

const form = useForm({
    recipient_email: props.defaultEmail || '',
    recipient_name: props.defaultName || '',
})

const submit = () => {
    form.post(route('patients.documents.share', [props.patientId, props.documentId]), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            emit('shared')
            emit('close')
        },
    })
}
</script>

<template>
    <Modal :show="show" title="Compartilhar documento" @close="$emit('close')">
        <div class="p-5 space-y-4">
            <p class="text-xs text-slate-500 leading-relaxed">
                O paciente recebe um e-mail com o documento (protegido por senha) e um link para conferir a senha
                após confirmar nome e CPF.
            </p>

            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">E-mail do destinatário</label>
                <input
                    v-model="form.recipient_email"
                    type="email"
                    required
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                    placeholder="paciente@exemplo.com"
                />
                <InputError :message="form.errors.recipient_email" />
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Nome (opcional, usado só na mensagem)</label>
                <input
                    v-model="form.recipient_name"
                    type="text"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                    placeholder="Nome do destinatário"
                />
                <InputError :message="form.errors.recipient_name" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="$emit('close')" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg">
                    Cancelar
                </button>
                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg disabled:opacity-50"
                >
                    Enviar
                </button>
            </div>
        </div>
    </Modal>
</template>
