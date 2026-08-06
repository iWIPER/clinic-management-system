<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/UI/Modal.vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import PaymentSummaryCard from '@/Components/Payments/PaymentSummaryCard.vue'
import PaymentCard from '@/Components/Payments/PaymentCard.vue'
import ReceivePaymentModal from '@/Components/Payments/ReceivePaymentModal.vue'
import PaymentFormModal from '@/Components/Payments/PaymentFormModal.vue'
import PaymentPlanModal from '@/Components/Payments/PaymentPlanModal.vue'
import { ArrowDownTrayIcon, ChevronDownIcon, TableCellsIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

// Mesma lista/padrão do botão "Exportar" da listagem de pacientes
// (Patients/Index.vue) — reaproveitado ao pé da letra.
const EXPORT_FORMATS = [
    { format: 'excel', label: 'Excel (.xlsx)', icon: TableCellsIcon },
    { format: 'csv', label: 'CSV', icon: DocumentTextIcon },
]

const props = defineProps({
    patient: Object,
    patientPayments: { type: Object, default: () => ({ data: [], pagination: null }) },
    paymentSummary: { type: Object, default: () => ({ received: 0, outstanding: 0, overdue: 0, total_charged: 0 }) },
    paymentMethods: { type: Array, default: () => [] },
    paymentStatuses: { type: Array, default: () => [] },
})

// Valores aceitos pelo backend em payments_status (ver PatientController::show).
// "atrasado" é um caso especial calculado no backend (pendente/parcial vencidos),
// não um status persistido — ver PatientPayment::isOverdue().
const FILTERS = [
    { value: '',          label: 'Todos' },
    { value: 'pendente',  label: 'Pendentes' },
    { value: 'pago',      label: 'Recebidos' },
    { value: 'atrasado',  label: 'Em atraso' },
    { value: 'parcial',   label: 'Parciais' },
]

// Só as 3 janelas fixas pedidas no spec — sem date-picker livre nesta fase.
const PERIODS = [
    { value: '',                 label: 'Todo o período' },
    { value: 'este_mes',         label: 'Este mês' },
    { value: 'mes_passado',      label: 'Mês passado' },
    { value: 'ultimos_90_dias',  label: 'Últimos 90 dias' },
]

const statusFilter = ref('')
const periodFilter = ref('')

const applyFilter = (value) => {
    statusFilter.value = value
    router.visit(route('patients.show', props.patient.id), {
        data: { payments_page: 1, payments_status: value || undefined, payments_period: periodFilter.value || undefined },
        only: ['patientPayments'],
        preserveState: true,
        preserveScroll: true,
    })
}

const applyPeriod = () => {
    router.visit(route('patients.show', props.patient.id), {
        data: { payments_page: 1, payments_status: statusFilter.value || undefined, payments_period: periodFilter.value || undefined },
        only: ['patientPayments'],
        preserveState: true,
        preserveScroll: true,
    })
}

const changePage = (page) => {
    router.visit(route('patients.show', props.patient.id), {
        data: { payments_page: page, payments_status: statusFilter.value || undefined, payments_period: periodFilter.value || undefined },
        only: ['patientPayments'],
        preserveState: true,
        preserveScroll: true,
    })
}

const exportUrl = (format) => {
    const params = new URLSearchParams({ format })
    if (statusFilter.value) params.set('payments_status', statusFilter.value)
    if (periodFilter.value) params.set('payments_period', periodFilter.value)
    return `${route('patients.payments.export', props.patient.id)}?${params.toString()}`
}

const openReceipt = (payment) => {
    window.open(route('patients.payments.receipt', [props.patient.id, payment.id]), '_blank')
}

// ── Ações por parcela ───────────────────────────────────────────────────
const activePayment = ref(null)
const showReceive = ref(false)
const showEdit = ref(false)
const showPlan = ref(false)
const showDeleteConfirm = ref(false)

const openReceive = (payment) => { activePayment.value = payment; showReceive.value = true }
const openEdit = (payment) => { activePayment.value = payment; showEdit.value = true }
const openPlan = (payment) => { activePayment.value = payment; showPlan.value = true }
const openDelete = (payment) => { activePayment.value = payment; showDeleteConfirm.value = true }

const closeAll = () => {
    showReceive.value = false
    showEdit.value = false
    showPlan.value = false
    showDeleteConfirm.value = false
}

// "Cancelar" é reversível na prática (a parcela continua existindo, só
// marcada como encerrada) — confirmação nativa é suficiente, sem precisar
// de um 3º modal. "Excluir" (irreversível) usa Modal, mesmo padrão de
// PatientTreatmentsTab.vue.
const cancelPayment = (payment) => {
    if (!confirm(`Cancelar a parcela ${payment.installment_number}/${payment.installment_total}?`)) return
    router.post(route('patients.payments.cancel', [props.patient.id, payment.id]), {}, { preserveScroll: true, except: ['activeTab'] })
}

const confirmDelete = () => {
    router.delete(route('patients.payments.destroy', [props.patient.id, activePayment.value.id]), {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => closeAll(),
    })
}
</script>

<template>
<div>
    <p class="text-sm text-slate-500 mb-4">Cobranças e recebimentos vinculados aos tratamentos deste paciente.</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <PaymentSummaryCard label="Recebido" :value="paymentSummary.received" tone="positive" />
        <PaymentSummaryCard label="A receber" :value="paymentSummary.outstanding" tone="warning" />
        <PaymentSummaryCard label="Em atraso" :value="paymentSummary.overdue" tone="critical" />
        <PaymentSummaryCard label="Total cobrado" :value="paymentSummary.total_charged" tone="neutral" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap gap-1.5">
            <button v-for="f in FILTERS" :key="f.value" type="button"
                    @click="applyFilter(f.value)"
                    class="rounded-full px-3 py-1.5 text-xs font-medium border transition-colors"
                    :class="statusFilter === f.value
                        ? 'bg-slate-900 text-white border-slate-900'
                        : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">
                {{ f.label }}
            </button>
        </div>

        <select v-model="periodFilter" @change="applyPeriod"
                class="border rounded-lg px-2.5 py-1.5 text-xs text-slate-600">
            <option v-for="p in PERIODS" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>
    </div>

    <div v-if="!patientPayments.data?.length" class="rounded-xl border border-dashed border-slate-200 py-16 text-center text-sm text-slate-500">
        Nenhuma cobrança encontrada.
    </div>

    <div v-else class="space-y-3">
        <PaymentCard v-for="payment in patientPayments.data" :key="payment.id" :payment="payment"
            @receive="openReceive" @edit="openEdit" @cancel="cancelPayment" @delete="openDelete" @plan="openPlan" @receipt="openReceipt" />
    </div>

    <div v-if="patientPayments.data?.length" class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <NavbarDropdown align="left" width="w-44" direction="up">
            <template #trigger>
                <button type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors">
                    <ArrowDownTrayIcon class="w-4 h-4" />
                    Exportar
                    <ChevronDownIcon class="w-3.5 h-3.5" />
                </button>
            </template>
            <template #default="{ close }">
                <a v-for="f in EXPORT_FORMATS" :key="f.format"
                   :href="exportUrl(f.format)" @click="close"
                   class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                    <component :is="f.icon" class="w-4 h-4 text-slate-400 shrink-0" />
                    {{ f.label }}
                </a>
            </template>
        </NavbarDropdown>

        <Pagination v-if="patientPayments.pagination"
                    :pagination="patientPayments.pagination"
                    :bordered="false"
                    @change="changePage" />
    </div>

    <ReceivePaymentModal :show="showReceive" :patient-id="patient.id" :payment="activePayment"
        :payment-methods="paymentMethods" @close="closeAll" />

    <PaymentFormModal :show="showEdit" :patient-id="patient.id" :payment="activePayment"
        :payment-methods="paymentMethods" @close="closeAll" />

    <PaymentPlanModal :show="showPlan" :patient-id="patient.id" :payment="activePayment" @close="closeAll" />

    <Modal :show="showDeleteConfirm" max-width="max-w-sm" title="Excluir parcela" @close="closeAll">
        <div class="p-5">
            <p class="text-sm text-slate-600">
                Tem certeza que deseja excluir a parcela
                <span class="font-semibold text-slate-800">{{ activePayment?.installment_number }}/{{ activePayment?.installment_total }}</span>
                de <span class="font-semibold text-slate-800">{{ activePayment?.treatment?.procedure_name }}</span>? Essa ação não pode ser desfeita.
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
