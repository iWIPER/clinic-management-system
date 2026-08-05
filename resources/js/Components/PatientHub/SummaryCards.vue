<script setup>
defineProps({ summary: Object });

const fmtCurrency = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—';
</script>

<template>
    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <h3 class="text-sm font-semibold text-slate-900">Resumo Financeiro</h3>
            <div class="sm:text-right">
                <p class="text-xs text-slate-500">Lifetime Value</p>
                <p class="text-2xl font-bold text-purple-700 leading-tight">{{ fmtCurrency(summary.financial.lifetime_value) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5 pt-4 border-t border-slate-100">
            <div>
                <p class="text-xs text-slate-500">Total Faturado</p>
                <p class="mt-0.5 font-semibold text-slate-900">{{ fmtCurrency(summary.financial.total_budgeted) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Total Pago</p>
                <p class="mt-0.5 font-semibold text-emerald-700">{{ fmtCurrency(summary.financial.total_received) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Total Pendente</p>
                <p class="mt-0.5 font-semibold" :class="summary.financial.total_pending > 0 ? 'text-amber-700' : 'text-slate-900'">
                    {{ fmtCurrency(summary.financial.total_pending) }}
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Ticket Médio</p>
                <p class="mt-0.5 font-semibold text-slate-900">{{ fmtCurrency(summary.financial.ticket_average) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Último Pagamento</p>
                <p class="mt-0.5 font-semibold text-slate-900">{{ fmtDate(summary.financial.last_payment_at) }}</p>
            </div>
        </div>

        <div v-if="summary.financial.convenio" class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2 text-sm">
            <span class="text-slate-500">Convênio</span>
            <span class="font-medium text-slate-800">{{ summary.financial.convenio }}</span>
        </div>
    </div>
</template>
