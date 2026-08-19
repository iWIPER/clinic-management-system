import { ref } from 'vue'

// Estado em escopo de módulo (não dentro da função): módulos ES são
// cacheados por import, então toda chamada de useSidebarState() nesta mesma
// carga de página compartilha os MESMOS refs — Sidebar.vue, TopIsland.vue
// (botão de abrir o drawer) e qualquer outro consumidor futuro ficam
// sincronizados sem precisar de props/emits entre eles.
//
// Só existe estado de drawer mobile — não há mais collapse/rail no desktop
// (removido deliberadamente: a sidebar já é estreita o suficiente e um
// toggle isolado não se sustentou visualmente na revisão anterior).
const mobileOpen = ref(false)

function toggleMobile() {
    mobileOpen.value = !mobileOpen.value
}

function closeMobile() {
    mobileOpen.value = false
}

export function useSidebarState() {
    return { mobileOpen, toggleMobile, closeMobile }
}
