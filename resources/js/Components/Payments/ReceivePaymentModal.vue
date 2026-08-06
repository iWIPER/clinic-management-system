<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    CreditCardIcon,
    ArrowsRightLeftIcon,
    PencilSquareIcon,
    ShieldCheckIcon,
    EllipsisHorizontalCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    payment: { type: Object, default: null },
    paymentMethods: { type: Array, default: () => [] },
})

// Um ícone por forma de pagamento (PatientPayment::METHODS) — só identidade
// visual, não afeta o valor enviado ao backend (continua sendo m.value).
// PIX não vem do pacote @heroicons (não existe um glifo do símbolo lá) — é um
// SVG customizado inline no template, no mesmo estilo Heroicons (24x24,
// stroke, sem preenchimento) pra ficar visualmente indistinguível dos demais.
// Mesmo padrão de ícone customizado de TreatmentActionsMenu.vue/Pagination.vue,
// sem pasta dedicada. Débito usa o ícone de banco (saída direta da conta) para
// se diferenciar do cartão de Crédito, em vez de repetir o mesmo ícone de cartão
// para os dois.
const METHOD_ICONS = {
    dinheiro: BanknotesIcon,
    debito: BuildingLibraryIcon,
    credito: CreditCardIcon,
    transferencia: ArrowsRightLeftIcon,
    cheque: PencilSquareIcon,
    convenio: ShieldCheckIcon,
    outro: EllipsisHorizontalCircleIcon,
}

const emit = defineEmits(['close'])

const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

// Mesmo cálculo de PatientPayment::effectiveTotal()/remaining() no backend —
// só para exibição/prefill imediatos, a validação de verdade é sempre no
// servidor (amount_received não pode passar do saldo devedor real).
const effectiveTotal = computed(() => {
    if (!props.payment) return 0
    return Math.max(0, Number(props.payment.amount) - Number(props.payment.discount) + Number(props.payment.interest))
})
const remaining = computed(() => {
    if (!props.payment) return 0
    return Math.max(0, effectiveTotal.value - Number(props.payment.amount_paid))
})

const form = ref({ amount_received: '', payment_method: '', notes: '' })
const errors = ref({})
const saving = ref(false)

watch(() => props.show, (visible) => {
    if (!visible || !props.payment) return
    form.value = { amount_received: remaining.value.toFixed(2), payment_method: '', notes: '' }
    errors.value = {}
})

const save = () => {
    if (!form.value.payment_method) {
        errors.value = { payment_method: 'Selecione a forma de pagamento.' }
        return
    }
    saving.value = true
    errors.value = {}
    router.post(route('patients.payments.receive', [props.patientId, props.payment.id]), form.value, {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => { saving.value = false; emit('close') },
        onError: (e) => { saving.value = false; errors.value = e },
    })
}
</script>

<template>
<Modal :show="show" max-width="max-w-md" title="Receber pagamento" @close="emit('close')">
    <div class="p-5 space-y-4">
        <div v-if="payment" class="rounded-lg bg-slate-50 border border-slate-100 p-3 space-y-1.5 text-sm">
            <p class="font-medium text-slate-800">
                {{ payment.treatment?.procedure_name }}
                <span class="text-slate-400 font-normal">· parcela {{ payment.installment_number }}/{{ payment.installment_total }}</span>
            </p>
            <div class="flex justify-between text-slate-500"><span>Valor</span><span>{{ fmtCurrency(payment.amount) }}</span></div>
            <div v-if="Number(payment.discount) > 0" class="flex justify-between text-slate-500"><span>Desconto</span><span>− {{ fmtCurrency(payment.discount) }}</span></div>
            <div v-if="Number(payment.interest) > 0" class="flex justify-between text-slate-500"><span>Juros</span><span>+ {{ fmtCurrency(payment.interest) }}</span></div>
            <div class="flex justify-between font-semibold text-slate-800 pt-1.5 border-t border-slate-200"><span>Total</span><span>{{ fmtCurrency(effectiveTotal) }}</span></div>
            <div v-if="Number(payment.amount_paid) > 0" class="flex justify-between text-amber-600"><span>Já recebido</span><span>{{ fmtCurrency(payment.amount_paid) }}</span></div>
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Valor recebido (R$)</label>
            <input v-model="form.amount_received" type="number" step="0.01" min="0.01" :max="remaining"
                   class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
            <p class="text-[11px] text-slate-400 mt-1">Saldo devedor: {{ fmtCurrency(remaining) }}. Informe um valor menor para registrar um recebimento parcial.</p>
            <InputError :message="errors.amount_received" />
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Forma de pagamento</label>
            <div class="grid grid-cols-2 gap-1.5">
                <button v-for="m in paymentMethods" :key="m.value" type="button"
                        @click="form.payment_method = m.value"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium border transition-colors text-left"
                        :class="form.payment_method === m.value
                            ? 'bg-teal-600 text-white border-teal-600'
                            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">
                    <svg v-if="m.value === 'pix'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8.7 5.44L11.29 2.85A1 1 0 0 1 12.71 2.85L15.3 5.44A1 1 0 0 1 15.3 6.86L12.71 9.45A1 1 0 0 1 11.29 9.45L8.7 6.86A1 1 0 0 1 8.7 5.44Z" />
                        <path d="M14.55 11.29L17.14 8.7A1 1 0 0 1 18.56 8.7L21.15 11.29A1 1 0 0 1 21.15 12.71L18.56 15.3A1 1 0 0 1 17.14 15.3L14.55 12.71A1 1 0 0 1 14.55 11.29Z" />
                        <path d="M8.7 17.14L11.29 14.55A1 1 0 0 1 12.71 14.55L15.3 17.14A1 1 0 0 1 15.3 18.56L12.71 21.15A1 1 0 0 1 11.29 21.15L8.7 18.56A1 1 0 0 1 8.7 17.14Z" />
                        <path d="M2.85 11.29L5.44 8.7A1 1 0 0 1 6.86 8.7L9.45 11.29A1 1 0 0 1 9.45 12.71L6.86 15.3A1 1 0 0 1 5.44 15.3L2.85 12.71A1 1 0 0 1 2.85 11.29Z" />
                    </svg>
                    <component :is="METHOD_ICONS[m.value]" v-else class="w-4 h-4 shrink-0" />
                    {{ m.label }}
                </button>
            </div>
            <InputError :message="errors.payment_method" />
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Observações (opcional)</label>
            <textarea v-model="form.notes" rows="2"
                      class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400"></textarea>
            <InputError :message="errors.notes" />
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
                {{ saving ? 'Registrando...' : 'Confirmar recebimento' }}
            </button>
        </div>
    </template>
</Modal>
</template>
