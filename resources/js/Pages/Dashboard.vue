<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    clinic: Object,
    stats: Object,
});
</script>

<template>
    <AppLayout>
        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-slate-900">Bem-vindo(a)!</h1>
            <p class="text-slate-600 mt-1">Do agendamento ao recebimento em uma única plataforma.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Pacientes</div>
                <div class="text-4xl font-semibold mt-2">{{ stats?.patients ?? 0 }}</div>
            </div>
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Agendamentos hoje</div>
                <div class="text-4xl font-semibold mt-2">{{ stats?.appointments_today ?? 0 }}</div>
            </div>
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Consultas em andamento</div>
                <div class="text-4xl font-semibold mt-2">{{ stats?.consultations_in_progress ?? 0 }}</div>
            </div>
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Receita do mês</div>
                <div class="text-4xl font-semibold mt-2">R$ {{ stats?.revenue_month ?? '0,00' }}</div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border p-6">
                <h3 class="font-semibold mb-4">Ações rápidas</h3>
                <div class="space-y-3">
                    <Link :href="route('patients.create')" class="block px-4 py-3 bg-emerald-600 text-white rounded-lg text-center hover:bg-emerald-700">
                        + Cadastrar Novo Paciente
                    </Link>
                    <Link :href="route('appointments.create')" class="block px-4 py-3 border border-slate-300 rounded-lg text-center hover:bg-slate-50">
                        + Novo Agendamento
                    </Link>
                    <Link :href="route('appointments.index')" class="block px-4 py-3 text-sm text-center text-emerald-600 hover:underline">
                        Ver agenda completa →
                    </Link>
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-6">
                <h3 class="font-semibold mb-4">Fluxo Ouro (MVP)</h3>
                <ol class="text-sm space-y-1 text-slate-600 list-decimal ml-4">
                    <li>Cadastrar Paciente</li>
                    <li>Criar Agendamento (com tratamento)</li>
                    <li>Check-in → Consulta</li>
                    <li>Registrar Prontuário + Fotos + Execução</li>
                    <li>Baixa automática no Estoque</li>
                    <li>Orçamento → Pagamento (Financeiro)</li>
                </ol>
            </div>
        </div>
    </AppLayout>
</template>
