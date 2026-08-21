<script setup>
import { usePage, Link } from '@inertiajs/vue3'
import { ShieldCheckIcon, BuildingOffice2Icon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import AdminQuickActionsMenu from './AdminQuickActionsMenu.vue'
import UserMenu from './UserMenu.vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'

// Espelha TopIsland.vue (shell clínico) — mesma família de "pílulas"
// flutuantes (h-11, rounded-full, border-slate-200/80, bg-white,
// shadow-sm), só trocando o conteúdo por equivalentes administrativos:
//
// - QuickActionsMenu clínico -> AdminQuickActionsMenu (2 atalhos fixos,
//   sem depender de clínica/preferences);
// - pílula de contexto (logo+nome da clínica, via currentClinic) -> pílula
//   estática "Admin de sistema" — o Backoffice em si nunca tem uma clínica
//   ativa (currentClinic continua null aqui), mesmo quando o admin tem
//   vínculo real com alguma;
// - Tarefas/NotificationCenter ficam de fora: são recursos clínicos
//   (tasks, anamneses pendentes), sem equivalente administrativo — incluir
//   dispararia fetch pra rotas que exigem contexto de clínica;
// - UserMenu continua o mesmo componente, já com mode="admin" (perfil +
//   sair, sem os itens exclusivos do modo clínica).
//
// "Entrar na clínica": System Admin nunca ganha contexto de clínica
// automaticamente (login, EnsureCurrentClinic) — só via ação explícita
// aqui, e só pras clínicas onde ele é membro real (clinic_user), listadas
// em auth.myClinics (ver HandleInertiaRequests). Ver
// Admin\ClinicController::enter()/exit().
const page = usePage()
</script>

<template>
    <div class="flex items-center">
        <AdminQuickActionsMenu />

        <div class="ml-6 lg:ml-10 flex h-11 shrink-0 items-center gap-2 rounded-full border border-slate-200/80 bg-white px-2.5 shadow-sm"
             title="Contexto administrativo">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-50">
                <ShieldCheckIcon class="h-4 w-4 text-violet-600" stroke-width="2" />
            </div>
            <span class="max-w-[6rem] truncate text-sm font-medium text-slate-700 sm:hidden">Admin</span>
            <span class="hidden max-w-[10rem] truncate text-sm font-medium text-slate-700 sm:inline">Admin de sistema</span>
        </div>

        <NavbarDropdown v-if="page.props.auth.myClinics?.length" align="right" width="w-64">
            <template #trigger="{ open }">
                <button
                    type="button"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    class="ml-2 flex h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-sm font-medium text-emerald-700 shadow-sm transition-colors duration-[180ms] ease hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35"
                >
                    <BuildingOffice2Icon class="h-4 w-4 shrink-0" stroke-width="2" />
                    <span class="hidden sm:inline">Entrar na clínica</span>
                    <ChevronDownIcon class="h-3.5 w-3.5 shrink-0 transition-transform duration-[180ms] ease" :class="open ? 'rotate-180' : ''" />
                </button>
            </template>

            <template #default="{ close }">
                <div class="py-1">
                    <p class="px-3.5 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Suas clínicas
                    </p>
                    <NavbarDropdownItem
                        v-for="clinic in page.props.auth.myClinics"
                        :key="clinic.id"
                        :href="route('admin.clinics.enter', clinic.id)"
                        method="post"
                        @click="close"
                    >
                        {{ clinic.name }}
                    </NavbarDropdownItem>
                </div>
            </template>
        </NavbarDropdown>

        <div class="ml-auto flex h-11 shrink-0 items-center">
            <div class="flex h-11 items-center rounded-full border border-slate-200/80 bg-white px-1 shadow-sm">
                <UserMenu mode="admin" />
            </div>
        </div>
    </div>
</template>
