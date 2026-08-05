<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import DocumentRichEditor from '@/Components/Documents/DocumentRichEditor.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    treatment: { type: Object, default: null },
    professionals: { type: Array, default: () => [] },
    hasCatalogTreatment: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const blank = () => ({
    professional_id: '',
    completed_at: new Date().toISOString().slice(0, 10),
    evolution: '',
    update_stock: false,
})

const form = ref(blank())
const errors = ref({})
const saving = ref(false)

watch(() => props.show, (visible) => {
    if (!visible) return
    // Sempre em branco — não herda o profissional do tratamento (normalmente
    // quem criou), senão dá pra finalizar sem escolher de propósito quem
    // concluiu (ver ToothHistoryModal/TreatmentHistoryModal, que mostram o
    // profissional selecionado aqui como o autor do evento "Concluído").
    form.value = blank()
    errors.value = {}
})

const save = () => {
    if (!form.value.professional_id) {
        errors.value = { professional_id: 'Selecione o profissional que concluiu o tratamento.' }
        return
    }
    saving.value = true
    errors.value = {}
    router.post(route('patients.treatments.finalize', [props.patientId, props.treatment.id]), form.value, {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => { saving.value = false; emit('close') },
        onError: (e) => { saving.value = false; errors.value = e },
    })
}
</script>

<template>
<Modal :show="show" max-width="max-w-2xl" title="Finalizar tratamento" @close="emit('close')">
    <div class="p-5 space-y-4">
        <p class="text-xs text-slate-500">{{ treatment?.procedure_name }} · {{ treatment?.budget_code }}</p>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Profissional</label>
                <select v-model="form.professional_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white">
                    <option value="">Selecione...</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <InputError :message="errors.professional_id" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Data de conclusão</label>
                <input v-model="form.completed_at" type="date"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.completed_at" />
            </div>
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Evolução clínica</label>
            <DocumentRichEditor v-model="form.evolution" />
            <InputError :message="errors.evolution" />
        </div>

        <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer" :class="{ 'opacity-40 cursor-not-allowed': !hasCatalogTreatment }">
            <input v-model="form.update_stock" type="checkbox" :disabled="!hasCatalogTreatment"
                   class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-400" />
            Atualizar estoque (consumir materiais vinculados a este procedimento do catálogo)
        </label>
    </div>

    <template #footer>
        <div class="flex gap-2">
            <button type="button" @click="emit('close')"
                    class="flex-1 border border-slate-200 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="save" :disabled="saving"
                    class="flex-1 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                {{ saving ? 'Concluindo...' : 'Finalizar tratamento' }}
            </button>
        </div>
    </template>
</Modal>
</template>
