<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useToast } from '@/composables/useToast'

const page = usePage()
const { toasts, show: showToast, dismiss } = useToast()

// ── Flash → Toast ─────────────────────────────────────────────────────────
watch(() => page.props.flash?.success, (val) => { if (val) showToast(val, 'success') }, { immediate: true })
watch(() => page.props.flash?.error,   (val) => { if (val) showToast(val, 'error') },   { immediate: true })

// ── Notificações ─────────────────────────────────────────────────────────
const counts = ref({ total: 0, aguardando_confirmacao: 0, aguardando_atendimento: 0, esperando_15min: 0, consulta_proxima: 0 })
const showNotifDropdown = ref(false)
const notifRef = ref(null)
let notifTimer = null

const fetchCounts = async () => {
    try {
        const res = await fetch(route('notifications.counts'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        if (res.ok) counts.value = await res.json()
    } catch {}
}

const onOutsideNotif = (e) => {
    if (notifRef.value && !notifRef.value.contains(e.target)) showNotifDropdown.value = false
}

onMounted(() => {
    fetchCounts()
    notifTimer = setInterval(fetchCounts, 60000)
    document.addEventListener('mousedown', onOutsideNotif)
})
onUnmounted(() => {
    clearInterval(notifTimer)
    document.removeEventListener('mousedown', onOutsideNotif)
})

// ── Itens de notificação derivados dos counts ─────────────────────────────
const notifItems = computed(() => {
    const items = []
    if (counts.value.aguardando_confirmacao > 0)
        items.push({ type: 'warning', text: `${counts.value.aguardando_confirmacao} agendamento(s) aguardando confirmação hoje` })
    if (counts.value.esperando_15min > 0)
        items.push({ type: 'error', text: `${counts.value.esperando_15min} paciente(s) esperando há mais de 15 min` })
    if (counts.value.aguardando_atendimento > 0)
        items.push({ type: 'info', text: `${counts.value.aguardando_atendimento} paciente(s) aguardando atendimento` })
    if (counts.value.consulta_proxima > 0)
        items.push({ type: 'success', text: `${counts.value.consulta_proxima} consulta(s) nos próximos 30 min` })
    return items
})

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

const notifDotColor = {
    success: 'bg-emerald-500',
    error:   'bg-red-500',
    warning: 'bg-amber-400',
    info:    'bg-blue-500',
}
</script>

<template>
<div class="min-h-screen bg-slate-50">

  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <nav class="border-b bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16 items-center">

        <!-- Logo + clínica -->
        <div class="flex items-center gap-3">
          <Link :href="route('dashboard')" class="font-semibold text-xl text-slate-800">
            Gestão Clínicas
          </Link>
          <div v-if="$page.props.currentClinic"
               class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700 font-medium">
            {{ $page.props.currentClinic.name }}
          </div>
        </div>

        <!-- Nav + utilitários -->
        <div class="flex items-center gap-4 text-sm">
          <Link :href="route('patients.index')"      class="text-emerald-700 hover:text-emerald-800 font-medium">Pacientes</Link>
          <Link :href="route('appointments.index')"  class="text-emerald-700 hover:text-emerald-800 font-medium">Agenda</Link>
          <Link :href="route('consultations.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Consultas</Link>
          <Link :href="route('clinical-records.index')" class="text-emerald-700 hover:text-emerald-800 font-medium">Atendimentos</Link>
          <Link :href="route('treatments.index')"    class="text-slate-500 hover:text-slate-700">Procedimentos</Link>
          <Link :href="route('inventory.index')"     class="text-emerald-700 hover:text-emerald-800 font-medium">Estoque</Link>
          <Link :href="route('finance.index')"       class="text-emerald-700 hover:text-emerald-800 font-medium">Financeiro</Link>

          <!-- ── Sino de notificações ──────────────────────────────────── -->
          <div ref="notifRef" class="relative">
            <button @click="showNotifDropdown = !showNotifDropdown"
                    class="relative p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
              </svg>
              <!-- Badge -->
              <span v-if="counts.total > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white leading-none">
                {{ counts.total > 9 ? '9+' : counts.total }}
              </span>
            </button>

            <!-- Dropdown de notificações -->
            <div v-if="showNotifDropdown"
                 class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-200 z-50 overflow-hidden">
              <div class="px-4 py-2.5 border-b flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-700 uppercase tracking-wide">Notificações</span>
                <span v-if="counts.total > 0"
                      class="text-[10px] bg-red-100 text-red-600 font-semibold px-1.5 py-0.5 rounded-full">
                  {{ counts.total }} pendente{{ counts.total > 1 ? 's' : '' }}
                </span>
              </div>

              <div v-if="notifItems.length === 0" class="px-4 py-6 text-center text-xs text-slate-400">
                Nenhuma notificação no momento.
              </div>

              <div v-else class="divide-y max-h-72 overflow-y-auto">
                <div v-for="(item, i) in notifItems" :key="i"
                     class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                  <div class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0" :class="notifDotColor[item.type]" />
                  <span class="text-xs text-slate-700 leading-snug">{{ item.text }}</span>
                </div>
              </div>

              <div class="border-t px-4 py-2">
                <Link :href="route('consultations.index')"
                      class="text-xs text-emerald-600 hover:text-emerald-700 font-medium"
                      @click="showNotifDropdown = false">
                  Ver consultas ativas →
                </Link>
              </div>
            </div>
          </div>

          <Link :href="route('clinic-settings.edit')" class="text-slate-400 hover:text-slate-600 text-xs">Config.</Link>

          <!-- Usuário + sair -->
          <div class="border-l pl-4 flex items-center gap-2">
            <span class="text-slate-500">{{ $page.props.auth.user.name }}</span>
            <Link :href="route('logout')" method="post" class="text-red-600 hover:text-red-700">Sair</Link>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── Conteúdo principal ─────────────────────────────────────────────── -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <slot />
  </main>

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
