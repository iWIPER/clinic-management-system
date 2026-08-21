<script setup>
import { BoltIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'
import { useSidebarState } from '@/composables/useSidebarState'
import { ADMIN_QUICK_ACTIONS } from '@/Navigation/adminQuickActions'

// Versão administrativa de QuickActionsMenu.vue — mesma pílula/dropdown
// visual, mas SEM personalização (add/remove/modal): exatamente os 2
// atalhos fixos de ADMIN_QUICK_ACTIONS, sempre os mesmos, não dependem de
// clínica nem de preferences do usuário.
const { toggleMobile } = useSidebarState()
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
                    <NavbarDropdownItem
                        v-for="action in ADMIN_QUICK_ACTIONS"
                        :key="action.key"
                        :href="route(action.route)"
                        @click="close"
                    >
                        <component :is="action.icon" class="mr-2.5 h-4 w-4 shrink-0 text-slate-400" stroke-width="2" />
                        {{ action.label }}
                    </NavbarDropdownItem>
                </div>
            </template>
        </NavbarDropdown>
    </div>
</template>
