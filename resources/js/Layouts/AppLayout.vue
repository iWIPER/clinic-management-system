<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import TopProgress from '@/Components/Navbar/TopProgress.vue'
import NavbarBrand from '@/Components/Navbar/NavbarBrand.vue'
import NavbarNav from '@/Components/Navbar/NavbarNav.vue'
import NavbarItem from '@/Components/Navbar/NavbarItem.vue'
import NavbarIconButton from '@/Components/Navbar/NavbarIconButton.vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'
import NotificationCenter from '@/Components/Navbar/NotificationCenter.vue'
import TaskPanel from '@/Components/Tasks/TaskPanel.vue'

// ── Largura do conteúdo ──────────────────────────────────────────────────
// Páginas de conteúdo largo (tabelas, dashboards, agenda, kanban) usam o
// padrão `full`. Páginas de conteúdo naturalmente estreito (formulários de
// cadastro/edição de uma entidade, telas de configuração) devem passar
// `content-width="sm|md|lg"` em vez de recriar um wrapper `max-w-*` próprio —
// mantém a régua de larguras centralizada num único lugar.
const props = defineProps({
    contentWidth: {
        type: String,
        default: 'full',
        validator: (v) => ['sm', 'md', 'lg', 'full'].includes(v),
    },
})

const CONTENT_WIDTH_CLASSES = {
    sm: 'max-w-lg',
    md: 'max-w-2xl',
    lg: 'max-w-4xl',
    full: 'max-w-7xl',
}

const mainWidthClass = computed(() => CONTENT_WIDTH_CLASSES[props.contentWidth] ?? CONTENT_WIDTH_CLASSES.full)

const page = usePage()
const { toasts, show: showToast, dismiss } = useToast()

// ── Flash → Toast ─────────────────────────────────────────────────────────
watch(() => page.props.flash?.success, (val) => { if (val) showToast(val, 'success') }, { immediate: true })
watch(() => page.props.flash?.error,   (val) => { if (val) showToast(val, 'error') },   { immediate: true })

const isSuperAdmin = computed(() => page.props.auth?.isSuperAdmin ?? false)
const isReferralsActive = computed(() => page.url.split('?')[0].startsWith('/indicacoes'))

const toastIcon = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error:   'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const toastColors = {
    success: { bg: 'bg-emerald-50 border-emerald-200', icon: 'text-emerald-500', text: 'text-emerald-800' },
    error:   { bg: 'bg-red-50 border-red-200',         icon: 'text-red-500',     text: 'text-red-800' },
    warning: { bg: 'bg-amber-50 border-amber-200',     icon: 'text-amber-500',   text: 'text-amber-800' },
    info:    { bg: 'bg-blue-50 border-blue-200',        icon: 'text-blue-500',    text: 'text-blue-800' },
}

const isSettingsActive = computed(() => page.url.split('?')[0].startsWith('/clinic-settings'))

// ── Painel de Tarefas ────────────────────────────────────────────────────
// Overlay client-side, não uma página — fica montado no layout para abrir
// sobre qualquer tela sem navegar (ver TaskPanel.vue).
const showTasksPanel = ref(false)
</script>

<template>
<div class="h-screen flex flex-col bg-slate-50 overflow-hidden">

  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <nav class="relative shrink-0 z-40 border-b bg-white">
    <TopProgress />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-[var(--app-navbar-h)] items-center">

        <!-- Marca -->
        <NavbarBrand :clinic="$page.props.currentClinic" />

        <!-- Nav + utilitários -->
        <div class="flex items-center gap-3 text-sm self-stretch">

          <NavbarNav>
            <NavbarItem :href="route('patients.index')"         label="Pacientes"      match="/patients" />
            <NavbarItem :href="route('appointments.index')"     label="Agenda"         match="/appointments" />
            <NavbarItem :href="route('consultations.index')"    label="Consultas"      match="/consultations" />
            <NavbarItem :href="route('clinical-records.index')" label="Atendimentos" match="/clinical-records" />
            <NavbarItem :href="route('treatments.index')"       label="Procedimentos"  match="/treatments" />
            <NavbarItem :href="route('inventory.index')"        label="Estoque"        match="/inventory" />
            <NavbarItem :href="route('finance.index')"          label="Financeiro"     match="/finance" />
          </NavbarNav>

          <!-- Ícones utilitários (expansível) -->
          <div class="flex items-center gap-0.5">
          <NavbarIconButton
            tooltip="Tarefas"
            :active="showTasksPanel"
            @click="showTasksPanel = true"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z"/>
            </svg>
          </NavbarIconButton>

          <NotificationCenter />

          <NavbarIconButton
            :href="route('clinic-settings.edit')"
            tooltip="Configurações"
            :active="isSettingsActive"
          >
            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </NavbarIconButton>
          </div>

          <!-- Perfil -->
          <div class="flex items-center border-l border-slate-200 pl-3">
            <NavbarDropdown width="w-52">
              <template #trigger="{ open }">
                <button
                  type="button"
                  :aria-expanded="open"
                  aria-haspopup="menu"
                  class="inline-flex h-8 cursor-pointer items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-medium leading-none tracking-normal text-slate-600 antialiased transition-all duration-[180ms] ease hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.98]"
                >
                  <span>{{ $page.props.auth.user.name }}</span>
                  <svg
                    class="h-3 w-3 text-slate-400 transition-transform duration-[180ms] ease"
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
                  <!-- Conta pessoal -->
                  <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Minha conta</div>
                  <NavbarDropdownItem :href="route('profile.edit')" @click="close">Meu perfil</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('access-logs.index')" @click="close">Logs de acesso</NavbarDropdownItem>
                  <NavbarDropdownItem v-if="isSuperAdmin" :href="route('admin.index')" @click="close">Backoffice</NavbarDropdownItem>
                  <div class="my-1 border-t border-slate-100" />
                  <!-- Clínica -->
                  <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Clínica</div>
                  <Link :href="route('referrals.index')" @click="close"
                        class="flex w-full items-center justify-between px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900"
                        :class="isReferralsActive ? 'bg-emerald-50 text-emerald-800' : ''">
                    <span>Programa de Indicações</span>
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-700">
                      Novidade
                    </span>
                  </Link>
                  <NavbarDropdownItem :href="route('team.index')" @click="close">Equipe</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('anamnesis-templates.index')" @click="close">Modelos de Anamnese</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('documents.index')" @click="close">Documentos</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('clinic-settings.documents.edit')" @click="close">Config. de Documentos</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('clinic-settings.convenios.index')" @click="close">Convênios</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('clinic-settings.edit')" @click="close">Configurações</NavbarDropdownItem>
                  <NavbarDropdownItem :href="route('clinic-settings.edit')" @click="close">Google Drive</NavbarDropdownItem>
                  <div class="my-1 border-t border-slate-100" />
                  <NavbarDropdownItem :href="route('logout')" method="post" danger @click="close">Sair</NavbarDropdownItem>
                </div>
              </template>
            </NavbarDropdown>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <TaskPanel :show="showTasksPanel" @close="showTasksPanel = false" />

  <!-- ── Região rolável (só o conteúdo rola; a navbar acima fica fora deste
       container, então a scrollbar nativa nasce já abaixo dela).
       `scroll-region`: diz ao Inertia que esta div — e não a window — é a
       área cujo scroll ele deve resetar/salvar/restaurar entre visitas
       (ver @inertiajs/core Scroll.regions()). Sem isso, o reset de scroll
       nas navegações e a restauração via voltar/avançar do navegador
       silenciosamente deixam de funcionar. ── -->
  <div class="flex-1 overflow-y-auto" scroll-region style="scrollbar-gutter: stable">
    <main :class="[mainWidthClass, 'mx-auto px-4 sm:px-6 lg:px-8 py-6']">
      <slot />
    </main>
  </div>

  <!-- ── Toast container (fixed top-right) ─────────────────────────────── -->
  <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none" style="min-width: 320px; max-width: 400px">
    <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
      <div v-for="toast in toasts" :key="toast.id"
           class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm"
           :class="toastColors[toast.type]?.bg">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="toastColors[toast.type]?.icon"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="toastIcon[toast.type]" />
        </svg>
        <span class="flex-1 leading-snug" :class="toastColors[toast.type]?.text">{{ toast.message }}</span>
        <button @click="dismiss(toast.id)"
                class="flex-shrink-0 opacity-50 hover:opacity-100 transition-opacity"
                :class="toastColors[toast.type]?.text">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</div>
</template>

<style scoped>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(40px); }
.toast-leave-to     { opacity: 0; transform: translateX(40px); }
.toast-move         { transition: transform 0.3s ease; }
</style>