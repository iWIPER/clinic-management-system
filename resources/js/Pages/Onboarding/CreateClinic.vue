<script setup>
import { computed, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { trackEvent } from '@/lib/analytics';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    plans: Array,
    maxChairs: { type: Number, default: 6 },
});

const TOTAL_STEPS = 4;
const step = ref(1);

const form = useForm({
    onboarding_stage: '',
    onboarding_current_system: '',
    name: '',
    type: 'odontologia',
    cnpj: '',
    plan_slug: 'start-gratis',
    chairs_count: 1,
});

const stageOptions = [
    { value: 'new', label: 'Ainda não comecei a atender', hint: 'A clínica está em preparação.' },
    { value: 'under_1y', label: 'Comecei a atender há menos de 1 ano', hint: 'Estamos no início da operação.' },
    { value: '1_to_5y', label: 'Atendo há entre 1 e 5 anos', hint: 'Já temos uma rotina estabelecida.' },
    { value: 'over_5y', label: 'Atendo há mais de 5 anos', hint: 'Clínica consolidada no mercado.' },
];

const systemOptions = [
    { value: 'paper_or_calendar', label: 'Agenda de papel ou Google Agenda/Calendar', hint: 'Ainda não uso um sistema.' },
    { value: 'spreadsheet', label: 'Uso uma planilha', hint: 'Controle manual em Excel/Sheets.' },
    { value: 'other_system', label: 'Já uso outro sistema de gestão', hint: 'Vou migrar meus dados pra cá.' },
];

const chairOptions = computed(() => Array.from({ length: props.maxChairs }, (_, i) => i + 1));

const canAdvance = computed(() => {
    if (step.value === 1) return !!form.onboarding_stage;
    if (step.value === 2) return !!form.onboarding_current_system;
    if (step.value === 3) return !!form.name && !!form.type && !!form.plan_slug;
    if (step.value === 4) return !!form.chairs_count;
    return true;
});

const next = () => { if (canAdvance.value && step.value < TOTAL_STEPS) step.value++; };
const back = () => { if (step.value > 1) step.value--; };

const submit = () => {
    form.post(route('onboarding.create-clinic'), {
        onSuccess: () => trackEvent('trial_iniciado'),
    });
};
</script>

<template>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-medium text-slate-400">Etapa {{ step }} de {{ TOTAL_STEPS }}</p>
                <div class="flex gap-1.5">
                    <span v-for="n in TOTAL_STEPS" :key="n"
                          class="h-1.5 w-6 rounded-full transition-colors"
                          :class="n <= step ? 'bg-emerald-500' : 'bg-slate-200'" />
                </div>
            </div>
            <h1 class="text-2xl sm:text-3xl font-semibold text-slate-900">Vamos configurar sua clínica</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white p-6 sm:p-8 rounded-2xl border space-y-6">

            <!-- Etapa 1 — Contexto da clínica -->
            <div v-if="step === 1" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Qual desses cenários representa sua clínica hoje?</h2>
                    <p class="text-sm text-slate-500 mt-1">Isso nos ajuda a preparar a melhor experiência pra você.</p>
                </div>
                <div class="space-y-2.5">
                    <button v-for="opt in stageOptions" :key="opt.value" type="button"
                            @click="form.onboarding_stage = opt.value"
                            class="w-full p-4 border-2 rounded-xl text-left transition-colors"
                            :class="form.onboarding_stage === opt.value ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-slate-300'">
                        <div class="font-medium text-slate-800">{{ opt.label }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">{{ opt.hint }}</div>
                    </button>
                </div>
                <InputError :message="form.errors.onboarding_stage" />
            </div>

            <!-- Etapa 2 — Experiência / sistema atual -->
            <div v-else-if="step === 2" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Como você organiza sua agenda hoje?</h2>
                    <p class="text-sm text-slate-500 mt-1">Vamos usar isso pra facilitar sua migração.</p>
                </div>
                <div class="space-y-2.5">
                    <button v-for="opt in systemOptions" :key="opt.value" type="button"
                            @click="form.onboarding_current_system = opt.value"
                            class="w-full p-4 border-2 rounded-xl text-left transition-colors"
                            :class="form.onboarding_current_system === opt.value ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-slate-300'">
                        <div class="font-medium text-slate-800">{{ opt.label }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">{{ opt.hint }}</div>
                    </button>
                </div>
                <InputError :message="form.errors.onboarding_current_system" />
            </div>

            <!-- Etapa 3 — Estrutura da clínica -->
            <div v-else-if="step === 3" class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Conte um pouco sobre sua clínica</h2>
                    <p class="text-sm text-slate-500 mt-1">Vamos configurar sua primeira unidade.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Nome da Clínica *</label>
                    <input v-model="form.name" type="text" required class="w-full border rounded-lg p-3" placeholder="Clínica Sorriso Perfeito" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo *</label>
                        <select v-model="form.type" class="w-full border rounded-lg p-3">
                            <option value="odontologia">Odontologia</option>
                            <option value="medicina">Medicina</option>
                            <option value="estetica">Estética</option>
                            <option value="outros">Outros</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">CNPJ</label>
                        <input v-model="form.cnpj" type="text" class="w-full border rounded-lg p-3" placeholder="00.000.000/0001-00" />
                        <InputError :message="form.errors.cnpj" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Plano inicial</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label v-for="plan in plans" :key="plan.slug" class="border rounded-xl p-4 cursor-pointer" :class="{ 'border-emerald-500 ring-1 ring-emerald-200': form.plan_slug === plan.slug }">
                            <input type="radio" v-model="form.plan_slug" :value="plan.slug" class="sr-only" />
                            <div class="font-semibold">{{ plan.name }}</div>
                            <div class="text-sm text-slate-500" v-if="plan.is_free">Grátis</div>
                            <div class="text-sm text-slate-500" v-else>R$ {{ (plan.price_monthly_cents / 100).toFixed(0) }}/mês</div>
                        </label>
                    </div>
                    <InputError :message="form.errors.plan_slug" />
                </div>
            </div>

            <!-- Etapa 4 — Quantidade de cadeiras. Lista vertical com altura
                 máxima (~3 opções visíveis) + scroll interno — não deixa o
                 card crescer pra caber as 6 opções de uma vez, mantendo
                 cabeçalho e botões de navegação sempre acessíveis. -->
            <div v-else-if="step === 4" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Quantas cadeiras sua clínica utiliza para atendimento?</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Vamos preparar sua Agenda automaticamente com uma cadeira pra cada uma —
                        você pode ajustar isso depois em Configurações.
                    </p>
                </div>
                <div class="max-h-[184px] overflow-y-auto pr-1 space-y-2">
                    <button v-for="n in chairOptions" :key="n" type="button"
                            @click="form.chairs_count = n"
                            class="w-full flex items-center gap-3 p-3.5 border-2 rounded-xl text-left transition-colors"
                            :class="form.chairs_count === n ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-slate-300'">
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2"
                              :class="form.chairs_count === n ? 'border-emerald-500' : 'border-slate-300'">
                            <span v-if="form.chairs_count === n" class="h-2 w-2 rounded-full bg-emerald-500" />
                        </span>
                        <span class="font-medium text-slate-800">{{ n }} cadeira{{ n === 1 ? '' : 's' }}</span>
                    </button>
                </div>
                <InputError :message="form.errors.chairs_count" />
            </div>

            <!-- Navegação -->
            <div class="pt-4 flex items-center justify-between gap-3 border-t">
                <button v-if="step > 1" type="button" @click="back" class="px-4 py-2.5 text-sm text-slate-600 hover:text-slate-800 transition-colors">
                    ← Voltar
                </button>
                <Link v-else :href="route('dashboard')" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 transition-colors">Pular por enquanto</Link>

                <button v-if="step < TOTAL_STEPS" type="button" @click="next" :disabled="!canAdvance"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-lg font-medium transition-colors">
                    Próxima →
                </button>
                <button v-else type="submit" :disabled="form.processing || !canAdvance"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white px-8 py-2.5 rounded-lg font-medium transition-colors">
                    {{ form.processing ? 'Criando...' : 'Concluir' }}
                </button>
            </div>
        </form>
    </div>
</template>
