<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { trackEvent } from '@/lib/analytics';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    plans: Array,
});

const form = useForm({
    name: '',
    type: 'odontologia',
    cnpj: '',
    plan_slug: 'start-gratis',
});

const submit = () => {
    form.post(route('onboarding.create-clinic'), {
        onSuccess: () => trackEvent('trial_iniciado'),
    });
};
</script>

<template>
    <div class="max-w-2xl mx-auto py-8">
        <h1 class="text-3xl font-semibold mb-1">Crie sua clínica</h1>
        <p class="text-slate-600 mb-8">Vamos configurar sua primeira unidade.</p>

        <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-6">
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

            <div class="pt-4 flex gap-3">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-lg font-medium" :disabled="form.processing">
                    Criar Clínica
                </button>
                <Link :href="route('dashboard')" class="px-6 py-3 text-slate-600">Pular por enquanto</Link>
            </div>
        </form>
    </div>
</template>
