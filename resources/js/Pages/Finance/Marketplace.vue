<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import IntegrationTestModal from '@/Components/Financial/IntegrationTestModal.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    institutions: Array,
});

const forms = ref({});
props.institutions.forEach(inst => {
    forms.value[inst.slug] = useForm({
        provider: inst.slug,
        environment: inst.environment ?? 'sandbox',
        client_id: '',
        client_secret: '',
    });
});

const testReport = ref(null);
const showTestModal = ref(false);
const testingProvider = ref(null);

function save(inst) {
    forms.value[inst.slug].post(route('finance.marketplace.store'), {
        preserveScroll: true,
    });
}

function testIntegration(inst) {
    testingProvider.value = inst.slug;
    testReport.value = null;
    showTestModal.value = true;

    window.axios.post(route('finance.marketplace.test', inst.slug))
        .then(res => { testReport.value = res.data; })
        .catch(err => {
            testReport.value = err.response?.data ?? {
                success: false,
                health_score: 0,
                checks: [{ key: 'error', label: 'Comunicação', status: 'fail', message: 'Falha na requisição.' }],
                recommendations: ['Verifique sua conexão e tente novamente.'],
            };
        });
}

function statusBadge(status) {
    if (status === 'active') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (status === 'error' || status === 'circuit_open') return 'bg-red-50 text-red-700 border-red-200';
    return 'bg-slate-50 text-slate-600 border-slate-200';
}
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Marketplace Financeiro</h1>
                <p class="text-sm text-slate-500 mt-1">Conecte instituições de crédito para oferecer financiamento aos pacientes.</p>
            </div>
            <Link :href="route('finance.index')" class="text-sm text-teal-600 hover:text-teal-800">← Voltar ao Financeiro</Link>
        </div>

        <div class="grid gap-5">
            <div v-for="inst in institutions" :key="inst.slug"
                 class="bg-white rounded-2xl border p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ inst.name }}</h2>
                        <p class="text-xs text-slate-500">{{ inst.product }}</p>
                        <p class="text-sm text-slate-600 mt-2">{{ inst.description }}</p>
                    </div>
                    <span class="shrink-0 text-xs px-2.5 py-1 rounded-full border font-medium"
                          :class="statusBadge(inst.status)">
                        {{ inst.connected ? 'Conectado' : inst.status === 'error' ? 'Erro' : 'Não configurado' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-xs font-medium text-slate-500">Ambiente</label>
                        <select v-model="forms[inst.slug].environment"
                                class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="sandbox">Sandbox</option>
                            <option value="production">Produção</option>
                        </select>
                        <InputError :message="forms[inst.slug].errors.environment" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">Client ID</label>
                        <input v-model="forms[inst.slug].client_id" type="text"
                               :placeholder="inst.has_credentials ? '•••••••• (mantém atual se vazio)' : 'Client ID'"
                               class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" />
                        <InputError :message="forms[inst.slug].errors.client_id" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-medium text-slate-500">Client Secret</label>
                        <input v-model="forms[inst.slug].client_secret" type="password"
                               placeholder="Client Secret"
                               class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" />
                        <InputError :message="forms[inst.slug].errors.client_secret" />
                    </div>
                </div>

                <p v-if="inst.webhook_url" class="text-[11px] text-slate-400 mb-4 break-all">
                    Webhook: {{ inst.webhook_url }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <button @click="save(inst)"
                            :disabled="forms[inst.slug].processing"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50">
                        Salvar credenciais
                    </button>
                    <button @click="testIntegration(inst)"
                            class="px-4 py-2 rounded-lg border border-teal-200 bg-teal-50 text-teal-800 text-sm font-medium hover:bg-teal-100">
                        Testar Integração
                    </button>
                </div>
            </div>
        </div>

        <IntegrationTestModal
            :show="showTestModal"
            :report="testReport"
            :provider="testingProvider"
            @close="showTestModal = false"
        />
    </AppLayout>
</template>