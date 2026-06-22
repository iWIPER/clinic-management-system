<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-6 text-center">
        <div class="max-w-md">
            <h1 class="text-5xl font-bold tracking-tight text-slate-900">Gestão Clínicas</h1>
            <p class="mt-4 text-xl text-slate-600">
                Do agendamento ao recebimento, a clínica inteira numa plataforma só.
            </p>

            <div class="mt-8 flex gap-4 justify-center">
                <Link :href="route('login')" class="px-6 py-3 bg-slate-900 text-white rounded-lg font-medium">
                    Entrar
                </Link>
                <Link :href="route('register')" class="px-6 py-3 border border-slate-300 rounded-lg font-medium">
                    Criar conta grátis
                </Link>
            </div>

            <p class="mt-10 text-xs text-slate-500">
                MVP enxuto • Multi-tenant • LGPD • Foco em Odontologia
            </p>
        </div>
    </div>
</template>
