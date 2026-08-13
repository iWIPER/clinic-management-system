<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()

const navItems = [
    { href: 'admin.index',      label: 'Dashboard',   match: '/admin$' },
    { href: 'admin.clinics',    label: 'Clínicas',    match: '/admin/clinicas' },
    { href: 'admin.referrals',  label: 'Indicações',  match: '/admin/indicacoes' },
    { href: 'admin.plans',      label: 'Planos',      match: '/admin/planos' },
    { href: 'admin.logs',       label: 'Logs',        match: '/admin/logs' },
]

function isActive(match) {
    const url = page.url.split('?')[0]
    if (match.endsWith('$')) return url === '/admin'
    return url.startsWith(match)
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold text-slate-900">Backoffice Wildental</h1>
                    <span class="rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700">
                        Super Admin
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">Painel administrativo exclusivo</p>
            </div>
            <Link :href="route('dashboard')"
                  class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                ← Voltar ao sistema
            </Link>
        </div>

        <nav class="mb-6 flex flex-wrap gap-1 rounded-xl border bg-white p-1">
            <Link v-for="item in navItems" :key="item.href"
                  :href="route(item.href)"
                  class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                  :class="isActive(item.match)
                      ? 'bg-emerald-600 text-white shadow-sm'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
                {{ item.label }}
            </Link>
        </nav>

        <slot />
    </AppLayout>
</template>