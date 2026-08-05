<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FinancingSimulationModal from '@/Components/Financial/FinancingSimulationModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    budgets: Array,
    transactions: Array,
    totalReceita: Number,
    totalDespesa: Number,
    pricing: Object,
});

const newTransaction = ref({ tipo: 'receita', valor: 0, categoria: '', descricao: '' });
const selectedBudget = ref(null);
const showSimulation = ref(false);

function openSimulation(budget) {
    selectedBudget.value = budget;
    showSimulation.value = true;
}

const submitTransaction = () => {
    router.post(route('finance.store-transaction'), newTransaction.value);
};

const updatePricing = () => {
    router.post(route('finance.update-pricing'), props.pricing);
};

const calcHoraTecnica = () => {
    const p = props.pricing;
    const custoHora = (p.salario_desejado / p.horas_trabalhadas) + (p.custos_fixos / p.horas_trabalhadas);
    const comMargem = custoHora * (1 + p.margem_lucro / 100);
    return comMargem.toFixed(2);
};
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Financeiro</h1>
            <Link :href="route('finance.marketplace')"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-teal-600 to-emerald-600 text-white text-sm font-medium shadow-sm hover:from-teal-700 hover:to-emerald-700">
                Hub de Crédito
            </Link>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Receita (pago)</div>
                <div class="text-3xl font-semibold text-green-600">R$ {{ totalReceita }}</div>
            </div>
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Despesa (pago)</div>
                <div class="text-3xl font-semibold text-red-600">R$ {{ totalDespesa }}</div>
            </div>
            <div class="bg-white p-5 rounded-2xl border">
                <div class="text-sm text-slate-500">Saldo</div>
                <div class="text-3xl font-semibold">R$ {{ (totalReceita - totalDespesa).toFixed(2) }}</div>
            </div>
        </div>

        <!-- Precificação -->
        <div class="bg-white p-6 rounded-2xl border mb-6">
            <h3 class="font-medium mb-4">Calculadora de Precificação (Hora Técnica + Hora Clínica)</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Salário Desejado (R$)</label>
                    <input v-model="pricing.salario_desejado" type="number" class="w-full border p-2 rounded" />
                </div>
                <div>
                    <label class="text-sm">Horas/Mês</label>
                    <input v-model="pricing.horas_trabalhadas" type="number" class="w-full border p-2 rounded" />
                </div>
            </div>
            <button @click="updatePricing" class="mt-4 bg-emerald-600 text-white px-4 py-2 rounded text-sm">Salvar e Calcular</button>
            <div class="mt-3 text-sm">Hora Técnica sugerida: R$ {{ calcHoraTecnica() }}</div>
        </div>

        <!-- Lançamentos -->
        <div class="bg-white p-6 rounded-2xl border mb-6">
            <h3 class="font-medium mb-4">Novo Lançamento</h3>
            <div class="flex gap-2">
                <select v-model="newTransaction.tipo" class="border p-2 rounded">
                    <option value="receita">Receita</option>
                    <option value="despesa">Despesa</option>
                </select>
                <input v-model="newTransaction.valor" type="number" placeholder="Valor" class="border p-2 rounded w-24" />
                <input v-model="newTransaction.categoria" placeholder="Categoria" class="border p-2 rounded flex-1" />
                <button @click="submitTransaction" class="bg-slate-800 text-white px-4 rounded">Lançar</button>
            </div>
        </div>

        <!-- Orçamentos -->
        <div v-if="budgets?.length" class="bg-white rounded-2xl border p-6 mb-6">
            <h3 class="font-medium mb-3">Orçamentos recentes</h3>
            <div v-for="b in budgets" :key="b.id"
                 class="flex items-center justify-between py-3 border-b last:border-0 text-sm">
                <div>
                    <p class="font-medium text-slate-800">{{ b.patient?.full_name ?? 'Paciente' }}</p>
                    <p class="text-xs text-slate-500">R$ {{ b.total }} · {{ b.status }}</p>
                </div>
                <button @click="openSimulation(b)"
                        class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-xs font-medium hover:bg-teal-50">
                    Simular financiamento
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border p-6">
            <h3 class="font-medium mb-3">Lançamentos Recentes</h3>
            <div v-for="t in transactions" :key="t.id" class="flex justify-between py-1 text-sm border-b">
                <div>{{ t.tipo }} - {{ t.categoria }} - {{ t.descricao }}</div>
                <div :class="t.tipo === 'receita' ? 'text-green-600' : 'text-red-600'">R$ {{ t.valor }}</div>
            </div>
        </div>

        <FinancingSimulationModal
            v-if="selectedBudget"
            :show="showSimulation"
            :budget="selectedBudget"
            @close="showSimulation = false"
            @proposal-submitted="router.reload({ only: ['budgets'] })"
        />
    </AppLayout>
</template>
