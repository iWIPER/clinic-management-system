<script setup>
import { Link } from '@inertiajs/vue3'
import TopProgress from '@/Components/Navbar/TopProgress.vue'
import NavbarBrand from '@/Components/Navbar/NavbarBrand.vue'
import UserMenu from './UserMenu.vue'

// Uso exclusivo do Backoffice (AdminLayout) — o modo clínica não usa mais
// uma topbar global; a sidebar (Sidebar.vue) é a âncora visual lá.
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
                    <Link :href="route('dashboard')" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        ← Voltar ao sistema
                    </Link>

                    <div class="flex items-center border-l border-slate-200 pl-3">
                        <UserMenu mode="admin" />
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>
