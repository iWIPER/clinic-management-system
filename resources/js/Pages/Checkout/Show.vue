<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    plan:             { type: Object,  required: true },
    price_amount:     { type: Number,  required: true },
    discount_amount:  { type: Number,  required: true },
    total_amount:     { type: Number,  required: true },
    has_discount:     { type: Boolean, default: false },
})

const form = useForm({ interval: props.plan.interval })

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function submit() {
    form.post(route('checkout.store', props.plan.slug))
}
</script>

<template>
    <AppLayout>
        <div class="max-w-lg mx-auto">
            <h1 class="text-2xl font-semibold text-slate-900 mb-1">Assinar plano</h1>
            <p class="text-sm text-slate-500 mb-6">Revise os detalhes antes de continuar para o pagamento.</p>

            <div class="rounded-2xl border bg-white p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Plano</span>
                    <span class="font-semibold text-slate-900">{{ plan.name }} ({{ plan.interval === 'yearly' ? 'anual' : 'mensal' }})</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Valor</span>
                    <span class="font-medium text-slate-800">{{ formatMoney(price_amount) }}</span>
                </div>

                <div v-if="has_discount" class="flex items-center justify-between text-emerald-600">
                    <span>Desconto de convite</span>
                    <span class="font-medium">- {{ formatMoney(discount_amount) }}</span>
                </div>

                <div class="border-t pt-4 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">Total</span>
                    <span class="text-2xl font-bold text-slate-900">{{ formatMoney(total_amount) }}</span>
                </div>

                <button @click="submit" :disabled="form.processing"
                        class="w-full mt-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    Continuar para pagamento
                </button>

                <p class="text-xs text-slate-400 text-center">Pagamento processado com segurança pelo Stripe.</p>
            </div>
        </div>
    </AppLayout>
</template>
