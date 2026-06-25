<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { resolveStatus, getDelayMinutes, sortByPriority } from '@/composables/useAppointmentStatus'

const props = defineProps({
    appointments: Array,
    professionals: Array,
    weekStart: String,
    filters: Object,
})

// ── Constantes do grid ────────────────────────────────────────────────────
const START_HOUR = 7
const END_HOUR = 21
const TOTAL_MIN = (END_HOUR - START_HOUR) * 60 // 840px de altura total

// ── Helpers de data ────────────────────────────────────────────────────────
const parseLocalDate = (str) => new Date(str + 'T00:00:00')
const addDays = (date, n) => { const d = new Date(date); d.setDate(d.getDate() + n); return d }
const toDateStr = (date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

const PT_DAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const PT_MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']

const dayIndex = (date) => date.getDay() === 0 ? 6 : date.getDay() - 1

const formatHour = (h) => `${String(h).padStart(2, '0')}:00`

const formatTime = (str) =>
    new Date(str).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })

const isToday = (date) => {
    const t = new Date()
    return date.getDate() === t.getDate() &&
           date.getMonth() === t.getMonth() &&
           date.getFullYear() === t.getFullYear()
}

// ── Estado da UI ───────────────────────────────────────────────────────────
const showSidebar    = ref(true)
const showMiniCal    = ref(true)
const showSaturday   = ref(false)
const showSunday     = ref(false)
const profFilter     = ref(props.filters?.professional_id || '')
const activePopover  = ref(null)
const popoverStyle   = ref({})
const popoverRef     = ref(null)

// ── Datas da semana ────────────────────────────────────────────────────────
const weekDates = computed(() => {
    const monday = parseLocalDate(props.weekStart)
    return Array.from({ length: 7 }, (_, i) => addDays(monday, i))
})

const visibleDays = computed(() => {
    const days = [...weekDates.value.slice(0, 5)]
    if (showSaturday.value) days.push(weekDates.value[5])
    if (showSunday.value)   days.push(weekDates.value[6])
    return days
})

const weekLabel = computed(() => {
    const [first, last] = [weekDates.value[0], weekDates.value[6]]
    return first.getMonth() === last.getMonth()
        ? `${PT_MONTHS[first.getMonth()]} ${first.getFullYear()}`
        : `${PT_MONTHS[first.getMonth()].slice(0, 3)} – ${PT_MONTHS[last.getMonth()].slice(0, 3)} ${last.getFullYear()}`
})

// ── Linhas de hora ─────────────────────────────────────────────────────────
const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)

// ── Lógica de sobreposição ─────────────────────────────────────────────────
function assignColumns(list) {
    if (!list.length) return []
    const sorted = [...list].sort((a, b) => new Date(a.start) - new Date(b.start))
    const colEnds = []

    sorted.forEach(appt => {
        const start = new Date(appt.start).getTime()
        const end   = new Date(appt.end).getTime()
        let col = colEnds.findIndex(e => e <= start)
        if (col === -1) col = colEnds.length
        colEnds[col] = end
        appt._col = col
    })

    sorted.forEach(appt => {
        const s = new Date(appt.start).getTime()
        const e = new Date(appt.end).getTime()
        let maxCol = 0
        sorted.forEach(o => {
            if (new Date(o.start).getTime() < e && new Date(o.end).getTime() > s)
                maxCol = Math.max(maxCol, o._col)
        })
        appt._totalCols = maxCol + 1
    })
    return sorted
}

const byDay = computed(() => {
    const map = {}
    visibleDays.value.forEach(day => {
        const ds = toDateStr(day)
        const list = props.appointments.filter(a => {
            const match = a.start.slice(0, 10) === ds
            return profFilter.value ? match && a.professional_id == profFilter.value : match
        })
        map[ds] = assignColumns(list)
    })
    return map
})

function apptStyle(appt) {
    const s = new Date(appt.start)
    const e = new Date(appt.end)
    const top = (s.getHours() - START_HOUR) * 60 + s.getMinutes()
    const dur = Math.max((e - s) / 60000, 15)
    const colW = 100 / appt._totalCols
    return {
        position: 'absolute',
        top:    `${top}px`,
        height: `${dur - 2}px`,
        left:   `calc(${appt._col * colW}% + 2px)`,
        width:  `calc(${colW}% - 4px)`,
        zIndex: activePopover.value?.id === appt.id ? 30 : 10,
    }
}

function apptHeightPx(appt) {
    const s = new Date(appt.start)
    const e = new Date(appt.end)
    return Math.max((e - s) / 60000, 15)
}

// ── Configuração de status ──────────────────────────────────────────────────
const STATUS = {
    scheduled:     { label: 'Agendada',       bg: 'bg-blue-50',    border: 'border-l-blue-400',   text: 'text-blue-700',   badge: 'bg-blue-100 text-blue-700' },
    confirmed:     { label: 'Confirmada',      bg: 'bg-green-50',   border: 'border-l-green-400',  text: 'text-green-700',  badge: 'bg-green-100 text-green-700' },
    in_attendance: { label: 'Em atendimento',  bg: 'bg-violet-50',  border: 'border-l-violet-500', text: 'text-violet-700', badge: 'bg-violet-100 text-violet-700' },
    completed:     { label: 'Concluída',       bg: 'bg-slate-50',   border: 'border-l-slate-400',  text: 'text-slate-500',  badge: 'bg-slate-100 text-slate-500' },
    cancelled:     { label: 'Cancelada',       bg: 'bg-red-50',     border: 'border-l-red-400',    text: 'text-red-600',    badge: 'bg-red-100 text-red-600' },
    no_show:       { label: 'Faltou',          bg: 'bg-amber-50',   border: 'border-l-amber-400',  text: 'text-amber-700',  badge: 'bg-amber-100 text-amber-700' },
}
const s = (status) => STATUS[status] ?? STATUS.scheduled

// ── Navegação de semana ────────────────────────────────────────────────────
const navWeek = (delta) => {
    const d = parseLocalDate(props.weekStart)
    d.setDate(d.getDate() + delta * 7)
    router.get(route('appointments.index'),
        { week: toDateStr(d), professional_id: profFilter.value || undefined },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

const goToday = () => router.get(route('appointments.index'),
    { professional_id: profFilter.value || undefined },
    { preserveState: true, only: ['appointments', 'weekStart'] })

const onProfChange = () => router.get(route('appointments.index'),
    { week: props.weekStart, professional_id: profFilter.value || undefined },
    { preserveState: true, only: ['appointments'] })

// ── Linha do horário atual ─────────────────────────────────────────────────
const nowRef = ref(new Date())
let _clockTimer = null
onMounted(() => { _clockTimer = setInterval(() => { nowRef.value = new Date() }, 30000) })
onUnmounted(() => clearInterval(_clockTimer))

const nowTop = computed(() => {
    const n = nowRef.value
    return (n.getHours() - START_HOUR) * 60 + n.getMinutes()
})

// ── Popover ────────────────────────────────────────────────────────────────
function openPopover(appt, e) {
    if (activePopover.value?.id === appt.id) { activePopover.value = null; return }
    activePopover.value = appt
    const rect = e.currentTarget.getBoundingClientRect()
    const PW = 300
    let left = rect.right + 8
    if (left + PW > window.innerWidth - 8) left = rect.left - PW - 8
    if (left < 8) left = 8
    let top = Math.min(rect.top, window.innerHeight - 340)
    if (top < 8) top = 8
    popoverStyle.value = { left: `${left}px`, top: `${top}px`, width: `${PW}px` }
}
const closePopover = () => { activePopover.value = null }

function onOutsideClick(e) {
    if (popoverRef.value && !popoverRef.value.contains(e.target)) closePopover()
}
onMounted(() => {
    document.addEventListener('mousedown', onOutsideClick)
    // Polling: atualiza agendamentos a cada 30s sem recarregar a página
    _pollTimer = setInterval(() => {
        router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true })
    }, 30000)
})
onUnmounted(() => {
    document.removeEventListener('mousedown', onOutsideClick)
    clearInterval(_pollTimer)
})
let _pollTimer = null

// ── Ações rápidas ─────────────────────────────────────────────────────────
const quickConfirm = (appt) =>
    router.patch(route('appointments.update-status', appt.id), { status: 'confirmed' },
        { preserveState: true, preserveScroll: true,
          onSuccess: () => { closePopover(); router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true }) } })

// appointments.check-in fica na agenda (back()) — não navega para consultas
const quickCheckin = (appt) =>
    router.post(route('appointments.check-in', appt.id), {},
        { preserveScroll: true, onSuccess: closePopover })

// ── Criar agendamento clicando na grade ────────────────────────────────────
function clickSlot(day, e) {
    if (activePopover.value) { closePopover(); return }
    // Só criar se clicou na área vazia (não num card)
    if (e.target !== e.currentTarget && e.target.closest('[data-appt]')) return
    const rect = e.currentTarget.getBoundingClientRect()
    const minutes = Math.floor((e.clientY - rect.top) / 30) * 30
    const totalMin = START_HOUR * 60 + minutes
    const h = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m = String(totalMin % 60).padStart(2, '0')
    router.get(route('appointments.create'), { date: toDateStr(day), time: `${h}:${m}` })
}

// ── Mini calendário ────────────────────────────────────────────────────────
const miniMonthDate = ref(new Date())

const miniDays = computed(() => {
    const year  = miniMonthDate.value.getFullYear()
    const month = miniMonthDate.value.getMonth()
    const first = new Date(year, month, 1)
    const last  = new Date(year, month + 1, 0)
    const startOffset = first.getDay() === 0 ? 6 : first.getDay() - 1
    const days = []
    for (let i = 0; i < startOffset; i++)
        days.push({ date: new Date(year, month, 1 - startOffset + i), cur: false })
    for (let i = 1; i <= last.getDate(); i++)
        days.push({ date: new Date(year, month, i), cur: true })
    const rem = 42 - days.length
    for (let i = 1; i <= rem; i++)
        days.push({ date: new Date(year, month + 1, i), cur: false })
    return days
})

const miniMonthLabel = computed(() =>
    `${PT_MONTHS[miniMonthDate.value.getMonth()].slice(0, 3)} ${miniMonthDate.value.getFullYear()}`)

const navMiniMonth = (d) => {
    const m = new Date(miniMonthDate.value)
    m.setMonth(m.getMonth() + d)
    miniMonthDate.value = m
}

const isInCurrentWeek = (date) => {
    const ws = parseLocalDate(props.weekStart)
    const we = addDays(ws, 6)
    return date >= ws && date <= we
}

const jumpToWeek = (date) => {
    const d = new Date(date)
    const dow = d.getDay()
    d.setDate(d.getDate() + (dow === 0 ? -6 : 1 - dow))
    router.get(route('appointments.index'),
        { week: toDateStr(d) },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

// ── Lista de pacientes de hoje (sidebar) ──────────────────────────────────
const todayStr = toDateStr(new Date())

const todayAppointmentsSorted = computed(() => {
    const list = props.appointments.filter(a => a.start.slice(0, 10) === todayStr)
    return sortByPriority(list, nowRef.value)
})

const todayStats = computed(() => {
    const list = props.appointments.filter(a => a.start.slice(0, 10) === todayStr)
    const usedMin = list.reduce((sum, a) => sum + (new Date(a.end) - new Date(a.start)) / 60000, 0)
    return {
        total:     list.length,
        confirmed: list.filter(a => a.status === 'confirmed').length,
        cancelled: list.filter(a => a.status === 'cancelled').length,
        no_show:   list.filter(a => a.status === 'no_show').length,
        occupancy: Math.min(Math.round((usedMin / TOTAL_MIN) * 100), 100),
    }
})
</script>

<template>
<AppLayout>
<!-- Bloco principal que usa toda a largura disponível -->
<div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 flex flex-col" style="min-height: calc(100vh - 64px)">

  <!-- ── Barra de ferramentas ──────────────────────────────────────────── -->
  <div class="flex items-center gap-2 px-4 py-2.5 border-b bg-white flex-shrink-0 flex-wrap">

    <!-- Toggle sidebar -->
    <button @click="showSidebar = !showSidebar"
            class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            :title="showSidebar ? 'Recolher painel' : 'Expandir painel'">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              :d="showSidebar ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'" />
      </svg>
    </button>

    <!-- Navegação semanal -->
    <div class="flex items-center gap-1">
      <button @click="navWeek(-1)"
              class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <button @click="goToday"
              class="px-3 py-1 text-xs font-medium rounded-md border border-slate-200 hover:bg-slate-50 transition-colors">
        Hoje
      </button>
      <button @click="navWeek(1)"
              class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

    <!-- Label da semana -->
    <span class="text-sm font-semibold text-slate-700">{{ weekLabel }}</span>
    <span class="text-xs text-slate-400 hidden sm:inline">
      {{ weekDates[0].getDate() }}/{{ weekDates[0].getMonth() + 1 }}
      –
      {{ weekDates[6].getDate() }}/{{ weekDates[6].getMonth() + 1 }}
    </span>

    <div class="flex-1" />

    <!-- Sábado / Domingo -->
    <button @click="showSaturday = !showSaturday"
            class="px-2.5 py-1 text-xs rounded-full border transition-colors"
            :class="showSaturday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
      Sábado
    </button>
    <button @click="showSunday = !showSunday"
            class="px-2.5 py-1 text-xs rounded-full border transition-colors"
            :class="showSunday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
      Domingo
    </button>

    <!-- Filtro profissional -->
    <select v-model="profFilter" @change="onProfChange"
            class="text-xs border border-slate-200 rounded-md px-2 py-1.5 text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-400 bg-white">
      <option value="">Todos</option>
      <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
    </select>

    <!-- Tela cheia -->
    <Link :href="route('appointments.fullscreen', { week: weekStart })"
          class="p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
          title="Tela cheia">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
      </svg>
    </Link>

    <!-- Novo agendamento -->
    <Link :href="route('appointments.create')"
          class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors">
      + Novo
    </Link>
  </div>

  <!-- ── Corpo: sidebar + calendário ──────────────────────────────────── -->
  <div class="flex flex-1 overflow-hidden">

    <!-- ── Sidebar esquerda ──────────────────────────────────────────── -->
    <transition name="agenda-sidebar">
      <div v-show="showSidebar"
           class="w-52 flex-shrink-0 border-r bg-white flex flex-col overflow-y-auto overflow-x-hidden">

        <!-- Mini calendário header -->
        <div class="px-3 py-2.5 border-b">
          <button @click="showMiniCal = !showMiniCal"
                  class="flex items-center justify-between w-full text-xs font-semibold text-slate-600 hover:text-slate-800">
            <span>Calendário</span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                 :class="{ 'rotate-180': !showMiniCal }"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
        </div>

        <!-- Mini calendário body -->
        <transition name="agenda-collapse">
          <div v-if="showMiniCal" class="px-3 py-3 border-b">
            <div class="flex items-center justify-between mb-2">
              <button @click="navMiniMonth(-1)" class="p-0.5 rounded hover:bg-slate-100 text-slate-400">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <span class="text-[11px] font-semibold text-slate-700">{{ miniMonthLabel }}</span>
              <button @click="navMiniMonth(1)" class="p-0.5 rounded hover:bg-slate-100 text-slate-400">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
            <!-- Cabeçalhos dos dias -->
            <div class="grid grid-cols-7 mb-1">
              <div v-for="d in ['S','T','Q','Q','S','S','D']" :key="d + Math.random()"
                   class="text-center text-[9px] font-medium text-slate-400">{{ d }}</div>
            </div>
            <!-- Dias -->
            <div class="grid grid-cols-7 gap-y-0.5">
              <button v-for="(day, i) in miniDays" :key="i"
                      @click="jumpToWeek(day.date)"
                      class="flex items-center justify-center text-[10px] rounded leading-none py-0.5 transition-colors"
                      :class="{
                        'text-slate-300':                               !day.cur,
                        'text-slate-600 hover:bg-slate-100':            day.cur && !isInCurrentWeek(day.date) && !isToday(day.date),
                        'bg-emerald-100 text-emerald-700 font-semibold': day.cur && isInCurrentWeek(day.date),
                        'ring-1 ring-inset ring-emerald-500':           isToday(day.date),
                      }">
                {{ day.date.getDate() }}
              </button>
            </div>
          </div>
        </transition>

        <!-- Resumo diário -->
        <div class="px-3 py-3 flex-1">
          <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 mb-3">Hoje</div>
          <div class="space-y-2.5">
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Consultas</span>
              <span class="font-semibold text-slate-700">{{ todayStats.total }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Confirmadas</span>
              <span class="font-semibold text-green-600">{{ todayStats.confirmed }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Canceladas</span>
              <span class="font-semibold text-red-500">{{ todayStats.cancelled }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Faltas</span>
              <span class="font-semibold text-amber-500">{{ todayStats.no_show }}</span>
            </div>
          </div>

          <!-- Barra de ocupação -->
          <div class="mt-4">
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-500">Ocupação</span>
              <span class="font-semibold text-slate-700">{{ todayStats.occupancy }}%</span>
            </div>
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :class="{
                     'bg-emerald-500': todayStats.occupancy < 70,
                     'bg-amber-400':   todayStats.occupancy >= 70 && todayStats.occupancy < 90,
                     'bg-red-500':     todayStats.occupancy >= 90,
                   }"
                   :style="{ width: todayStats.occupancy + '%' }" />
            </div>
          </div>

          <!-- Lista de pacientes de hoje — ordenados por prioridade -->
          <div v-if="todayAppointmentsSorted.length" class="mt-4 border-t pt-3">
            <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 mb-2">Fila de hoje</div>
            <div class="space-y-1">
              <button v-for="appt in todayAppointmentsSorted" :key="'sl-' + appt.id"
                      class="w-full flex items-center gap-1.5 py-1 px-1 -mx-1 rounded hover:bg-slate-50 text-left transition-colors"
                      @click="openPopover(appt, $event)">
                <StatusIndicator
                  :status="resolveStatus(appt, nowRef)"
                  :delay-minutes="getDelayMinutes(appt, nowRef)"
                  size="sm" />
                <span class="text-[10px] text-slate-700 truncate flex-1 leading-tight">
                  {{ appt.patient?.nome }} {{ appt.patient?.sobrenome?.charAt(0) }}.
                </span>
                <span class="text-[9px] text-slate-400 flex-shrink-0 tabular-nums">
                  {{ formatTime(appt.start) }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Grade do calendário ───────────────────────────────────────── -->
    <div class="flex-1 overflow-auto bg-slate-50/60">

      <!-- Cabeçalho dos dias (sticky) -->
      <div class="flex bg-white border-b sticky top-0 z-20" style="min-width: max-content">
        <div class="w-14 flex-shrink-0 border-r" />
        <div v-for="day in visibleDays" :key="'hd-' + toDateStr(day)"
             class="flex-1 min-w-[130px] text-center py-2 border-r last:border-r-0"
             :class="isToday(day) ? 'bg-emerald-50' : ''">
          <div class="text-[10px] font-semibold uppercase tracking-wide"
               :class="isToday(day) ? 'text-emerald-500' : 'text-slate-400'">
            {{ PT_DAYS[dayIndex(day)] }}
          </div>
          <div class="text-base font-bold leading-tight"
               :class="isToday(day) ? 'text-emerald-600' : 'text-slate-700'">
            {{ day.getDate() }}
          </div>
        </div>
      </div>

      <!-- Grade de tempo -->
      <div class="flex" :style="{ height: TOTAL_MIN + 'px', minWidth: 'max-content' }">

        <!-- Coluna de horas -->
        <div class="w-14 flex-shrink-0 border-r bg-white relative">
          <div v-for="h in hours" :key="'th-' + h"
               class="absolute right-0 pr-2 -translate-y-1/2 text-[10px] text-slate-400 text-right"
               :style="{ top: `${(h - START_HOUR) * 60}px` }">
            {{ formatHour(h) }}
          </div>
        </div>

        <!-- Colunas dos dias -->
        <div v-for="day in visibleDays" :key="'dc-' + toDateStr(day)"
             class="flex-1 min-w-[130px] relative border-r last:border-r-0"
             :class="isToday(day) ? 'bg-blue-50/20' : 'bg-white'"
             @click="clickSlot(day, $event)">

          <!-- Linhas de hora -->
          <div v-for="h in hours" :key="'hl-' + h"
               class="absolute w-full border-t border-slate-100"
               :style="{ top: `${(h - START_HOUR) * 60}px` }" />

          <!-- Linhas de meia hora -->
          <div v-for="h in hours.slice(0, -1)" :key="'hhl-' + h"
               class="absolute w-full border-t border-dashed border-slate-100"
               :style="{ top: `${(h - START_HOUR) * 60 + 30}px` }" />

          <!-- Linha do horário atual -->
          <div v-if="isToday(day) && nowTop >= 0 && nowTop <= TOTAL_MIN"
               class="absolute left-0 right-0 z-10 pointer-events-none flex items-center"
               :style="{ top: `${nowTop}px` }">
            <div class="w-2 h-2 rounded-full bg-red-500 -ml-1 flex-shrink-0 shadow-sm" />
            <div class="flex-1 h-px bg-red-400" />
          </div>

          <!-- Cards de agendamento -->
          <div v-for="appt in byDay[toDateStr(day)]" :key="appt.id"
               data-appt="1"
               class="absolute rounded border-l-[3px] overflow-visible cursor-pointer select-none hover:shadow-md transition-shadow"
               :class="[s(appt.status).bg, s(appt.status).border]"
               :style="apptStyle(appt)"
               @click.stop="openPopover(appt, $event)">

            <!-- Indicador de status (canto direito) -->
            <div class="absolute top-1 right-1 z-20">
              <StatusIndicator
                :status="resolveStatus(appt, nowRef)"
                :delay-minutes="getDelayMinutes(appt, nowRef)"
                size="sm" />
            </div>

            <div class="h-full flex flex-col justify-start px-1.5 pt-0.5 gap-px pr-4">
              <!-- Nome sempre visível -->
              <div class="text-[10px] font-semibold leading-tight truncate"
                   :class="s(appt.status).text">
                {{ appt.patient?.nome }} {{ appt.patient?.sobrenome }}
              </div>
              <!-- Tratamento: visível se ≥ 28px -->
              <div v-if="apptHeightPx(appt) >= 28"
                   class="text-[9px] text-slate-500 truncate leading-tight">
                {{ appt.treatment?.nome }}
              </div>
              <!-- Horário: visível se ≥ 44px -->
              <div v-if="apptHeightPx(appt) >= 44"
                   class="text-[9px] text-slate-400 leading-tight">
                {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Popover (teleport para o body) ────────────────────────────────── -->
<Teleport to="body">
  <div v-if="activePopover"
       ref="popoverRef"
       class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden"
       :style="popoverStyle">

    <!-- Header -->
    <div class="px-4 py-3 border-b" :class="s(activePopover.status).bg">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="font-semibold text-sm text-slate-800 leading-tight truncate">
            {{ activePopover.patient?.nome }} {{ activePopover.patient?.sobrenome }}
          </div>
          <div class="text-xs text-slate-500 mt-0.5">
            {{ formatTime(activePopover.start) }} – {{ formatTime(activePopover.end) }}
            <span class="mx-1">·</span>
            {{ activePopover.treatment?.nome }}
          </div>
        </div>
        <button @click="closePopover"
                class="p-0.5 rounded hover:bg-black/10 text-slate-400 flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Info -->
    <div class="px-4 py-2.5 border-b space-y-1.5">
      <div class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Profissional</span>
        <span class="text-xs text-slate-700">{{ activePopover.professional?.name || '—' }}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Status</span>
        <StatusIndicator
          :status="resolveStatus(activePopover, nowRef)"
          :delay-minutes="getDelayMinutes(activePopover, nowRef)"
          show-label />
      </div>
      <div v-if="activePopover.patient?.telefone" class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Telefone</span>
        <span class="text-xs text-slate-700">{{ activePopover.patient.telefone }}</span>
      </div>
    </div>

    <!-- Ações rápidas -->
    <div class="p-3 grid grid-cols-2 gap-2">
      <button v-if="activePopover.status === 'scheduled'"
              @click="quickConfirm(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 transition-colors border border-green-200">
        Confirmar
      </button>

      <button v-if="['scheduled', 'confirmed'].includes(activePopover.status)"
              @click="quickCheckin(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 transition-colors border border-blue-200">
        Check-in
      </button>

      <Link v-if="activePopover.consultation"
            :href="route('consultations.show', activePopover.consultation.id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-violet-50 hover:bg-violet-100 text-violet-700 transition-colors border border-violet-200">
        Prontuário
      </Link>

      <Link :href="route('appointments.edit', activePopover.id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 transition-colors border border-slate-200">
        Editar
      </Link>

      <Link :href="route('patients.show', activePopover.patient_id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 transition-colors border border-slate-200">
        Ver paciente
      </Link>
    </div>
  </div>
</Teleport>
</AppLayout>
</template>

<style scoped>
/* Sidebar collapse animation */
.agenda-sidebar-enter-active,
.agenda-sidebar-leave-active {
  transition: max-width 0.22s ease, opacity 0.18s ease;
  overflow: hidden;
}
.agenda-sidebar-enter-from,
.agenda-sidebar-leave-to {
  max-width: 0 !important;
  opacity: 0;
}
.agenda-sidebar-enter-to,
.agenda-sidebar-leave-from {
  max-width: 13rem;
  opacity: 1;
}

/* Mini calendário collapse */
.agenda-collapse-enter-active,
.agenda-collapse-leave-active {
  transition: max-height 0.2s ease, opacity 0.15s ease;
  overflow: hidden;
}
.agenda-collapse-enter-from,
.agenda-collapse-leave-to {
  max-height: 0;
  opacity: 0;
}
.agenda-collapse-enter-to,
.agenda-collapse-leave-from {
  max-height: 300px;
  opacity: 1;
}
</style>
