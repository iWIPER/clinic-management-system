<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { watch, onMounted, onUnmounted } from 'vue'
import { adminNavigation } from '@/Navigation/adminConfig'
import { useSidebarState } from '@/composables/useSidebarState'
import SidebarSection from './SidebarSection.vue'
import SidebarItem from './SidebarItem.vue'

// Espelha Sidebar.vue (shell clínico) estrutural e visualmente — mesma
// largura, mesmo comportamento de drawer mobile, mesmos subcomponentes
// (SidebarSection/SidebarItem, sem duplicar nada deles). Só a árvore de
// navegação muda (adminNavigation, só módulos de plataforma) e o logo
// aponta pro Backoffice em vez do dashboard clínico. useSidebarState é o
// mesmo estado compartilhado por módulo — seguro reusar aqui porque este
// componente e o Sidebar.vue clínico nunca ficam montados ao mesmo tempo
// (são shells de páginas Inertia mutuamente exclusivas).
const page = usePage()
const { mobileOpen, closeMobile } = useSidebarState()

watch(() => page.url, () => closeMobile())

function onKeydown(event) {
    if (event.key === 'Escape') closeMobile()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-900/30 lg:hidden"
            @click="closeMobile"
        />
    </Transition>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-60 shrink-0 flex-col bg-white shadow-[2px_0_6px_-2px_rgba(15,23,42,0.06)] transition-transform duration-200 ease-out lg:static lg:translate-x-0"
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        role="navigation"
        aria-label="Navegação do Backoffice"
    >
        <!-- Logo do produto, idêntica à sidebar clínica, + tag "ADMIN" pra
             deixar o contexto administrativo claro de cara (não é uma
             clínica, é a plataforma). -->
        <div class="flex items-center gap-2 px-4 pb-1 pt-5">
            <Link
                :href="route('admin.index')"
                class="inline-flex flex-1 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-2"
            >
                <img src="/images/brand/logo-geral.png" alt="Wildental" class="h-auto w-full object-contain" />
            </Link>
            <span class="shrink-0 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700">
                Admin
            </span>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-2 pb-3 pt-6">
            <SidebarSection
                v-for="(group, index) in adminNavigation"
                :key="index"
                :label="group.section"
                :divider="group.divider"
            >
                <SidebarItem
                    v-for="item in group.items"
                    :key="item.route"
                    :label="item.label"
                    :route-name="item.route"
                    :match="item.match"
                    :icon="item.icon"
                    @navigate="closeMobile"
                />
            </SidebarSection>
        </div>
    </aside>
</template>
