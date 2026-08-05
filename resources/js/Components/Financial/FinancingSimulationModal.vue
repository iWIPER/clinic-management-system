<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
    budget: Object,
});

const emit = defineEmits(['close', 'proposal-submitted']);

const loading = ref(false);
const cpf = ref(props.budget?.patient?.cpf ?? '');
const installments = ref(12);
const result = ref(null);
const selected = ref(null);
const proposalLoading = ref(false);

function formatMoney(v) {
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function simulate() {
    if (loading.value) return;
    loading.value = true;
    result.value = null;
    selected.value = null;

    window.axios.post(route('finance.budgets.simulate', props.budget.id), {
        cpf: cpf.value,
        installments: installments.value,
    })
        .then(res => { result.value = res.data; })
        .catch(err => {
            result.value = {
                simulations: [],
                failures: [{ message: err.response?.data?.message ?? 'Falha na simulação.' }],
                compared: [],
            };
        })
        .finally(() => { loading.value = false; });
}

function submitProposal() {
    if (!selected.value || proposalLoading.value) return;
    proposalLoading.value = true;

    const p = props.budget.patient;

    window.axios.post(route('finance.budgets.proposals', props.budget.id), {
        provider: selected.value.provider,
        name: p?.full_name ?? '',
        cpf: cpf.value,
        phone: p?.telefone ?? '',
        email: p?.email ?? '',
        installments: selected.value.installments,
        simulation_external_id: selected.value.external_id,
    })
        .then(() => {
            emit('proposal-submitted');
            emit('close');
        })
        .finally(() => { proposalLoading.value = false; });
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show"
                 class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                 @click.self="emit('close')">
                <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Simular Financiamento</h2>
                            <p class="text-xs text-slate-500">
                                {{ budget?.patient?.full_name }} — {{ formatMoney(budget?.total) }}
                            </p>
                        </div>
                        <button @click="emit('close')" class="text-slate-400">✕</button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-slate-500">CPF do paciente</label>
                                <input v-model="cpf" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Parcelas desejadas</label>
                                <select v-model="installments" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                                    <option :value="6">6x</option>
                                    <option :value="12">12x</option>
                                    <option :value="18">18x</option>
                                    <option :value="24">24x</option>
                                    <option :value="36">36x</option>
                                </select>
                            </div>
                        </div>

                        <button @click="simulate" :disabled="loading || !cpf"
                                class="w-full py-2.5 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700 disabled:opacity-50">
                            {{ loading ? 'Consultando instituições...' : 'Simular financiamento' }}
                        </button>

                        <div v-if="result?.failures?.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 space-y-1">
                            <p v-for="(f, i) in result.failures" :key="i">{{ f.message }}</p>
                        </div>

                        <div v-if="result?.compared?.length" class="space-y-2">
                            <h3 class="text-xs font-semibold uppercase text-slate-400">Comparação de propostas</h3>
                            <div v-for="sim in result.compared" :key="sim.provider + sim.installments"
                                 @click="selected = sim"
                                 class="rounded-xl border p-4 cursor-pointer transition-colors"
                                 :class="selected === sim ? 'border-teal-400 bg-teal-50' : 'border-slate-200 hover:border-slate-300'">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-sm">{{ sim.provider_name }}</p>
                                        <p class="text-xs text-slate-500">{{ sim.installments }}x de {{ formatMoney(sim.installment_value) }}</p>
                                    </div>
                                    <span v-if="sim.rank === 1" class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">Melhor CET</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 mt-2 text-xs text-slate-600">
                                    <span>Total: {{ formatMoney(sim.total_amount) }}</span>
                                    <span>CET: {{ sim.cet }}%</span>
                                    <span>Juros: {{ sim.interest_rate }}%</span>
                                </div>
                            </div>
                        </div>

                        <button v-if="selected"
                                @click="submitProposal"
                                :disabled="proposalLoading"
                                class="w-full py-2.5 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50">
                            {{ proposalLoading ? 'Enviando proposta...' : 'Solicitar proposta com ' + selected.provider_name }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>