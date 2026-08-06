<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    // A parcela cujo menu disparou a ação — usada só pra saber qual
    // tratamento replanejar (patient_treatment_id) e mostrar o valor total.
    payment: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (d) => d.toLocaleDateString('pt-BR', { timeZone: 'UTC' })

const form = ref({ installments: 2, first_due_date: '', interval_days: 30 })
const errors = ref({})
const saving = ref(false)

watch(() => props.show, (visible) => {
    if (!visible) return
    form.value = { installments: 2, first_due_date: new Date().toISOString().slice(0, 10), interval_days: 30 }
    errors.value = {}
})

const totalAmount = computed(() => Number(props.payment?.treatment?.value_charged ?? 0))

// Mesma lógica de PatientPaymentService::generatePlan() — divide em
// centavos inteiros e a última parcela absorve o resto, pra nunca perder
// nem inventar centavo por arredondamento. Só para pré-visualização; quem
// grava de verdade é sempre o backend.
const preview = computed(() => {
    const installments = Math.max(1, Number(form.value.installments) || 1)
    const totalCents = Math.round(totalAmount.value * 100)
    const baseCents = Math.floor(totalCents / installments)
    const remainderCents = totalCents - baseCents * installments
    const intervalDays = Number(form.value.interval_days) || 0
    const firstDue = form.value.first_due_date ? new Date(form.value.first_due_date + 'T00:00:00Z') : null

    const rows = []
    for (let i = 1; i <= installments; i++) {
        const cents = baseCents + (i === installments ? remainderCents : 0)
        const due = firstDue ? new Date(firstDue) : null
        if (due) due.setUTCDate(due.getUTCDate() + intervalDays * (i - 1))
        rows.push({ number: i, amount: cents / 100, due })
    }
    return rows
})

const save = () => {
    saving.value = true
    errors.value = {}
    router.post(route('patients.treatments.payment-plan', [props.patientId, props.payment.patient_treatment_id]), form.value, {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => { saving.value = false; emit('close') },
        onError: (e) => { saving.value = false; errors.value = e },
    })
}
</script>

<template>
<Modal :show="show" max-width="max-w-lg" title="Criar plano de pagamento" @close="emit('close')">
    <div class="p-5 space-y-4">
        <p v-if="payment" class="text-xs text-slate-500">
            {{ payment.treatment?.procedure_name }} · valor total {{ fmtCurrency(totalAmount) }}
        </p>
        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
            Isso substitui a(s) parcela(s) atual(is) deste tratamento por um novo plano — só é possível enquanto nenhuma delas tiver recebido algum pagamento.
        </p>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Nº de parcelas</label>
                <input v-model="form.installments" type="number" min="2" max="24"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.installments" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">1ª cobrança</label>
                <input v-model="form.first_due_date" type="date"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.first_due_date" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Intervalo (dias)</label>
                <input v-model="form.interval_days" type="number" min="1" max="365"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.interval_days" />
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-600 mb-1.5">Prévia do cronograma</p>
            <div class="rounded-lg border border-slate-200 divide-y divide-slate-100 max-h-48 overflow-y-auto">
                <div v-for="row in preview" :key="row.number" class="flex items-center justify-between px-3 py-1.5 text-sm">
                    <span class="text-slate-500">{{ row.number }}/{{ preview.length }} · {{ row.due ? fmtDate(row.due) : '—' }}</span>
                    <span class="font-medium text-slate-800">{{ fmtCurrency(row.amount) }}</span>
                </div>
            </div>
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
                {{ saving ? 'Criando...' : 'Criar plano' }}
            </button>
        </div>
    </template>
</Modal>
</template>
