<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <nav class="clinic-header border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center gap-x-3">
                        <Link :href="route('dashboard')" class="font-semibold text-xl text-slate-800">
                            Gestão Clínicas
                        </Link>
                        <div class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700 font-medium" v-if="$page.props.clinic">
                            {{ $page.props.clinic.name }}
                        </div>
                    </div>

                    <div class="flex items-center gap-x-4 text-sm">
                        <!-- Menu principal MVP -->
                        <Link :href="route('patients.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Pacientes</Link>
                        <Link :href="route('appointments.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Agenda</Link>
                        <Link :href="route('consultations.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Consultas</Link>
                        <Link :href="route('treatments.index')" class="hover:text-slate-600">Procedimentos</Link>
                        <Link :href="route('inventory.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Estoque</Link>
                        <Link :href="route('finance.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Financeiro</Link>

                        <div class="border-l pl-4 flex items-center gap-x-2">
                            <span class="text-slate-500">{{ $page.props.auth.user.name }}</span>
                            <Link :href="route('logout')" method="post" class="text-red-600 hover:text-red-700">Sair</Link>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <slot />
        </main>
    </div>
</template>
