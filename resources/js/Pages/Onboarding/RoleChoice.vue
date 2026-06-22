<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue'; // ou um layout simplificado

const form = useForm({
    role_type: '',
});

const select = (type) => {
    form.role_type = type;
    form.post(route('onboarding.choose-role'));
};
</script>

<template>
    <div class="max-w-lg mx-auto mt-12">
        <h1 class="text-3xl font-semibold mb-2">Bem-vindo!</h1>
        <p class="text-slate-600 mb-8">Como você quer começar?</p>

        <div class="space-y-4">
            <button 
                @click="select('owner')"
                class="w-full p-6 border-2 rounded-2xl text-left hover:border-emerald-500 transition group"
            >
                <div class="font-semibold text-lg group-hover:text-emerald-600">Sou proprietário de uma clínica</div>
                <div class="text-sm text-slate-500 mt-1">Vou criar minha clínica e gerenciar a equipe.</div>
            </button>

            <button 
                @click="select('guest')"
                class="w-full p-6 border-2 rounded-2xl text-left hover:border-emerald-500 transition group"
            >
                <div class="font-semibold text-lg group-hover:text-emerald-600">Fui convidado para uma clínica</div>
                <div class="text-sm text-slate-500 mt-1">Já recebi um convite por email.</div>
            </button>
        </div>

        <p class="text-xs text-slate-400 mt-8 text-center">
            Você poderá mudar isso depois ou ser convidado para outras clínicas.
        </p>
    </div>
</template>
