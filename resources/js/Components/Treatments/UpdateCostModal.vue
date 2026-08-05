<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    treatment: { type: Object, default: null },
    // Modo criação (tratamento ainda não existe no banco — sem id pra chamar
    // o endpoint de custo): salva só em memória, devolve os valores via
    // `close` pro formulário principal gravar quando o tratamento inteiro for
    // submetido. "Salvar como padrão" exige uma chamada ao servidor, então
    // fica escondido aqui.
    localOnly: { type: Boolean, default: false },
    // Nome do procedimento já escolhido no formulário principal — usado só
    // como texto de contexto quando ainda não existe `treatment` (criação).
    procedureName: { type: String, default: '' },
    // Valor atual do formulário principal (modo criação, sem `treatment`
    // ainda) — pré-preenche o campo Valor aqui pra ficar em sincronia. Sem
    // isso, abrir "Alterar" sem tocar no Valor mandava esse campo de volta
    // vazio no fechamento, apagando o preço já preenchido pelo procedimento
    // (Valor não deve ser afetado por essa tela, só o Custo).
    currentValueCharged: { type: [Number, String], default: '' },
    // Procedimento do catálogo selecionado no formulário principal (modo
    // criação) — precisa disso pra "salvar como padrão" gravar em algum
    // lugar antes de existir um PatientTreatment (ver treatments.default-cost).
    treatmentId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close'])

const form = ref({ value_charged: '', cost: '', save_as_default: false })
const errors = ref({})
const saving = ref(false)

// Id do procedimento do catálogo, seja editando (vem de `treatment`) ou
// criando (vem de `treatmentId`, lido do form principal) — só existe se um
// procedimento do catálogo foi selecionado (não texto livre), e é o que
// permite mostrar o checkbox "salvar como padrão" nos dois modos.
const catalogTreatmentId = computed(() => props.treatment?.treatment_id ?? props.treatmentId ?? null)

watch(() => props.show, (visible) => {
    if (!visible) return
    form.value = {
        value_charged: props.treatment?.value_charged ?? props.currentValueCharged ?? '',
        cost: props.treatment?.cost ?? '',
        save_as_default: false,
    }
    errors.value = {}
})

const validate = () => {
    if (Number(form.value.cost) > Number(form.value.value_charged)) {
        errors.value = { cost: 'O custo não pode ser maior que o valor cobrado.' }
        return false
    }
    return true
}

const save = () => {
    errors.value = {}
    if (!validate()) return

    if (props.localOnly) {
        // Ainda sem PatientTreatment salvo — não há onde chamar o endpoint de
        // custo por tratamento. Se marcou "salvar como padrão", grava direto
        // no catálogo (só custo_padrao, nunca preco_base — ver
        // TreatmentController::updateDefaultCost()); senão só devolve os
        // valores pro formulário principal gravar quando o tratamento inteiro
        // for submetido.
        if (form.value.save_as_default && catalogTreatmentId.value) {
            saving.value = true
            router.post(route('treatments.default-cost', catalogTreatmentId.value), { custo_padrao: form.value.cost }, {
                preserveScroll: true,
                onSuccess: () => {
                    saving.value = false
                    emit('close', { value_charged: form.value.value_charged, cost: form.value.cost })
                },
                onError: (e) => { saving.value = false; errors.value = e },
            })
            return
        }
        emit('close', { value_charged: form.value.value_charged, cost: form.value.cost })
        return
    }

    saving.value = true
    router.post(route('patients.treatments.cost', [props.patientId, props.treatment.id]), form.value, {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => {
            saving.value = false
            emit('close', { value_charged: form.value.value_charged, cost: form.value.cost })
        },
        onError: (e) => { saving.value = false; errors.value = e },
    })
}
</script>

<template>
<Modal :show="show" max-width="max-w-sm" title="Alterar Valor e Custo" @close="emit('close')">
    <div class="p-5 space-y-4">
        <p v-if="treatment" class="text-xs text-slate-500">{{ treatment.procedure_name }} · {{ treatment.budget_code }}</p>
        <p v-else-if="procedureName" class="text-xs text-slate-500">{{ procedureName }}</p>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Valor (R$)</label>
                <input v-model="form.value_charged" type="number" step="0.01" min="0"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.value_charged" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Custo (R$)</label>
                <input v-model="form.cost" type="number" step="0.01" min="0"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.cost" />
            </div>
        </div>

        <label v-if="catalogTreatmentId" class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
            <input v-model="form.save_as_default" type="checkbox" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-400" />
            Salvar este custo como padrão para próximas ocorrências deste procedimento
        </label>
    </div>

    <template #footer>
        <div class="flex gap-2">
            <button type="button" @click="emit('close')"
                    class="flex-1 border border-slate-200 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="save" :disabled="saving"
                    class="flex-1 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                {{ saving ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </template>
</Modal>
</template>
