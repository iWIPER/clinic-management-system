<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    conversions:    { type: Object, required: true },
    filters:        { type: Object, default: () => ({}) },
    status_options: { type: Object, default: () => ({}) },
})

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function filterByStatus(status) {
    router.get(route('admin.referrals'), { status: status || undefined }, { preserveState: true })
}

async function refund(id) {
    if (!confirm('Marcar esta indicação como estornada? O bônus será retirado da carteira do indicador.')) return
    await window.axios.post(route('admin.referrals.refund', id))
    router.reload({ only: ['conversions'] })
}

async function review(id) {
    await window.axios.post(route('admin.referrals.review', id))
    router.reload({ only: ['conversions'] })
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4 flex flex-wrap gap-2">
            <button @click="filterByStatus('')"
                    class="rounded-lg border px-3 py-1.5 text-xs font-medium"
                    :class="!filters.status ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600 hover:bg-slate-50'">
                Todos
            </button>
            <button v-for="(label, key) in status_options" :key="key"
                    @click="filterByStatus(key)"
                    class="rounded-lg border px-3 py-1.5 text-xs font-medium"
                    :class="filters.status === key ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600 hover:bg-slate-50'">
                {{ label }}
            </button>
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Indicador</th>
                        <th class="px-4 py-3">Indicado</th>
                        <th class="px-4 py-3">Plano</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Teste</th>
                        <th class="px-4 py-3">Elegível</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="c in conversions.data" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ c.referrer }}</td>
                        <td class="px-4 py-3 font-medium">{{ c.referred }}</td>
                        <td class="px-4 py-3">{{ c.plan }}</td>
                        <td class="px-4 py-3 font-medium text-emerald-600">{{ formatMoney(c.reward_amount) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full border px-2 py-0.5 text-xs bg-slate-50">{{ c.status_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ c.trial_started || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ c.eligible_at || '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="review(c.id)" class="text-xs text-purple-600 hover:text-purple-700 mr-3">Revisar</button>
                            <button @click="refund(c.id)" class="text-xs text-rose-600 hover:text-rose-700">Estornar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>