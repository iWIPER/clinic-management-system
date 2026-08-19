<script setup>
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Topbar from '@/Components/Navigation/Topbar.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import SystemAdminAccessNotice from '@/Components/Admin/SystemAdminAccessNotice.vue'

// Shell próprio do Backoffice — não envolve mais o AppLayout de propósito:
// o AppLayout agora sempre renderiza a sidebar clínica (Pacientes/Agenda/
// Estoque/...), que não faz sentido num contexto cross-tenant de super
// admin. O Backoffice continua com navegação em abas horizontais (não uma
// segunda sidebar), só herdando a Topbar/tokens visuais do novo shell.
const page = usePage()

const navItems = [
    { href: 'admin.index',         label: 'Dashboard',      match: '/admin$' },
    { href: 'admin.clinics',       label: 'Clínicas',       match: '/admin/clinicas' },
    { href: 'admin.users',         label: 'Usuários',       match: '/admin/usuarios' },
    { href: 'admin.referrals',     label: 'Indicações',     match: '/admin/indicacoes' },
    { href: 'admin.plans',         label: 'Planos',         match: '/admin/planos' },
    { href: 'admin.exports',       label: 'Exportações',    match: '/admin/exportacoes' },
    { href: 'admin.logs',          label: 'Logs',           match: '/admin/logs' },
    { href: 'admin.system-admins', label: 'System Admins',  match: '/admin/system-admins' },
]

function isActive(match) {
    const url = page.url.split('?')[0]
    if (match.endsWith('$')) return url === '/admin'
    return url.startsWith(match)
}

// Aviso de acesso privilegiado — puramente informativo, NUNCA um mecanismo
// de autorização (a única autoridade real é o middleware system-admin no
// backend). Estado vem da sessão do Laravel (auth.hasAcknowledgedAdminAccess,
// ver HandleInertiaRequests), não de sessionStorage/localStorage: reaparece
// numa sessão de login nova (logout invalida a sessão do Laravel) e não
// reaparece só por causa de navegação/refresh dentro do mesmo login — sem
// precisar de coluna nova no banco só pra isso. Um ref local dá feedback
// instantâneo no clique, sem esperar o round-trip do axios.
const showAccessNotice = ref(!page.props.auth.hasAcknowledgedAdminAccess)

function acknowledgeAccess() {
    showAccessNotice.value = false
    window.axios.post(route('admin.acknowledge-access')).catch(() => {})
}
</script>

<template>
<div class="h-screen flex flex-col bg-slate-50 overflow-hidden">

  <Topbar mode="admin" />

  <div class="flex-1 overflow-y-auto" scroll-region style="scrollbar-gutter: stable">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Backoffice Wildental</h1>
        <p class="mt-1 text-sm text-slate-500">Painel administrativo exclusivo</p>
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
    </main>
  </div>

  <ToastContainer />
  <SystemAdminAccessNotice :show="showAccessNotice" @acknowledge="acknowledgeAccess" />
</div>
</template>
