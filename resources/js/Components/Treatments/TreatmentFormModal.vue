<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import ProcedureAutocomplete from './ProcedureAutocomplete.vue'
import ToothFaceSelector from './ToothFaceSelector.vue'
import UpdateCostModal from './UpdateCostModal.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    treatment: { type: Object, default: null }, // presente = modo edição
    arch: { type: String, default: 'permanent' },
    professionals: { type: Array, default: () => [] },
    convenios: { type: Array, default: () => [] },
    catalogTreatments: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    defaultTooth: { type: String, default: null },
})

const emit = defineEmits(['close'])

const particularConvenioId = computed(() =>
    props.convenios.find(c => c.nome === 'Particular')?.id ?? ''
)

const blank = () => ({
    treatment_id: null,
    procedure_name: '',
    professional_id: '',
    convenio_id: particularConvenioId.value,
    treatment_date: new Date().toISOString().slice(0, 10),
    teeth: props.defaultTooth ? [props.defaultTooth] : [],
    faces: [],
    value_charged: '',
    cost: 0, // sempre 0 por padrão (nunca herdado do catálogo) — só muda via "Alterar"
    status: 'em_andamento',
    notes: '',
})

const form = ref(blank())
const errors = ref({})
const saving = ref(false)

watch(() => props.show, (visible) => {
    if (!visible) return
    if (props.treatment) {
        form.value = {
            treatment_id: props.treatment.treatment_id ?? null,
            procedure_name: props.treatment.procedure_name ?? '',
            professional_id: props.treatment.professional_id ?? '',
            convenio_id: props.treatment.convenio_id ?? particularConvenioId.value,
            treatment_date: props.treatment.treatment_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
            teeth: props.treatment.tooth ? [props.treatment.tooth] : [],
            faces: props.treatment.faces ?? [],
            value_charged: props.treatment.value_charged ?? '',
            cost: props.treatment.cost ?? 0,
            status: props.treatment.status ?? 'em_andamento',
            notes: props.treatment.notes ?? '',
        }
    } else {
        form.value = blank()
    }
    errors.value = {}
})

// Custo NUNCA é herdado do catálogo, nem do `custo_padrao` — mesmo quando
// esse procedimento específico já tem um custo customizado salvo (checkbox
// "salvar como padrão" no UpdateCostModal). Fica sempre 0 (ver blank()) até o
// usuário abrir "Alterar" e definir manualmente pra este tratamento. O
// `custo_padrao` continua sendo gravado nesse checkbox — só não retroalimenta
// o formulário — hoje serve como registro pro catálogo (base pro cálculo de
// comissão no futuro), não como sugestão de preenchimento.
const onProcedureSelect = (t) => {
    if (!form.value.value_charged) form.value.value_charged = t.preco_base
}

const creatableStatuses = computed(() => props.statuses.filter(s => s.value !== 'concluido'))

// ── Custo (link "Alterar" abre o mesmo UpdateCostModal já usado no menu "⋮")
// Em edição (treatment existe) ele salva direto no servidor; em criação
// (ainda sem id) ele só devolve os valores pra cá — a gravação de verdade só
// acontece quando o formulário inteiro for submetido, igual já era antes com
// os campos diretos.
const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const showCostModal = ref(false)
const onCostSaved = (saved) => {
    showCostModal.value = false
    if (!saved) return
    form.value.value_charged = saved.value_charged
    form.value.cost = saved.cost
}

// ── Cadastro rápido de procedimento (nova aba, reaproveita 100% a página
// "Novo Procedimento" — sem duplicar formulário/validação/controller). Como
// o catálogo já foi carregado antes de o usuário cadastrar o novo na outra
// aba, ao voltar o foco pra esta aba recarrega só `catalogTreatments` (mesmo
// padrão de router.reload({ only: [...] }) já usado no restante do projeto).
const onWindowFocus = () => {
    if (!props.show) return
    router.reload({ only: ['catalogTreatments'], preserveScroll: true, preserveState: true })
}
onMounted(() => window.addEventListener('focus', onWindowFocus))
onUnmounted(() => window.removeEventListener('focus', onWindowFocus))

const validate = () => {
    const errs = {}
    if (!form.value.professional_id) errs.professional_id = 'Selecione um profissional.'
    if (!form.value.treatment_id && !form.value.procedure_name?.trim()) errs.procedure_name = 'Selecione ou informe um procedimento.'
    errors.value = errs
    return Object.keys(errs).length === 0
}

const save = () => {
    if (!validate()) return

    saving.value = true
    errors.value = {}

    const options = {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => { saving.value = false; emit('close') },
        onError: (e) => { saving.value = false; errors.value = e },
    }

    if (props.treatment) {
        // Edição é sempre um dente só (uma linha já salva) — o endpoint de
        // update continua recebendo `tooth` singular, sem mudança nele.
        const { teeth, ...rest } = form.value
        const payload = { ...rest, tooth: teeth[0] ?? null }
        router.put(route('patients.treatments.update', [props.patientId, props.treatment.id]), payload, options)
    } else {
        // Criação aceita vários dentes — o backend cria uma linha por dente
        // marcado (ver PatientTreatmentController::store()).
        router.post(route('patients.treatments.store', props.patientId), form.value, options)
    }
}
</script>

<template>
<Modal :show="show" max-width="max-w-2xl" :title="treatment ? `Editar tratamento ${treatment.budget_code}` : 'Adicionar tratamento'" @close="emit('close')">
    <div class="p-5 space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Profissional <span class="text-red-500">*</span></label>
                <select v-model="form.professional_id" class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white"
                        :class="errors.professional_id ? 'border-red-400' : 'border-slate-200'">
                    <option value="">Selecione...</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <InputError :message="errors.professional_id" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Convênio</label>
                <select v-model="form.convenio_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white">
                    <option v-for="c in convenios" :key="c.id" :value="c.id">{{ c.nome }}</option>
                </select>
                <InputError :message="errors.convenio_id" />
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Data</label>
                <input v-model="form.treatment_date" type="date"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.treatment_date" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Status</label>
                <select v-model="form.status" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white">
                    <option v-for="s in creatableStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <InputError :message="errors.status" />
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-semibold text-slate-600 block">Procedimento <span class="text-red-500">*</span></label>
                <a :href="route('treatments.create')" target="_blank" rel="noopener"
                   class="text-[11px] font-semibold text-teal-600 hover:text-teal-800 transition-colors">
                    Cadastrar novo procedimento
                </a>
            </div>
            <ProcedureAutocomplete
                v-model="form.treatment_id"
                v-model:procedure-name="form.procedure_name"
                :treatments="catalogTreatments"
                :invalid="!!errors.procedure_name"
                @select="onProcedureSelect" />
            <InputError :message="errors.procedure_name" />
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Valor e custo</label>
            <div class="flex items-center justify-between gap-3">
                <span class="text-base font-semibold text-slate-800">{{ fmtCurrency(form.value_charged) }}</span>
                <p class="text-xs text-slate-400 shrink-0">
                    Custo: {{ fmtCurrency(form.cost) }}
                    <button type="button" @click="showCostModal = true"
                            class="ml-1 font-semibold text-teal-600 hover:text-teal-800 transition-colors">
                        Alterar
                    </button>
                </p>
            </div>
            <InputError :message="errors.value_charged" />
            <InputError :message="errors.cost" />
        </div>

        <div class="border-t border-slate-100 pt-4">
            <ToothFaceSelector :arch="arch" :multiple="!treatment" v-model:teeth="form.teeth" v-model:faces="form.faces" />
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Observações</label>
            <textarea v-model="form.notes" rows="3" placeholder="Anotações sobre este tratamento..."
                      class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
        </div>
    </div>

    <template #footer>
        <div class="flex gap-2">
            <button type="button" @click="emit('close')"
                    class="flex-1 border border-slate-200 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="save" :disabled="saving"
                    class="flex-1 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                {{ saving ? 'Salvando...' : (treatment ? 'Salvar alterações' : 'Adicionar tratamento') }}
            </button>
        </div>
    </template>
</Modal>

<!-- Aninhado (não substitui o modal principal) — preserva os outros campos
     já preenchidos aqui, que seriam perdidos se fechássemos/reabríssemos
     este modal pra trocar pelo de custo. -->
<UpdateCostModal
    :show="showCostModal"
    :patient-id="patientId"
    :treatment="treatment"
    :treatment-id="form.treatment_id"
    :procedure-name="form.procedure_name"
    :current-value-charged="form.value_charged"
    :local-only="!treatment"
    @close="onCostSaved" />
</template>
