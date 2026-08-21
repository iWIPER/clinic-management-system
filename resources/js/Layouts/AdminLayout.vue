<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminSidebar from '@/Components/Navigation/AdminSidebar.vue'
import AdminTopIsland from '@/Components/Navigation/AdminTopIsland.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import TopProgress from '@/Components/Navbar/TopProgress.vue'
import SystemAdminAccessNotice from '@/Components/Admin/SystemAdminAccessNotice.vue'
import { adminNavigation } from '@/Navigation/adminConfig'

// Shell do Backoffice — agora espelha a mesma estrutura visual do shell
// clínico (AppLayout.vue: sidebar fixa + região rolável com uma TopIsland
// sticky), só trocando os componentes de navegação por seus equivalentes
// administrativos (AdminSidebar/AdminTopIsland). A tab-bar horizontal que
// existia antes foi removida — os mesmos módulos agora vivem na sidebar,
// então mantê-la também seria navegação duplicada. TaskPanel não entra
// aqui: é um recurso clínico (tarefas da clínica), sem equivalente
// administrativo.
const page = usePage()

const pageTitle = computed(() => {
    const url = page.url.split('?')[0]
    const match = adminNavigation
        .flatMap((group) => group.items)
        .find((item) => (item.match.endsWith('$') ? url === item.match.slice(0, -1) : url.startsWith(item.match)))
    return match?.label ?? 'Backoffice'
})

// Aviso de acesso privilegiado — puramente informativo, NUNCA um mecanismo
// de autorização (a única autoridade real é o middleware system-admin no
// backend). Estado vem de users.preferences (auth.hasAcknowledgedAdminAccess,
// ver HandleInertiaRequests) — persistido no usuário, não sessão/
// localStorage: nunca reaparece depois de reconhecido uma vez, em nenhum
// login futuro. Um ref local dá feedback instantâneo no clique, sem
// esperar o round-trip do axios.
const showAccessNotice = ref(!page.props.auth.hasAcknowledgedAdminAccess)

function acknowledgeAccess() {
    showAccessNotice.value = false
    window.axios.post(route('admin.acknowledge-access')).catch(() => {})
}
</script>

<template>
<div class="relative h-screen flex bg-slate-100 overflow-hidden">
  <TopProgress />

  <AdminSidebar />

  <div class="flex-1 overflow-y-auto" scroll-region style="scrollbar-gutter: stable">
    <main class="w-full px-[var(--shell-gutter)] mx-auto pb-6">

      <div class="sticky top-0 z-30 space-y-3 bg-slate-100 pb-[var(--shell-gutter)] pt-[var(--shell-gutter)] -mx-[var(--shell-gutter)] px-[var(--shell-gutter)]">
        <AdminTopIsland />
        <h1 class="text-lg font-semibold text-slate-900">{{ pageTitle }}</h1>
      </div>

      <slot />
    </main>
  </div>

  <ToastContainer />
  <SystemAdminAccessNotice :show="showAccessNotice" @acknowledge="acknowledgeAccess" />
</div>
</template>
