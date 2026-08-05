<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import OdontogramChart from '@/Components/Prontuario/OdontogramChart.vue'
import TreatmentsTable from '@/Components/Treatments/TreatmentsTable.vue'
import Pagination from '@/Components/Pagination.vue'
import TreatmentFormModal from '@/Components/Treatments/TreatmentFormModal.vue'
import UpdateCostModal from '@/Components/Treatments/UpdateCostModal.vue'
import FinalizeTreatmentModal from '@/Components/Treatments/FinalizeTreatmentModal.vue'
import TreatmentHistoryModal from '@/Components/Treatments/TreatmentHistoryModal.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
    patient: Object,
    odontogram: { type: Object, default: () => null },
    toothStatuses: { type: Array, default: () => [] },
    treatmentsByTooth: { type: Object, default: () => ({}) },
    patientTreatments: { type: Object, default: () => ({ data: [], pagination: null }) },
    catalogTreatments: { type: Array, default: () => [] },
    convenios: { type: Array, default: () => [] },
    professionals: { type: Array, default: () => [] },
    treatmentStatuses: { type: Array, default: () => [] },
})

const teethData = computed(() => props.odontogram?.teeth_data ?? {})
const arch = ref('permanent')

// ── Modais ───────────────────────────────────────────────────────────────
const showForm = ref(false)
const showCost = ref(false)
const showFinalize = ref(false)
const showHistory = ref(false)
const showDeleteConfirm = ref(false)
const activeTreatment = ref(null)
const prefillTooth = ref(null)

const openAdd = (tooth = null) => { activeTreatment.value = null; prefillTooth.value = tooth; showForm.value = true }
const openEdit = (t) => { activeTreatment.value = t; showForm.value = true }
const openCost = (t) => { activeTreatment.value = t; showCost.value = true }
const openFinalize = (t) => { activeTreatment.value = t; showFinalize.value = true }
const openHistory = (t) => { activeTreatment.value = t; showHistory.value = true }
const openDelete = (t) => { activeTreatment.value = t; showDeleteConfirm.value = true }

const closeAll = () => {
    showForm.value = false
    showCost.value = false
    showFinalize.value = false
    showHistory.value = false
    showDeleteConfirm.value = false
}

const confirmDelete = () => {
    router.delete(route('patients.treatments.destroy', [props.patient.id, activeTreatment.value.id]), {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => closeAll(),
    })
}

const duplicate = (t) => {
    router.post(route('patients.treatments.duplicate', [props.patient.id, t.id]), {}, { preserveScroll: true, except: ['activeTab'] })
}

// Mesmo padrão de paginação da aba Anamneses (ver PatientAnamnesesTab.vue) —
// recarga parcial via Inertia, só busca este prop, mantém aba ativa e scroll.
const changePage = (page) => {
    router.visit(route('patients.show', props.patient.id), {
        data:           { treatments_page: page },
        only:           ['patientTreatments'],
        preserveState:  true,
        preserveScroll: true,
    })
}

// Deep-link a partir do odontograma: /patients/{id}?tab=treatments&tooth=XX
onMounted(() => {
    const tooth = new URLSearchParams(window.location.search).get('tooth')
    if (tooth) openAdd(tooth)
})
</script>

<template>
<div>
    <div class="flex items-center justify-between gap-3 mb-4">
        <p class="text-sm text-slate-500">Histórico odontológico completo do paciente, integrado ao odontograma.</p>
        <button type="button" @click="openAdd()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors shrink-0">
            + Adicionar Tratamento
        </button>
    </div>

    <!-- Odontograma + Barra de status -->
    <div class="bg-gradient-to-b from-slate-50/80 to-white border border-slate-200 rounded-2xl px-4 py-6 shadow-sm mb-6">
        <OdontogramChart
            :teeth-data="teethData"
            :tooth-statuses="toothStatuses"
            :treatments-by-tooth="treatmentsByTooth"
            @open-treatments="openAdd" />
    </div>

    <!-- Tabela de tratamentos -->
    <TreatmentsTable
        :treatments="patientTreatments.data"
        @add="openAdd()"
        @edit="openEdit"
        @cost="openCost"
        @finalize="openFinalize"
        @delete="openDelete"
        @view="openHistory"
        @duplicate="duplicate"
        @history="openHistory" />

    <Pagination v-if="patientTreatments.pagination"
                :pagination="patientTreatments.pagination"
                @change="changePage" />

    <TreatmentFormModal
        :show="showForm"
        :patient-id="patient.id"
        :treatment="activeTreatment"
        :arch="arch"
        :professionals="professionals"
        :convenios="convenios"
        :catalog-treatments="catalogTreatments"
        :statuses="treatmentStatuses"
        :default-tooth="prefillTooth"
        @close="closeAll" />

    <UpdateCostModal
        :show="showCost"
        :patient-id="patient.id"
        :treatment="activeTreatment"
        @close="closeAll" />

    <FinalizeTreatmentModal
        :show="showFinalize"
        :patient-id="patient.id"
        :treatment="activeTreatment"
        :professionals="professionals"
        :has-catalog-treatment="!!activeTreatment?.treatment_id"
        @close="closeAll" />

    <TreatmentHistoryModal
        :show="showHistory"
        :treatment="activeTreatment"
        @close="closeAll" />

    <Modal :show="showDeleteConfirm" max-width="max-w-sm" title="Excluir tratamento" @close="closeAll">
        <div class="p-5">
            <p class="text-sm text-slate-600">
                Tem certeza que deseja excluir o tratamento
                <span class="font-semibold text-slate-800">{{ activeTreatment?.procedure_name }}</span>
                ({{ activeTreatment?.budget_code }})? Essa ação não pode ser desfeita.
            </p>
        </div>
        <template #footer>
            <div class="flex gap-2">
                <button type="button" @click="closeAll"
                        class="flex-1 border border-slate-200 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" @click="confirmDelete"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                    Excluir
                </button>
            </div>
        </template>
    </Modal>
</div>
</template>
