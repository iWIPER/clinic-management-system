<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    active: { type: String, required: true },
})

// Indicador de scroll — a barra já rolava horizontalmente em telas
// estreitas (overflow-x-auto), mas sem nenhuma pista visual de que havia
// mais abas fora da tela (achado da auditoria: usuário não descobre
// sozinho). Um fade discreto nas bordas, só quando há de fato mais
// conteúdo pra rolar naquela direção — em desktop, onde tudo cabe, os
// dois fades ficam sempre ocultos e a barra continua idêntica a hoje.
const navRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

function updateScrollState() {
    const el = navRef.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 1
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1
}

onMounted(() => {
    updateScrollState()
    navRef.value?.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', updateScrollState)
})
onUnmounted(() => {
    navRef.value?.removeEventListener('scroll', updateScrollState)
    window.removeEventListener('resize', updateScrollState)
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
    <div class="relative border-b border-slate-200">
        <nav ref="navRef" class="flex items-center gap-5 overflow-x-auto no-scrollbar">
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

        <!-- Fades de "tem mais abas pra esse lado" — só aparecem quando dá
             pra rolar naquela direção (v-show liga/desliga a opacidade,
             não o layout, pra não causar reflow ao rolar). Cor do fade
             casa com o canvas da página (slate-100), não branco — a barra
             de abas fica direto sobre o canvas, sem card por trás. -->
        <div v-show="canScrollLeft" class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-slate-100 to-transparent transition-opacity duration-150" />
        <div v-show="canScrollRight" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-slate-100 to-transparent transition-opacity duration-150" />
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
