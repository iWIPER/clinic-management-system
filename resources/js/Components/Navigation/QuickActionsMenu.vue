<script setup>
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { BoltIcon, ChevronDownIcon, PlusIcon, MinusIcon } from '@heroicons/vue/24/outline'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'
import { useSidebarState } from '@/composables/useSidebarState'
import { STANDARD_QUICK_ACTIONS, findQuickAction } from '@/Navigation/quickActions'
import QuickActionsCustomizeModal from './QuickActionsCustomizeModal.vue'
import RemoveQuickActionModal from './RemoveQuickActionModal.vue'

// Pílula mais à esquerda da TopIsland — substitui o antigo contexto de
// clínica ("Lelis Care"). Carrega também o trigger do drawer mobile
// (mesma posição que o contexto de clínica ocupava antes), já que
// continua sendo a pílula mais à esquerda.
//
// Conceito assumido como "Atalhos": 2 atalhos padrão fixos + até 2
// personalizados. Adicionar acontece pelo "+" ao lado do rótulo "Ações
// padrão" (abre o modal de personalização); remover acontece direto no
// menu, por linha, via MinusIcon + confirmação — sem precisar abrir o
// modal só pra isso.
const page = usePage()
const quickActionKeys = computed(() => page.props.quickActions ?? [])
const customActions = computed(() => quickActionKeys.value.map(findQuickAction).filter(Boolean))

const { toggleMobile } = useSidebarState()
const showCustomizeModal = ref(false)
const actionPendingRemoval = ref(null)

function requestRemoval(action, close) {
    actionPendingRemoval.value = action
    close()
}
</script>

<template>
    <div class="flex h-11 items-center gap-1 rounded-full border border-slate-200/80 bg-white pl-1.5 pr-3 shadow-sm">
        <button
            type="button"
            aria-label="Abrir menu de navegação"
            class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-500 transition-colors duration-[180ms] ease hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.97] lg:hidden"
            @click="toggleMobile"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </button>

        <NavbarDropdown width="w-64" align="left">
            <template #trigger="{ open }">
                <button
                    type="button"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    aria-label="Atalhos"
                    class="flex items-center gap-1.5 rounded-full px-1.5 py-1 text-sm font-medium text-slate-700 transition-colors duration-[180ms] ease hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35"
                >
                    <BoltIcon class="h-[18px] w-[18px] text-emerald-600" stroke-width="2" />
                    <span>Atalhos</span>
                    <ChevronDownIcon
                        class="h-3.5 w-3.5 text-slate-400 transition-transform duration-[180ms] ease"
                        :class="open ? 'rotate-180' : ''"
                    />
                </button>
            </template>

            <template #default="{ close }">
                <div class="py-1">
                    <div class="flex items-center justify-between pl-3.5 pr-2 pb-1 pt-1.5">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Ações padrão</span>
                        <button
                            type="button"
                            aria-label="Personalizar atalhos"
                            class="rounded-md p-1 text-slate-400 transition-colors duration-[180ms] ease hover:bg-slate-100 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35"
                            @click="showCustomizeModal = true; close()"
                        >
                            <PlusIcon class="h-3.5 w-3.5" stroke-width="2.5" />
                        </button>
                    </div>
                    <NavbarDropdownItem
                        v-for="action in STANDARD_QUICK_ACTIONS"
                        :key="action.key"
                        :href="route(action.route)"
                        @click="close"
                    >
                        <component :is="action.icon" class="mr-2.5 h-4 w-4 shrink-0 text-slate-400" stroke-width="2" />
                        {{ action.label }}
                    </NavbarDropdownItem>

                    <div class="my-1 border-t border-slate-100" />

                    <!-- Atalhos personalizados já adicionados: linha executa (Link),
                         MinusIcon remove (com confirmação) — nunca uma linha vazia
                         representando um slot ainda livre. -->
                    <div v-for="action in customActions" :key="action.key" class="flex items-center">
                        <Link
                            :href="route(action.route)"
                            class="flex flex-1 items-center px-3.5 py-2 text-sm font-medium leading-none tracking-normal text-slate-700 antialiased transition-colors duration-[180ms] ease hover:bg-slate-50 hover:text-slate-900"
                            @click="close"
                        >
                            <component :is="action.icon" class="mr-2.5 h-4 w-4 shrink-0 text-slate-400" stroke-width="2" />
                            {{ action.label }}
                        </Link>
                        <button
                            type="button"
                            :aria-label="`Remover atalho ${action.label}`"
                            class="mr-2 flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-slate-400 transition-colors duration-[180ms] ease hover:bg-slate-100 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/35"
                            @click.stop="requestRemoval(action, close)"
                        >
                            <MinusIcon class="h-2.5 w-2.5" stroke-width="3" />
                        </button>
                    </div>
                </div>
            </template>
        </NavbarDropdown>
    </div>

    <QuickActionsCustomizeModal
        :show="showCustomizeModal"
        :current-actions="quickActionKeys"
        @close="showCustomizeModal = false"
    />

    <RemoveQuickActionModal
        :show="!!actionPendingRemoval"
        :action="actionPendingRemoval"
        :current-actions="quickActionKeys"
        @close="actionPendingRemoval = null"
    />
</template>
