<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: Boolean,
    patientId: { type: [Number, String], required: true },
    payment: { type: Object, default: null },
    paymentMethods: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const form = ref({ due_date: '', discount: 0, interest: 0, payment_method: '', notes: '' })
const errors = ref({})
const saving = ref(false)

watch(() => props.show, (visible) => {
    if (!visible || !props.payment) return
    form.value = {
        due_date: props.payment.due_date?.slice(0, 10) ?? '',
        discount: Number(props.payment.discount ?? 0),
        interest: Number(props.payment.interest ?? 0),
        payment_method: props.payment.payment_method ?? '',
        notes: props.payment.notes ?? '',
    }
    errors.value = {}
})

const save = () => {
    saving.value = true
    errors.value = {}
    router.put(route('patients.payments.update', [props.patientId, props.payment.id]), form.value, {
        preserveScroll: true,
        except: ['activeTab'],
        onSuccess: () => { saving.value = false; emit('close') },
        onError: (e) => { saving.value = false; errors.value = e },
    })
}
</script>

<template>
<Modal :show="show" max-width="max-w-md" title="Editar parcela" @close="emit('close')">
    <div class="p-5 space-y-4">
        <p v-if="payment" class="text-xs text-slate-500">
            {{ payment.treatment?.procedure_name }} · parcela {{ payment.installment_number }}/{{ payment.installment_total }}
        </p>

        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Vencimento</label>
                <input v-model="form.due_date" type="date"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.due_date" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Desconto (R$)</label>
                <input v-model="form.discount" type="number" step="0.01" min="0"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.discount" />
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Juros (R$)</label>
                <input v-model="form.interest" type="number" step="0.01" min="0"
                       class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400" />
                <InputError :message="errors.interest" />
            </div>
        </div>

        <div>
            <label class="text-xs font-semibold text-slate-600 mb-1 block">Forma de pagamento prevista (opcional)</label>
            <select v-model="form.payment_method" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400 bg-white">
                <option value="">Não definida</option>
                <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
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
                {{ saving ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </template>
</Modal>
</template>
