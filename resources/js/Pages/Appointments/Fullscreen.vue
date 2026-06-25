<script setup>
import StatusIndicator from '@/Components/StatusIndicator.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { resolveStatus, getDelayMinutes } from '@/composables/useAppointmentStatus'

const props = defineProps({
    appointments: Array,
    professionals: Array,
    weekStart: String,
})

// ── Constantes ──────────────────────────────────────────────────────────────
const START_HOUR = 7
const END_HOUR = 21
const TOTAL_MIN = (END_HOUR - START_HOUR) * 60

// ── Helpers de data ─────────────────────────────────────────────────────────
const parseLocalDate = (str) => new Date(str + 'T00:00:00')
const addDays = (date, n) => { const d = new Date(date); d.setDate(d.getDate() + n); return d }
const toDateStr = (date) => {
    return [date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')].join('-')
}
const PT_DAYS   = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const PT_MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
const dayIndex  = (d) => d.getDay() === 0 ? 6 : d.getDay() - 1
const formatTime = (str) => new Date(str).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
const isToday = (date) => {
    const t = new Date()
    return date.getDate() === t.getDate() && date.getMonth() === t.getMonth() && date.getFullYear() === t.getFullYear()
}

// ── Estado ─────────────────────────────────────────────────────────────────
const showSaturday  = ref(false)
const showSunday    = ref(false)
const profFilter    = ref('')
const activePopover = ref(null)
const popoverStyle  = ref({})
const popoverRef    = ref(null)

// ── Datas ──────────────────────────────────────────────────────────────────
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
    const [f, l] = [weekDates.value[0], weekDates.value[6]]
    return f.getMonth() === l.getMonth()
        ? `${PT_MONTHS[f.getMonth()]} ${f.getFullYear()}`
        : `${PT_MONTHS[f.getMonth()].slice(0,3)} – ${PT_MONTHS[l.getMonth()].slice(0,3)} ${l.getFullYear()}`
})

const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)

// ── Sobreposição ────────────────────────────────────────────────────────────
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
        let mx = 0
        sorted.forEach(o => {
            if (new Date(o.start).getTime() < e && new Date(o.end).getTime() > s)
                mx = Math.max(mx, o._col)
        })
        appt._totalCols = mx + 1
    })
    return sorted
}

const byDay = computed(() => {
    const map = {}
    visibleDays.value.forEach(day => {
        const ds = toDateStr(day)
        const list = props.appointments.filter(a => {
            const ok = a.start.slice(0, 10) === ds
            return profFilter.value ? ok && a.professional_id == profFilter.value : ok
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
    const cw  = 100 / appt._totalCols
    return {
        position: 'absolute',
        top:    `${top}px`,
        height: `${dur - 2}px`,
        left:   `calc(${appt._col * cw}% + 2px)`,
        width:  `calc(${cw}% - 4px)`,
        zIndex: activePopover.value?.id === appt.id ? 30 : 10,
    }
}

const apptH = (appt) => Math.max((new Date(appt.end) - new Date(appt.start)) / 60000, 15)

// ── Status ──────────────────────────────────────────────────────────────────
const STATUS = {
    scheduled:     { label: 'Agendada',       bg: 'bg-blue-50',   border: 'border-l-blue-400',   text: 'text-blue-700',   badge: 'bg-blue-100 text-blue-700' },
    confirmed:     { label: 'Confirmada',      bg: 'bg-green-50',  border: 'border-l-green-400',  text: 'text-green-700',  badge: 'bg-green-100 text-green-700' },
    in_attendance: { label: 'Em atendimento',  bg: 'bg-violet-50', border: 'border-l-violet-500', text: 'text-violet-700', badge: 'bg-violet-100 text-violet-700' },
    completed:     { label: 'Concluída',       bg: 'bg-slate-50',  border: 'border-l-slate-400',  text: 'text-slate-500',  badge: 'bg-slate-100 text-slate-500' },
    cancelled:     { label: 'Cancelada',       bg: 'bg-red-50',    border: 'border-l-red-400',    text: 'text-red-600',    badge: 'bg-red-100 text-red-600' },
    no_show:       { label: 'Faltou',          bg: 'bg-amber-50',  border: 'border-l-amber-400',  text: 'text-amber-700',  badge: 'bg-amber-100 text-amber-700' },
}
const st = (status) => STATUS[status] ?? STATUS.scheduled

// ── Navegação ───────────────────────────────────────────────────────────────
const navWeek = (delta) => {
    const d = parseLocalDate(props.weekStart)
    d.setDate(d.getDate() + delta * 7)
    router.get(route('appointments.fullscreen'),
        { week: toDateStr(d), professional_id: profFilter.value || undefined },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

const goToday = () => router.get(route('appointments.fullscreen'),
    { professional_id: profFilter.value || undefined },
    { preserveState: true, only: ['appointments', 'weekStart'] })

// ── Linha do tempo atual ────────────────────────────────────────────────────
const nowRef = ref(new Date())
let _t = null
onMounted(() => { _t = setInterval(() => { nowRef.value = new Date() }, 30000) })
onUnmounted(() => clearInterval(_t))

const nowTop = computed(() => (nowRef.value.getHours() - START_HOUR) * 60 + nowRef.value.getMinutes())

// ── Popover ─────────────────────────────────────────────────────────────────
function openPopover(appt, e) {
    if (activePopover.value?.id === appt.id) { activePopover.value = null; return }
    activePopover.value = appt
    const rect = e.currentTarget.getBoundingClientRect()
    const PW = 300
    let left = rect.right + 8
    if (left + PW > window.innerWidth - 8) left = rect.left - PW - 8
    if (left < 8) left = 8
    let top = Math.min(rect.top, window.innerHeight - 320)
    if (top < 8) top = 8
    popoverStyle.value = { left: `${left}px`, top: `${top}px`, width: `${PW}px` }
}
const closePopover = () => { activePopover.value = null }

function onOutside(e) {
    if (popoverRef.value && !popoverRef.value.contains(e.target)) closePopover()
}
onMounted(() => document.addEventListener('mousedown', onOutside))
onUnmounted(() => document.removeEventListener('mousedown', onOutside))

// ── Ações rápidas ───────────────────────────────────────────────────────────
const quickConfirm = (appt) =>
    router.patch(route('appointments.update-status', appt.id), { status: 'confirmed' },
        { onSuccess: () => { closePopover(); router.reload({ only: ['appointments'] }) } })

const quickCheckin = (appt) =>
    router.post(route('appointments.check-in', appt.id), {},
        { preserveScroll: true, onSuccess: closePopover })

function clickSlot(day, e) {
    if (activePopover.value) { closePopover(); return }
    if (e.target !== e.currentTarget && e.target.closest('[data-appt]')) return
    const rect = e.currentTarget.getBoundingClientRect()
    const minutes = Math.floor((e.clientY - rect.top) / 30) * 30
    const total = START_HOUR * 60 + minutes
    const h = String(Math.floor(total / 60)).padStart(2, '0')
    const m = String(total % 60).padStart(2, '0')
    router.get(route('appointments.create'), { date: toDateStr(day), time: `${h}:${m}` })
}
</script>

<template>
<div class="h-screen flex flex-col bg-slate-50 overflow-hidden">

  <!-- ── Barra superior ─────────────────────────────────────────────────── -->
  <div class="flex items-center gap-2 px-4 py-2 border-b bg-white flex-shrink-0">

    <!-- Voltar -->
    <Link :href="route('appointments.index', { week: weekStart })"
          class="p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
          title="Sair da tela cheia">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
      </svg>
    </Link>

    <!-- Navegação semanal -->
    <div class="flex items-center gap-1">
      <button @click="navWeek(-1)" class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <button @click="goToday" class="px-3 py-1 text-xs font-medium rounded-md border border-slate-200 hover:bg-slate-50 transition-colors">
        Hoje
      </button>
      <button @click="navWeek(1)" class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

    <span class="text-sm font-semibold text-slate-700">{{ weekLabel }}</span>

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
    <select v-model="profFilter"
            class="text-xs border border-slate-200 rounded-md px-2 py-1.5 text-slate-600 focus:outline-none bg-white">
      <option value="">Todos</option>
      <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
    </select>

    <!-- Novo agendamento -->
    <Link :href="route('appointments.create')"
          class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors">
      + Novo
    </Link>
  </div>

  <!-- ── Grade do calendário (ocupa todo o espaço restante) ─────────────── -->
  <div class="flex-1 overflow-auto">

    <!-- Cabeçalho dos dias (sticky) -->
    <div class="flex bg-white border-b sticky top-0 z-20" style="min-width: max-content">
      <div class="w-14 flex-shrink-0 border-r" />
      <div v-for="day in visibleDays" :key="'fhd-' + toDateStr(day)"
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
        <div v-for="h in hours" :key="'fth-' + h"
             class="absolute right-0 pr-2 -translate-y-1/2 text-[10px] text-slate-400 text-right"
             :style="{ top: `${(h - START_HOUR) * 60}px` }">
          {{ String(h).padStart(2, '0') }}:00
        </div>
      </div>

      <!-- Colunas dos dias -->
      <div v-for="day in visibleDays" :key="'fdc-' + toDateStr(day)"
           class="flex-1 min-w-[130px] relative border-r last:border-r-0"
           :class="isToday(day) ? 'bg-blue-50/20' : 'bg-white'"
           @click="clickSlot(day, $event)">

        <div v-for="h in hours" :key="'fhl-' + h"
             class="absolute w-full border-t border-slate-100"
             :style="{ top: `${(h - START_HOUR) * 60}px` }" />
        <div v-for="h in hours.slice(0, -1)" :key="'fhhl-' + h"
             class="absolute w-full border-t border-dashed border-slate-100"
             :style="{ top: `${(h - START_HOUR) * 60 + 30}px` }" />

        <!-- Linha do tempo atual -->
        <div v-if="isToday(day) && nowTop >= 0 && nowTop <= TOTAL_MIN"
             class="absolute left-0 right-0 z-10 pointer-events-none flex items-center"
             :style="{ top: `${nowTop}px` }">
          <div class="w-2 h-2 rounded-full bg-red-500 -ml-1 flex-shrink-0 shadow-sm" />
          <div class="flex-1 h-px bg-red-400" />
        </div>

        <!-- Cards -->
        <div v-for="appt in byDay[toDateStr(day)]" :key="appt.id"
             data-appt="1"
             class="absolute rounded border-l-[3px] overflow-visible cursor-pointer select-none hover:shadow-md transition-shadow"
             :class="[st(appt.status).bg, st(appt.status).border]"
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
            <div class="text-[10px] font-semibold leading-tight truncate" :class="st(appt.status).text">
              {{ appt.patient?.nome }} {{ appt.patient?.sobrenome }}
            </div>
            <div v-if="apptH(appt) >= 28" class="text-[9px] text-slate-500 truncate leading-tight">
              {{ appt.treatment?.nome }}
            </div>
            <div v-if="apptH(appt) >= 44" class="text-[9px] text-slate-400 leading-tight">
              {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Popover ──────────────────────────────────────────────────────────── -->
<Teleport to="body">
  <div v-if="activePopover"
       ref="popoverRef"
       class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden"
       :style="popoverStyle">

    <div class="px-4 py-3 border-b" :class="st(activePopover.status).bg">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="font-semibold text-sm text-slate-800 leading-tight truncate">
            {{ activePopover.patient?.nome }} {{ activePopover.patient?.sobrenome }}
          </div>
          <div class="text-xs text-slate-500 mt-0.5">
            {{ formatTime(activePopover.start) }} – {{ formatTime(activePopover.end) }}
            <span class="mx-1">·</span>{{ activePopover.treatment?.nome }}
          </div>
        </div>
        <button @click="closePopover" class="p-0.5 rounded hover:bg-black/10 text-slate-400 flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

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

    <div class="p-3 grid grid-cols-2 gap-2">
      <button v-if="activePopover.status === 'scheduled'"
              @click="quickConfirm(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 transition-colors">
        Confirmar
      </button>
      <button v-if="['scheduled', 'confirmed'].includes(activePopover.status)"
              @click="quickCheckin(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 transition-colors">
        Check-in
      </button>
      <Link v-if="activePopover.consultation"
            :href="route('consultations.show', activePopover.consultation.id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-violet-50 hover:bg-violet-100 text-violet-700 border border-violet-200 transition-colors">
        Prontuário
      </Link>
      <Link :href="route('appointments.edit', activePopover.id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors">
        Editar
      </Link>
      <Link :href="route('patients.show', activePopover.patient_id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors">
        Ver paciente
      </Link>
    </div>
  </div>
</Teleport>
</template>
