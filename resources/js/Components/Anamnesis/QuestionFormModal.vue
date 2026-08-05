<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: Boolean,
    mode: { type: String, default: 'create' },
    question: { type: Object, default: null },
    templateId: Number,
    categories: Array,
    types: Array,
})

const emit = defineEmits(['close', 'saved'])

const form = ref(emptyForm())
const saving = ref(false)
const errors = ref({})

function emptyForm() {
    return {
        text: '',
        description: '',
        category: 'GERAL',
        type: 'yes_no_unknown_text',
        is_required: false,
        has_alert: false,
        alert_text: '',
        show_on_patient_card: true,
        is_active: true,
        pivot_is_required: false,
    }
}

watch(() => props.show, (v) => {
    if (!v) return
    if (props.mode === 'edit' && props.question) {
        form.value = {
            ...emptyForm(),
            ...props.question,
            has_alert: !!props.question.has_alert,
            pivot_is_required: !!props.question.pivot_is_required,
            category: props.question.category || 'GERAL',
        }
    } else {
        form.value = emptyForm()
    }
    errors.value = {}
})

const save = async () => {
    saving.value = true
    errors.value = {}
    try {
        if (props.mode === 'edit' && props.question?.id) {
            await axios.put(route('anamnesis-questions.update', props.question.id), form.value)
            if (props.templateId && form.value.pivot_is_required !== props.question.pivot_is_required) {
                await axios.post(route('anamnesis-templates.questions.attach', props.templateId), {
                    question_id: props.question.id,
                    is_required: form.value.pivot_is_required,
                })
            }
        } else {
            await axios.post(route('anamnesis-questions.store'), {
                ...form.value,
                template_id: props.templateId,
            })
        }
        emit('saved')
        emit('close')
        router.reload({ only: ['editor'] })
    } catch (err) {
        const serverErrors = err.response?.data?.errors ?? {}
        errors.value = Object.fromEntries(
            Object.entries(serverErrors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
        )
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="emit('close')">
        <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-[#E8EDF4] px-5 py-4">
                <h3 class="font-semibold text-slate-900">{{ mode === 'edit' ? 'Editar pergunta' : 'Nova pergunta' }}</h3>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <div class="p-5 space-y-3">
                <div>
                    <label class="text-[11px] font-medium text-slate-500 mb-1 block">Pergunta</label>
                    <input v-model="form.text" type="text" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 outline-none" />
                    <InputError :message="errors.text" />
                </div>
                <div>
                    <label class="text-[11px] font-medium text-slate-500 mb-1 block">Descrição (interna)</label>
                    <input v-model="form.description" type="text" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 outline-none" />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-medium text-slate-500 mb-1 block">Categoria</label>
                        <select v-model="form.category" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm focus:border-teal-500 outline-none">
                            <option v-for="c in categories" :key="c.id || c.name" :value="c.name">{{ c.name }}</option>
                        </select>
                        <InputError :message="errors.category" />
                    </div>
                    <div>
                        <label class="text-[11px] font-medium text-slate-500 mb-1 block">Tipo</label>
                        <select v-model="form.type" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm focus:border-teal-500 outline-none">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.pivot_is_required" type="checkbox" class="text-teal-600" /> Obrigatória no modelo
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.has_alert" type="checkbox" class="text-teal-600" /> Possui alerta clínico
                </label>
                <input v-if="form.has_alert" v-model="form.alert_text" type="text" placeholder="Texto do alerta (ex: Hipertenso)" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.show_on_patient_card" type="checkbox" class="text-teal-600" /> Mostrar na ficha
                </label>
                <label v-if="mode === 'edit'" class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="text-teal-600" /> Pergunta ativa
                </label>
            </div>

            <div class="border-t border-[#E8EDF4] px-5 py-4 flex justify-end gap-2">
                <button type="button" @click="emit('close')" class="rounded-xl border border-[#E8EDF4] px-4 py-2 text-sm">Cancelar</button>
                <button type="button" @click="save" :disabled="saving || !form.text" class="rounded-xl bg-teal-600 px-4 py-2 text-sm text-white hover:bg-teal-700 disabled:opacity-50">
                    {{ saving ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </div>
    </div>
</template>