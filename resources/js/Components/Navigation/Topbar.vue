<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import TopProgress from '@/Components/Navbar/TopProgress.vue'
import NavbarBrand from '@/Components/Navbar/NavbarBrand.vue'
import UserMenu from './UserMenu.vue'

// Uso exclusivo do Backoffice (AdminLayout) — o modo clínica não usa mais
// uma topbar global; a sidebar (Sidebar.vue) é a âncora visual lá.

// "Acessar clínica" é a ÚNICA porta pro contexto clínico a partir daqui —
// entrada automática foi removida de propósito (ver EnsureCurrentClinic +
// AuthenticatedSessionController). Só aparece se a conta realmente tem
// vínculo com alguma clínica (hasClinicAccess, ver HandleInertiaRequests);
// sem isso, não há pra onde "acessar". POST porque muda sessão (marca
// admin_clinic_context) — não é só navegação.
const page = usePage()
const hasClinicAccess = computed(() => page.props.auth?.hasClinicAccess ?? false)
</script>

<template>
    <nav class="relative shrink-0 z-40 border-b bg-white">
        <TopProgress />

        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex h-[var(--app-navbar-h)] items-center justify-between gap-3">

                <div class="flex min-w-0 items-center gap-3">
                    <NavbarBrand />

                    <span class="rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700">
                        Backoffice
                    </span>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <Link v-if="hasClinicAccess" :href="route('admin.enter-clinic')" method="post"
                          class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        Acessar clínica →
                    </Link>

                    <div class="flex items-center border-l border-slate-200 pl-3">
                        <UserMenu mode="admin" />
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>
