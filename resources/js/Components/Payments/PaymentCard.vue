<script setup>
import PaymentStatusBadge from './PaymentStatusBadge.vue'
import PaymentActionsMenu from './PaymentActionsMenu.vue'

const props = defineProps({
    payment: { type: Object, required: true },
})

defineEmits(['receive', 'edit', 'cancel', 'delete', 'plan', 'receipt'])

const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR', { timeZone: 'UTC' }) : '—'

// Mesma regra de PatientPayment::isOverdue() no backend — recalculado aqui só
// para exibição imediata, a fonte de verdade continua sendo o backend.
const isOverdue = () => {
    return ['pendente', 'parcial'].includes(props.payment.status)
        && new Date(props.payment.due_date) < new Date(new Date().toDateString())
}
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-white p-4 flex items-center gap-4 flex-wrap sm:flex-nowrap">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <h4 class="font-medium text-slate-900 truncate">{{ payment.treatment?.procedure_name ?? 'Tratamento' }}</h4>
                <span class="text-[11px] font-semibold text-slate-400 bg-slate-100 rounded-full px-2 py-0.5 shrink-0">
                    {{ payment.installment_number }}/{{ payment.installment_total }}
                </span>
                <PaymentStatusBadge :status="payment.status" :overdue="isOverdue()" />
            </div>
            <p class="text-xs text-slate-500 mt-1.5">
                Vencimento {{ fmtDate(payment.due_date) }}
                <template v-if="payment.treatment?.professional?.name"> · {{ payment.treatment.professional.name }}</template>
            </p>
        </div>

        <div class="text-right shrink-0">
            <p class="font-semibold text-slate-900">{{ fmtCurrency(payment.amount) }}</p>
            <p v-if="payment.status === 'parcial'" class="text-[11px] text-amber-600 mt-0.5">
                {{ fmtCurrency(payment.amount_paid) }} recebido(s)
            </p>
        </div>

        <div v-if="!['pago', 'cancelado'].includes(payment.status)" class="shrink-0 flex items-center gap-1.5">
            <button type="button" @click="$emit('receive', payment)"
                    class="rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold px-4 py-2 transition-colors">
                Receber
            </button>
            <PaymentActionsMenu :payment="payment"
                @edit="$emit('edit', payment)"
                @cancel="$emit('cancel', payment)"
                @delete="$emit('delete', payment)"
                @plan="$emit('plan', payment)"
                @receipt="$emit('receipt', payment)" />
        </div>
        <div v-else class="shrink-0">
            <PaymentActionsMenu :payment="payment"
                @edit="$emit('edit', payment)"
                @cancel="$emit('cancel', payment)"
                @delete="$emit('delete', payment)"
                @plan="$emit('plan', payment)"
                @receipt="$emit('receipt', payment)" />
        </div>
    </article>
</template>
