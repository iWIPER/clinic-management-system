<script setup>
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    token: '',
});

const submit = () => {
    form.post(route('onboarding.join-invite'));
};
</script>

<template>
    <div class="max-w-md mx-auto mt-12 px-4">
        <h1 class="text-3xl font-semibold mb-2">Entrar em uma clínica</h1>
        <p class="text-slate-600 mb-6">Cole o token do convite ou verifique seu email.</p>

        <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-4">
            <div>
                <label class="block mb-1 text-sm">Token do convite</label>
                <input v-model="form.token" type="text" class="w-full border p-3 rounded-lg font-mono" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required />
                <InputError :message="form.errors.token" />
            </div>

            <button type="submit" class="w-full bg-emerald-600 text-white py-3 rounded-lg" :disabled="form.processing">
                Aceitar convite e entrar
            </button>
        </form>
    </div>
</template>
