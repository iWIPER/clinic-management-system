<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { watch, onMounted, onUnmounted } from 'vue'
import { navigation } from '@/Navigation/config'
import { useSidebarState } from '@/composables/useSidebarState'
import SidebarSection from './SidebarSection.vue'
import SidebarItem from './SidebarItem.vue'

// Superfície estrutural da navegação — logo e navegação formam uma
// composição contínua (sem linha divisória entre eles). Funções globais
// (tarefas, notificações, perfil, clínica) não vivem aqui: ficam na
// TopIsland.vue, para não duplicar controles entre sidebar e ilha.
const page = usePage()
const { mobileOpen, closeMobile } = useSidebarState()

// Fecha o drawer mobile automaticamente ao navegar para outra tela.
watch(() => page.url, () => closeMobile())

// Escape precisa fechar o drawer não importa onde o foco esteja — o botão
// que abre o drawer mora na TopIsland, fora desta árvore, então um
// @keydown só no <aside> nunca recebe o evento nesse caso. Ouve em
// document (mesmo padrão do NavbarDropdown.vue) em vez de depender de
// bubbling a partir de dentro do próprio componente.
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
        aria-label="Navegação principal"
    >
        <!-- Identidade do PRODUTO (Wildental) — não confundir com a
             identidade da CLÍNICA (essa vive na ilha de contexto da
             TopIsland, ver TopIsland.vue, que reaproveita ClinicLogo.vue).
             A logo já traz o wordmark embutido na própria imagem — por
             isso nenhum texto "Wildental" ao lado; sem card/borda/fundo
             próprio, só a imagem no canto superior esquerdo. -->
        <div class="flex items-center px-4 pb-1 pt-5">
            <Link
                :href="route('dashboard')"
                class="inline-flex w-full rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-2"
            >
                <img src="/images/brand/logo-geral.png" alt="Wildental" class="h-auto w-full object-contain" />
            </Link>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-2 pb-3 pt-6">
            <SidebarSection
                v-for="(group, index) in navigation"
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
                    :badge="item.badge"
                    :emphasized="item.emphasized"
                    @navigate="closeMobile"
                />
            </SidebarSection>
        </div>
    </aside>
</template>
