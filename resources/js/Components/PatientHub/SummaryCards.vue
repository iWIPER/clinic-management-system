<script setup>
defineProps({ summary: Object });

const fmtCurrency = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—';
</script>

<template>
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Financeiro</h3>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500 text-xs">Total Orçado</dt><dd class="font-semibold text-slate-900">{{ fmtCurrency(summary.financial.total_budgeted) }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Total Recebido</dt><dd class="font-semibold text-emerald-700">{{ fmtCurrency(summary.financial.total_received) }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Total Pendente</dt><dd class="font-semibold text-amber-700">{{ fmtCurrency(summary.financial.total_pending) }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Ticket Médio</dt><dd class="font-semibold text-slate-900">{{ fmtCurrency(summary.financial.ticket_average) }}</dd></div>
                <div class="col-span-2 pt-2 border-t border-slate-100">
                    <dt class="text-slate-500 text-xs">Lifetime Value</dt>
                    <dd class="text-lg font-bold text-purple-700">{{ fmtCurrency(summary.financial.lifetime_value) }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Clínico</h3>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500 text-xs">Consultas realizadas</dt><dd class="font-semibold">{{ summary.clinical.consultations_completed }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Tratamentos concluídos</dt><dd class="font-semibold">{{ summary.clinical.treatments_completed }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Tratamentos ativos</dt><dd class="font-semibold text-blue-700">{{ summary.clinical.treatments_active }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Último dente</dt><dd class="font-semibold">{{ summary.clinical.last_tooth || '—' }}</dd></div>
                <div class="col-span-2"><dt class="text-slate-500 text-xs">Último procedimento</dt><dd class="font-medium text-slate-800">{{ summary.clinical.last_procedure || '—' }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Última consulta</dt><dd>{{ fmtDate(summary.clinical.last_visit_at) }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Próxima consulta</dt><dd class="text-emerald-700 font-medium">{{ fmtDate(summary.clinical.next_appointment_at) }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Relacionamento</h3>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500 text-xs">Taxa comparecimento</dt><dd class="font-semibold text-emerald-700">{{ summary.relationship.attendance_rate }}%</dd></div>
                <div><dt class="text-slate-500 text-xs">Faltas</dt><dd class="font-semibold text-red-600">{{ summary.relationship.no_shows }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Cancelamentos</dt><dd class="font-semibold">{{ summary.relationship.cancellations }}</dd></div>
                <div><dt class="text-slate-500 text-xs">Tempo como paciente</dt><dd class="font-semibold">{{ summary.relationship.months_as_patient }} meses</dd></div>
                <div class="col-span-2 pt-2 border-t border-slate-100">
                    <dt class="text-slate-500 text-xs">Primeiro atendimento</dt>
                    <dd class="font-medium">{{ fmtDate(summary.relationship.first_visit_at) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>