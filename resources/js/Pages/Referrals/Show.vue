<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    conversion: { type: Object, required: true },
})

const STATUS_STYLES = {
    testing:           'bg-blue-50 text-blue-700 border-blue-200',
    awaiting_payment:  'bg-amber-50 text-amber-700 border-amber-200',
    payment_confirmed: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    eligible:          'bg-emerald-50 text-emerald-700 border-emerald-200',
    paid:              'bg-slate-50 text-slate-700 border-slate-200',
    cancelled:         'bg-red-50 text-red-700 border-red-200',
    expired:           'bg-slate-50 text-slate-500 border-slate-200',
}

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(iso) {
    if (! iso) return '—'
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <Link :href="route('referrals.index')" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                ← Voltar ao programa
            </Link>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="rounded-2xl border bg-white p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ conversion.clinic_name }}</h1>
                        <p class="text-sm text-slate-500 mt-1">{{ conversion.clinic_city }}</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold"
                          :class="STATUS_STYLES[conversion.status]">
                        {{ conversion.status_label }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plano</dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-800">{{ conversion.plan_name }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dias restantes de teste</dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-800">{{ conversion.days_remaining }} dias</dd>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Data prevista da liberação</dt>
                        <dd class="mt-1 text-lg font-semibold text-emerald-800">{{ formatDate(conversion.eligible_at) }}</dd>
                    </div>
                    <div class="rounded-xl bg-blue-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-600">Valor do bônus</dt>
                        <dd class="mt-1 text-lg font-semibold text-blue-800">{{ formatMoney(conversion.reward_amount) }}</dd>
                    </div>
                </dl>

                <div class="mt-6 pt-6 border-t space-y-2 text-sm text-slate-500">
                    <p v-if="conversion.trial_started">Teste iniciado: {{ formatDate(conversion.trial_started) }}</p>
                    <p v-if="conversion.plan_subscribed">Plano assinado: {{ formatDate(conversion.plan_subscribed) }}</p>
                    <p v-if="conversion.payment_confirmed">Pagamento confirmado: {{ formatDate(conversion.payment_confirmed) }}</p>
                    <p v-if="conversion.paid_at">Bônus pago: {{ formatDate(conversion.paid_at) }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>