<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { UserCircleIcon } from '@heroicons/vue/24/outline'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'

// mode="clinic" (usado pela TopIsland): Meu perfil, Logs de acesso,
// Backoffice (se super admin), Sair.
// mode="admin" (usado pela Topbar do Backoffice): Meu perfil, Sair — sem
// Backoffice (redundante, já se está nele) nem Logs.
const props = defineProps({
    mode: { type: String, default: 'clinic', validator: (v) => ['clinic', 'admin'].includes(v) },
})

const page = usePage()
const isSystemAdmin = computed(() => page.props.auth?.isSystemAdmin ?? false)
</script>

<template>
    <NavbarDropdown width="w-52">
        <template #trigger="{ open }">
            <button
                type="button"
                :aria-expanded="open"
                aria-haspopup="menu"
                aria-label="Menu do usuário"
                class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium leading-none tracking-normal text-slate-600 antialiased transition-all duration-[180ms] ease hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.98]"
            >
                <UserCircleIcon class="h-5 w-5 shrink-0 text-slate-400 sm:hidden" />
                <span class="hidden max-w-[9rem] truncate sm:inline">{{ page.props.auth.user.name }}</span>
                <svg
                    class="h-3 w-3 shrink-0 text-slate-400 transition-transform duration-[180ms] ease"
                    :class="open ? 'rotate-180' : ''"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </template>

        <template #default="{ close }">
            <div class="py-1">
                <NavbarDropdownItem :href="route('profile.edit')" @click="close">Meu perfil</NavbarDropdownItem>
                <template v-if="mode === 'clinic'">
                    <NavbarDropdownItem :href="route('access-logs.index')" @click="close">Logs de acesso</NavbarDropdownItem>
                    <NavbarDropdownItem v-if="isSystemAdmin" :href="route('admin.index')" @click="close">Backoffice</NavbarDropdownItem>
                </template>
                <div class="my-1 border-t border-slate-100" />
                <NavbarDropdownItem :href="route('logout')" method="post" danger @click="close">Sair</NavbarDropdownItem>
            </div>
        </template>
    </NavbarDropdown>
</template>
