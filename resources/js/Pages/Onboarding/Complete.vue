<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    clinicName: String,
    chairsCount: Number,
});

const chairsLabel = computed(() =>
    `${props.chairsCount} cadeira${props.chairsCount === 1 ? '' : 's'} configurada${props.chairsCount === 1 ? '' : 's'}`);

const checklist = computed(() => ([
    'Clínica configurada',
    chairsLabel.value,
    'Agenda preparada',
    'Estrutura inicial concluída',
]));
</script>

<template>
    <div class="max-w-lg mx-auto py-16 px-4 text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
            <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-2xl sm:text-3xl font-semibold text-slate-900">Pronto! Sua clínica está preparada.</h1>
        <p class="text-slate-500 mt-3 leading-relaxed">
            Agora você já pode começar a organizar sua rotina, sua agenda e seus atendimentos
            em um só lugar.
        </p>

        <div class="mt-8 bg-white border rounded-2xl p-6 text-left space-y-3">
            <div v-for="item in checklist" :key="item" class="flex items-center gap-3">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500">
                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                <span class="text-sm font-medium text-slate-700">{{ item }}</span>
            </div>
        </div>

        <Link :href="route('dashboard')"
              class="mt-8 inline-flex w-full items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3.5 rounded-lg font-medium transition-colors">
            Começar a usar o ClinicFlow
        </Link>

        <Link :href="route('onboarding.invite-team')" class="mt-4 inline-block text-sm text-slate-500 hover:text-slate-700 transition-colors">
            Convidar minha equipe agora
        </Link>
    </div>
</template>
