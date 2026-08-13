<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    active: { type: String, required: true },
})

// Abas "internas" — telas que já vivem sob /clinic-settings e agora
// ganham essa barra compartilhada em vez de header próprio.
const tabs = [
    { id: 'general', label: 'Geral', route: 'clinic-settings.edit' },
    { id: 'chairs', label: 'Cadeiras', route: 'clinic-settings.chairs' },
    { id: 'agendas', label: 'Agendas', route: 'clinic-settings.agendas' },
    { id: 'convenios', label: 'Convênios', route: 'clinic-settings.convenios.index' },
    { id: 'documents-config', label: 'Config. de Documentos', route: 'clinic-settings.documents.edit' },
]

// Áreas correlatas que continuam sendo páginas/rotas independentes — visual
// deliberadamente diferente das abas (sem borda ativa/estado de aba), pra
// não parecer uma quebra acidental da mesma navegação.
const externalLinks = [
    { id: 'team', label: 'Equipe', route: 'team.index' },
    { id: 'anamnesis', label: 'Modelos de Anamnese', route: 'anamnesis-templates.index' },
    { id: 'documents', label: 'Documentos', route: 'documents.index' },
]
</script>

<template>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Configurações da Clínica</h1>
    <p class="text-sm text-slate-500 mt-1 mb-5">Gerencie os dados, recursos e áreas da sua clínica.</p>

    <div class="border-b border-slate-200">
        <nav class="flex items-center gap-5 overflow-x-auto no-scrollbar">
            <Link v-for="tab in tabs" :key="tab.id" :href="route(tab.route)"
                  class="shrink-0 whitespace-nowrap px-0.5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
                  :class="active === tab.id
                    ? 'border-emerald-600 text-emerald-700'
                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                {{ tab.label }}
            </Link>

            <span class="shrink-0 h-4 w-px bg-slate-200 mx-1" />

            <Link v-for="link in externalLinks" :key="link.id" :href="route(link.route)"
                  class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 py-2.5 text-sm font-medium text-slate-400 hover:text-emerald-700 transition-colors">
                {{ link.label }}
                <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </Link>
        </nav>
    </div>
</div>
</template>

<style scoped>
.no-scrollbar {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
