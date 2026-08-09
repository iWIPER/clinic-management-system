<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { resolveStatus, getDelayMinutes, sortByPriority } from '@/composables/useAppointmentStatus'
import { useAgendaSettings } from '@/composables/useAgendaSettings'

const props = defineProps({
    appointments: Array,
    professionals: Array,
    weekStart: String,
    filters: Object,
})

// ── Constantes do grid ─────────────────────────────────────────────────────
const START_HOUR = 7
const END_HOUR   = 21
const TOTAL_MIN  = (END_HOUR - START_HOUR) * 60 // 840

// ── Helpers de data ────────────────────────────────────────────────────────
const parseLocalDate = (str) => new Date(str + 'T00:00:00')
const addDays = (date, n) => { const d = new Date(date); d.setDate(d.getDate() + n); return d }
const toDateStr = (date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}
const PT_DAYS   = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const PT_MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
const dayIndex  = (date) => date.getDay() === 0 ? 6 : date.getDay() - 1
const formatHour = (h) => `${String(h).padStart(2, '0')}:00`
const formatTime = (str) => new Date(str).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
const formatCurrency = (val) => val != null ? `R$ ${Number(val).toFixed(2).replace('.', ',')}` : null
const isToday = (date) => {
    const t = new Date()
    return date.getDate() === t.getDate() &&
           date.getMonth() === t.getMonth() &&
           date.getFullYear() === t.getFullYear()
}
const todayStr = toDateStr(new Date())

// ── Settings persistidos ───────────────────────────────────────────────────
const settings = useAgendaSettings()

// ── Estado da UI ───────────────────────────────────────────────────────────
const showSidebar    = ref(true)
const showMiniCal    = ref(true)
const profFilter     = ref(props.filters?.professional_id || '')
const activePopover  = ref(null)
const popoverStyle   = ref({})
const popoverRef     = ref(null)
const showSettings   = ref(false)
const settingsBtnRef = ref(null)
const settingsPanelRef = ref(null)
const gridScrollRef  = ref(null)

// ── Modo de visualização (Semana | Dia) ────────────────────────────────────
const viewMode = computed({
    get: () => settings.viewMode,
    set: (v) => { settings.viewMode = v },
})

const selectedDay = ref((() => {
    const today = new Date()
    const ws    = parseLocalDate(props.weekStart)
    const we    = addDays(ws, 6)
    return (today >= ws && today <= we) ? today : ws
})())

const pendingDayAfterNav = ref(null)
watch(() => props.weekStart, () => {
    if (pendingDayAfterNav.value) {
        selectedDay.value = parseLocalDate(pendingDayAfterNav.value)
        pendingDayAfterNav.value = null
    }
})

// ── Zoom ───────────────────────────────────────────────────────────────────
const zoomLevel = computed({
    get: () => settings.zoomLevel,
    set: (v) => { settings.zoomLevel = Math.max(0.4, Math.min(3.0, Math.round(v * 10) / 10)) },
})
const pxPerMin = computed(() => zoomLevel.value)
const gridHeight = computed(() => TOTAL_MIN * pxPerMin.value)

// ── Datas da semana ────────────────────────────────────────────────────────
const weekDates = computed(() => {
    const monday = parseLocalDate(props.weekStart)
    return Array.from({ length: 7 }, (_, i) => addDays(monday, i))
})

const visibleDays = computed(() => {
    if (viewMode.value === 'day') return [selectedDay.value]
    const days = [...weekDates.value.slice(0, 5)]
    if (settings.showSaturday) days.push(weekDates.value[5])
    if (settings.showSunday)   days.push(weekDates.value[6])
    return days
})

const periodLabel = computed(() => {
    if (viewMode.value === 'day') {
        const d = selectedDay.value
        return `${PT_DAYS[dayIndex(d)]}, ${d.getDate()} de ${PT_MONTHS[d.getMonth()]} ${d.getFullYear()}`
    }
    const [first, last] = [weekDates.value[0], weekDates.value[6]]
    return first.getMonth() === last.getMonth()
        ? `${PT_MONTHS[first.getMonth()]} ${first.getFullYear()}`
        : `${PT_MONTHS[first.getMonth()].slice(0, 3)} – ${PT_MONTHS[last.getMonth()].slice(0, 3)} ${last.getFullYear()}`
})

const periodRangeLabel = computed(() => {
    if (viewMode.value === 'day') return null
    const [f, l] = [weekDates.value[0], weekDates.value[6]]
    return `${f.getDate()}/${f.getMonth() + 1} – ${l.getDate()}/${l.getMonth() + 1}`
})

// ── Linhas de hora ─────────────────────────────────────────────────────────
const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)

// ── Lógica de sobreposição ─────────────────────────────────────────────────
function assignColumns(list) {
    if (!list.length) return []
    const sorted = [...list].sort((a, b) => new Date(a.start) - new Date(b.start))
    const colEnds = []
    sorted.forEach(appt => {
        const s = new Date(appt.start).getTime()
        const e = new Date(appt.end).getTime()
        let col = colEnds.findIndex(end => end <= s)
        if (col === -1) col = colEnds.length
        colEnds[col] = e
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
        let list = props.appointments.filter(a => {
            const match = a.start.slice(0, 10) === ds
            return profFilter.value ? match && a.professional_id == profFilter.value : match
        })
        if (settings.hideCancelled) list = list.filter(a => a.status !== 'cancelled')
        map[ds] = assignColumns(list)
    })
    return map
})

// ── Posicionamento dos cards (zoom-aware) ──────────────────────────────────
function apptStyle(appt) {
    const s   = new Date(appt.start)
    const e   = new Date(appt.end)
    const top = ((s.getHours() - START_HOUR) * 60 + s.getMinutes()) * pxPerMin.value
    const dur = Math.max((e - s) / 60000, 15)
    const cw  = 100 / appt._totalCols
    return {
        position: 'absolute',
        top:    `${top}px`,
        height: `${dur * pxPerMin.value - 2}px`,
        left:   `calc(${appt._col * cw}% + 2px)`,
        width:  `calc(${cw}% - 4px)`,
        zIndex: activePopover.value?.id === appt.id ? 30 : 10,
    }
}

function apptHeightPx(appt) {
    const s = new Date(appt.start)
    const e = new Date(appt.end)
    return Math.max((e - s) / 60000, 15) * pxPerMin.value
}

// ── Configuração de status (cores premium) ─────────────────────────────────
const STATUS = {
    scheduled:     { label: 'Agendada',       bg: 'bg-blue-50',    border: 'border-l-blue-400',    text: 'text-blue-700' },
    confirmed:     { label: 'Confirmada',      bg: 'bg-green-50',   border: 'border-l-green-500',   text: 'text-green-700' },
    in_attendance: { label: 'Em atendimento',  bg: 'bg-orange-50',  border: 'border-l-orange-400',  text: 'text-orange-700' },
    completed:     { label: 'Concluída',       bg: 'bg-emerald-50', border: 'border-l-emerald-600', text: 'text-emerald-800' },
    cancelled:     { label: 'Cancelada',       bg: 'bg-slate-50',   border: 'border-l-slate-300',   text: 'text-slate-400' },
    no_show:       { label: 'Faltou',          bg: 'bg-red-50',     border: 'border-l-red-400',     text: 'text-red-600' },
}
const s = (status) => STATUS[status] ?? STATUS.scheduled

// ── Consultas passadas (dim) ───────────────────────────────────────────────
function isPastAppt(appt) {
    return settings.dimPastAppointments &&
           new Date(appt.end) < nowRef.value &&
           !['cancelled', 'no_show'].includes(appt.status)
}

// ── Navegação ──────────────────────────────────────────────────────────────
function navWeek(delta) {
    const d = parseLocalDate(props.weekStart)
    d.setDate(d.getDate() + delta * 7)
    router.get(route('appointments.index'),
        { week: toDateStr(d), professional_id: profFilter.value || undefined },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

function navPrev() {
    if (viewMode.value === 'day') {
        const prev = addDays(selectedDay.value, -1)
        const ws   = parseLocalDate(props.weekStart)
        if (prev >= ws) { selectedDay.value = prev; return }
        pendingDayAfterNav.value = toDateStr(prev)
        router.get(route('appointments.index'),
            { week: toDateStr(addDays(ws, -7)), professional_id: profFilter.value || undefined },
            { preserveState: true, only: ['appointments', 'weekStart'] })
    } else {
        navWeek(-1)
    }
}

function navNext() {
    if (viewMode.value === 'day') {
        const next = addDays(selectedDay.value, 1)
        const ws   = parseLocalDate(props.weekStart)
        const we   = addDays(ws, 6)
        if (next <= we) { selectedDay.value = next; return }
        pendingDayAfterNav.value = toDateStr(next)
        router.get(route('appointments.index'),
            { week: toDateStr(addDays(ws, 7)), professional_id: profFilter.value || undefined },
            { preserveState: true, only: ['appointments', 'weekStart'] })
    } else {
        navWeek(1)
    }
}

const goToday = () => {
    const today = new Date()
    router.get(route('appointments.index'),
        { professional_id: profFilter.value || undefined },
        { preserveState: true, only: ['appointments', 'weekStart'],
          onSuccess: () => { selectedDay.value = today } })
}

const onProfChange = () => router.get(route('appointments.index'),
    { week: props.weekStart, professional_id: profFilter.value || undefined },
    { preserveState: true, only: ['appointments'] })

// ── Linha do horário atual ─────────────────────────────────────────────────
const nowRef = ref(new Date())
let _clockTimer = null

const nowTop = computed(() =>
    ((nowRef.value.getHours() - START_HOUR) * 60 + nowRef.value.getMinutes()) * pxPerMin.value)

// ── Zoom via CTRL+Scroll ───────────────────────────────────────────────────
function onGridWheel(e) {
    const isCtrl = e.ctrlKey || e.metaKey
    if (settings.ctrlScrollZoom && !isCtrl) return
    if (!settings.ctrlScrollZoom && isCtrl) return
    e.preventDefault()

    const container = gridScrollRef.value
    if (!container) return

    const delta    = e.deltaY < 0 ? 0.1 : -0.1
    const oldZoom  = zoomLevel.value
    const newZoom  = Math.max(0.4, Math.min(3.0, Math.round((oldZoom + delta) * 10) / 10))
    if (newZoom === oldZoom) return

    const rect             = container.getBoundingClientRect()
    const mouseYInContent  = e.clientY - rect.top + container.scrollTop

    zoomLevel.value = newZoom

    nextTick(() => {
        container.scrollTop = mouseYInContent * (newZoom / oldZoom) - (e.clientY - rect.top)
    })
}

// ── Tooltip rico (hover) ───────────────────────────────────────────────────
const tooltipAppt  = ref(null)
const tooltipStyle = ref({})
let _tooltipTimer  = null

function showTooltipDelayed(appt, e) {
    clearTimeout(_tooltipTimer)
    if (activePopover.value) return
    _tooltipTimer = setTimeout(() => {
        tooltipAppt.value = appt
        positionTooltip(e)
    }, 280)
}

function hideTooltip() {
    clearTimeout(_tooltipTimer)
    tooltipAppt.value = null
}

function positionTooltip(e) {
    const TW  = 252
    const TH  = 320
    let left  = e.clientX + 14
    let top   = e.clientY - TH / 2
    if (left + TW > window.innerWidth  - 8) left = e.clientX - TW - 14
    if (left < 8) left = 8
    if (top  < 8) top  = 8
    if (top + TH > window.innerHeight - 8) top = window.innerHeight - TH - 8
    tooltipStyle.value = { left: `${left}px`, top: `${top}px`, width: `${TW}px` }
}

function tooltipTimeRemaining(appt) {
    const end  = new Date(appt.end)
    const diff = Math.floor((end - nowRef.value) / 60000)
    if (diff <= 0) return null
    if (diff < 60) return `${diff} min restantes`
    const h = Math.floor(diff / 60)
    const m = diff % 60
    return m ? `${h}h ${m}min restantes` : `${h}h restantes`
}

// ── Popover (clique) ───────────────────────────────────────────────────────
function openPopover(appt, e) {
    hideTooltip()
    if (activePopover.value?.id === appt.id) { activePopover.value = null; return }
    activePopover.value = appt
    const rect = e.currentTarget.getBoundingClientRect()
    const PW   = 300
    let left   = rect.right + 8
    if (left + PW > window.innerWidth - 8) left = rect.left - PW - 8
    if (left < 8) left = 8
    let top    = Math.min(rect.top, window.innerHeight - 360)
    if (top < 8) top = 8
    popoverStyle.value = { left: `${left}px`, top: `${top}px`, width: `${PW}px` }
}
const closePopover = () => { activePopover.value = null }

function onOutsideClick(e) {
    if (popoverRef.value && !popoverRef.value.contains(e.target)) closePopover()
}

function onOutsideSettings(e) {
    if (!showSettings.value) return
    if (settingsBtnRef.value?.contains(e.target))   return
    if (settingsPanelRef.value?.contains(e.target)) return
    showSettings.value = false
}

// ── Ações rápidas ──────────────────────────────────────────────────────────
const quickConfirm = (appt) =>
    router.patch(route('appointments.update-status', appt.id), { status: 'confirmed' },
        { preserveState: true, preserveScroll: true,
          onSuccess: () => { closePopover(); router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true }) } })

const quickCheckin = (appt) =>
    router.post(route('appointments.check-in', appt.id), {},
        { preserveScroll: true, onSuccess: closePopover })

// ── Criar agendamento clicando na grade ────────────────────────────────────
function clickSlot(day, e) {
    if (activePopover.value) { closePopover(); return }
    if (e.target !== e.currentTarget && e.target.closest('[data-appt]')) return
    const rect       = e.currentTarget.getBoundingClientRect()
    const minutes    = Math.floor(((e.clientY - rect.top) / pxPerMin.value) / 30) * 30
    const totalMin   = START_HOUR * 60 + minutes
    const h          = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m          = String(totalMin % 60).padStart(2, '0')
    router.get(route('appointments.create'), { date: toDateStr(day), time: `${h}:${m}` })
}

// ── Mini calendário ────────────────────────────────────────────────────────
const miniMonthDate = ref(new Date())

const miniDays = computed(() => {
    const year   = miniMonthDate.value.getFullYear()
    const month  = miniMonthDate.value.getMonth()
    const first  = new Date(year, month, 1)
    const last   = new Date(year, month + 1, 0)
    const offset = first.getDay() === 0 ? 6 : first.getDay() - 1
    const days   = []
    for (let i = 0; i < offset; i++)
        days.push({ date: new Date(year, month, 1 - offset + i), cur: false })
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

const isSelectedDay = (date) =>
    viewMode.value === 'day' && toDateStr(date) === toDateStr(selectedDay.value)

function jumpToDate(date) {
    const d    = new Date(date)
    const dow  = d.getDay()
    const ws   = new Date(d)
    ws.setDate(ws.getDate() + (dow === 0 ? -6 : 1 - dow))
    const wsStr = toDateStr(ws)

    if (wsStr === props.weekStart) {
        // Semana já carregada — apenas atualiza dia selecionado
        selectedDay.value = d
        if (viewMode.value !== 'day') viewMode.value = 'day'
        return
    }
    pendingDayAfterNav.value = toDateStr(d)
    if (viewMode.value !== 'day') viewMode.value = 'day'
    router.get(route('appointments.index'),
        { week: wsStr, professional_id: profFilter.value || undefined },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

function jumpToWeek(date) {
    const d   = new Date(date)
    const dow = d.getDay()
    d.setDate(d.getDate() + (dow === 0 ? -6 : 1 - dow))
    router.get(route('appointments.index'),
        { week: toDateStr(d) },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

function switchToDayView(day) {
    selectedDay.value = day
    settings.viewMode  = 'day'
}

// ── Banda de almoço (posição zoom-aware) ───────────────────────────────────
const lunchBandStyle = computed(() => {
    if (!settings.showLunchBand) return null
    const [lh, lm] = settings.lunchStart.split(':').map(Number)
    const [eh, em] = settings.lunchEnd.split(':').map(Number)
    const topMin    = (lh - START_HOUR) * 60 + lm
    const heightMin = (eh - lh) * 60 + (em - lm)
    return {
        top:    `${topMin    * pxPerMin.value}px`,
        height: `${heightMin * pxPerMin.value}px`,
    }
})

// ── Lista de hoje (sidebar) ────────────────────────────────────────────────
const todayAppointmentsSorted = computed(() => {
    const list = props.appointments.filter(a => a.start.slice(0, 10) === todayStr)
    return sortByPriority(list, nowRef.value)
})

const todayStats = computed(() => {
    const list    = props.appointments.filter(a => a.start.slice(0, 10) === todayStr)
    const usedMin = list.reduce((sum, a) => sum + (new Date(a.end) - new Date(a.start)) / 60000, 0)
    return {
        total:     list.length,
        confirmed: list.filter(a => a.status === 'confirmed').length,
        cancelled: list.filter(a => a.status === 'cancelled').length,
        no_show:   list.filter(a => a.status === 'no_show').length,
        occupancy: Math.min(Math.round((usedMin / TOTAL_MIN) * 100), 100),
    }
})

// ── Exportar CSV ───────────────────────────────────────────────────────────
function exportCSV() {
    const appts = [...props.appointments]
        .sort((a, b) => new Date(a.start) - new Date(b.start))
    const rows = [['Data', 'Horário', 'Paciente', 'Telefone', 'Tratamento', 'Profissional', 'Status', 'Observações']]
    appts.forEach(a => rows.push([
        a.start.slice(0, 10),
        `${formatTime(a.start)}-${formatTime(a.end)}`,
        `${a.patient?.nome || ''} ${a.patient?.sobrenome || ''}`.trim(),
        a.patient?.telefone || '',
        a.treatment?.nome || '',
        a.professional?.name || '',
        STATUS[a.status]?.label || a.status,
        a.notes || '',
    ]))
    const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
    const url  = URL.createObjectURL(blob)
    const el   = document.createElement('a')
    el.href     = url
    el.download = `agenda-${todayStr}.csv`
    el.click()
    URL.revokeObjectURL(url)
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
let _pollTimer = null

onMounted(() => {
    document.addEventListener('mousedown', onOutsideClick)
    document.addEventListener('mousedown', onOutsideSettings)
    _clockTimer = setInterval(() => { nowRef.value = new Date() }, 30000)
    _pollTimer  = setInterval(() => {
        router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true })
    }, 30000)
    nextTick(() => {
        gridScrollRef.value?.addEventListener('wheel', onGridWheel, { passive: false })
    })
})

onUnmounted(() => {
    document.removeEventListener('mousedown', onOutsideClick)
    document.removeEventListener('mousedown', onOutsideSettings)
    clearInterval(_clockTimer)
    clearInterval(_pollTimer)
    clearTimeout(_tooltipTimer)
    gridScrollRef.value?.removeEventListener('wheel', onGridWheel)
})
</script>

<template>
<AppLayout>
<div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 flex flex-col no-print" style="min-height: calc(100vh - var(--app-navbar-h) - 1.5rem)">

  <!-- ── Barra de ferramentas ────────────────────────────────────────────── -->
  <div class="flex items-center gap-2 px-4 py-2 border-b bg-white flex-shrink-0 flex-wrap no-print">

    <!-- Toggle sidebar -->
    <button @click="showSidebar = !showSidebar"
            class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            :title="showSidebar ? 'Recolher painel' : 'Expandir painel'">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              :d="showSidebar ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'" />
      </svg>
    </button>

    <!-- Navegação -->
    <div class="flex items-center gap-1">
      <button @click="navPrev"
              class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <button @click="goToday"
              class="px-3 py-1 text-xs font-medium rounded-md border border-slate-200 hover:bg-slate-50 transition-colors">
        Hoje
      </button>
      <button @click="navNext"
              class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

    <!-- Label do período -->
    <span class="text-sm font-semibold text-slate-700">{{ periodLabel }}</span>
    <span v-if="periodRangeLabel" class="text-xs text-slate-400 hidden sm:inline">
      {{ periodRangeLabel }}
    </span>

    <div class="flex-1" />

    <!-- View toggle: Semana | Dia -->
    <div class="hidden sm:flex rounded-lg overflow-hidden border border-slate-200 text-xs">
      <button @click="viewMode = 'week'"
              class="px-3 py-1.5 font-medium transition-colors"
              :class="viewMode === 'week' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
        Semana
      </button>
      <button @click="viewMode = 'day'"
              class="px-3 py-1.5 font-medium transition-colors border-l border-slate-200"
              :class="viewMode === 'day' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
        Dia
      </button>
    </div>

    <!-- Sábado / Domingo (só na visão semana) -->
    <template v-if="viewMode === 'week'">
      <button @click="settings.showSaturday = !settings.showSaturday"
              class="hidden sm:block px-2.5 py-1 text-xs rounded-full border transition-colors"
              :class="settings.showSaturday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
        Sáb
      </button>
      <button @click="settings.showSunday = !settings.showSunday"
              class="hidden sm:block px-2.5 py-1 text-xs rounded-full border transition-colors"
              :class="settings.showSunday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
        Dom
      </button>
    </template>

    <!-- Zoom controls -->
    <div class="hidden sm:flex items-center gap-0.5 text-slate-500">
      <button @click="zoomLevel = zoomLevel - 0.1"
              class="w-6 h-6 flex items-center justify-center rounded hover:bg-slate-100 text-base leading-none transition-colors"
              title="Reduzir zoom">−</button>
      <span class="text-[10px] tabular-nums w-8 text-center">{{ Math.round(zoomLevel * 100) }}%</span>
      <button @click="zoomLevel = zoomLevel + 0.1"
              class="w-6 h-6 flex items-center justify-center rounded hover:bg-slate-100 text-base leading-none transition-colors"
              title="Aumentar zoom">+</button>
    </div>

    <!-- Filtro profissional -->
    <select v-model="profFilter" @change="onProfChange"
            class="text-xs border border-slate-200 rounded-md px-2 py-1.5 text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-400 bg-white">
      <option value="">Todos</option>
      <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
    </select>

    <!-- Exportar CSV -->
    <button @click="exportCSV"
            class="hidden md:flex items-center gap-1 p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
            title="Exportar CSV">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
    </button>

    <!-- Imprimir -->
    <button @click="window.print()"
            class="hidden md:flex items-center gap-1 p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
            title="Imprimir agenda">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
      </svg>
    </button>

    <!-- Configurações -->
    <div class="relative">
      <button ref="settingsBtnRef"
              @click="showSettings = !showSettings"
              class="p-1.5 rounded-md transition-colors"
              :class="showSettings ? 'bg-slate-100 text-slate-700' : 'hover:bg-slate-100 text-slate-400 hover:text-slate-600'"
              title="Configurações da agenda">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </button>

      <!-- Painel de configurações -->
      <Transition name="settings-drop">
        <div v-if="showSettings"
             ref="settingsPanelRef"
             class="absolute right-0 top-9 w-60 bg-white rounded-xl shadow-2xl border border-slate-200/80 py-2 z-50">

          <div class="px-3 pb-1.5 border-b border-slate-100">
            <span class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Agenda</span>
          </div>

          <div class="px-3 py-1.5 space-y-0.5">
            <label v-for="(opt) in [
                { key: 'hideCancelled',       label: 'Ocultar canceladas' },
                { key: 'showNowLine',         label: 'Mostrar horário atual' },
                { key: 'showSecondaryGrid',   label: 'Mostrar grade de meia hora' },
                { key: 'showLunchBand',       label: 'Mostrar horário de almoço' },
                { key: 'dimPastAppointments', label: 'Esmaece consultas passadas' },
                { key: 'compactMode',         label: 'Modo compacto' },
                { key: 'ctrlScrollZoom',      label: 'Zoom com CTRL+Scroll' },
            ]" :key="opt.key"
                   class="flex items-center justify-between py-1.5 cursor-pointer select-none">
              <span class="text-xs text-slate-600">{{ opt.label }}</span>
              <button @click="settings[opt.key] = !settings[opt.key]"
                      class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors duration-150 flex-shrink-0"
                      :class="settings[opt.key] ? 'bg-emerald-500' : 'bg-slate-200'">
                <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform duration-150"
                      :class="settings[opt.key] ? 'translate-x-3.5' : 'translate-x-0.5'" />
              </button>
            </label>
          </div>

          <div class="px-3 pt-1.5 border-t border-slate-100">
            <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 mb-1.5">Zoom</div>
            <input type="range" min="0.4" max="3.0" step="0.1"
                   :value="zoomLevel"
                   @input="zoomLevel = parseFloat($event.target.value)"
                   class="w-full h-1 accent-emerald-500" />
            <div class="text-[10px] text-slate-400 text-center mt-1">{{ Math.round(zoomLevel * 100) }}%</div>
          </div>
        </div>
      </Transition>
    </div>

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

  <!-- ── Corpo: sidebar + calendário ────────────────────────────────────── -->
  <div class="flex flex-1 overflow-hidden">

    <!-- ── Sidebar esquerda ─────────────────────────────────────────────── -->
    <transition name="agenda-sidebar">
      <div v-show="showSidebar"
           class="w-52 flex-shrink-0 border-r bg-white flex flex-col overflow-y-auto overflow-x-hidden no-print">

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
              <div v-for="(d, i) in ['S','T','Q','Q','S','S','D']" :key="i"
                   class="text-center text-[9px] font-medium text-slate-400">{{ d }}</div>
            </div>
            <!-- Dias -->
            <div class="grid grid-cols-7 gap-y-0.5">
              <button v-for="(day, i) in miniDays" :key="i"
                      @click="viewMode === 'day' ? jumpToDate(day.date) : jumpToWeek(day.date)"
                      class="flex items-center justify-center text-[10px] rounded leading-none py-0.5 transition-colors relative"
                      :class="{
                        'text-slate-300':                                !day.cur,
                        'text-slate-600 hover:bg-slate-100':             day.cur && !isInCurrentWeek(day.date) && !isToday(day.date) && !isSelectedDay(day.date),
                        'bg-emerald-50 text-emerald-600 font-medium':    day.cur && isInCurrentWeek(day.date) && viewMode === 'week',
                        'bg-emerald-500 text-white font-semibold rounded-full': day.cur && isSelectedDay(day.date),
                        'ring-1 ring-inset ring-emerald-400 rounded-full': day.cur && isToday(day.date) && !isSelectedDay(day.date),
                      }">
                {{ day.date.getDate() }}
              </button>
            </div>
          </div>
        </transition>

        <!-- Resumo diário -->
        <div class="px-3 py-3 flex-1 flex flex-col">
          <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 mb-2.5">Hoje</div>
          <div class="space-y-2">
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
          <div class="mt-3">
            <div class="flex justify-between text-xs mb-1">
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

          <!-- Fila de hoje -->
          <div v-if="todayAppointmentsSorted.length" class="mt-3 pt-3 border-t border-slate-100 flex-1">
            <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 mb-2">Fila de hoje</div>
            <div class="space-y-0.5">
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

    <!-- ── Grade do calendário ─────────────────────────────────────────── -->
    <div ref="gridScrollRef" class="flex-1 overflow-auto bg-slate-50/40 relative">

      <!-- Cabeçalho dos dias (sticky) -->
      <div class="flex bg-white border-b sticky top-0 z-20" style="min-width: max-content">
        <div class="w-14 flex-shrink-0 border-r bg-white" />
        <div v-for="day in visibleDays" :key="'hd-' + toDateStr(day)"
             class="flex-1 text-center py-2.5 border-r last:border-r-0 cursor-pointer transition-colors"
             :class="[
               isToday(day) ? 'bg-emerald-50/80' : 'hover:bg-slate-50',
               viewMode === 'week' ? 'min-w-[130px]' : 'min-w-[200px]',
             ]"
             @click="viewMode === 'week' && switchToDayView(day)">
          <div class="text-[10px] font-semibold uppercase tracking-wide"
               :class="isToday(day) ? 'text-emerald-500' : 'text-slate-400'">
            {{ PT_DAYS[dayIndex(day)] }}
          </div>
          <div class="text-lg font-bold leading-tight"
               :class="isToday(day) ? 'text-emerald-600' : 'text-slate-700'">
            {{ day.getDate() }}
          </div>
          <div v-if="viewMode === 'week'" class="text-[9px] text-slate-400 leading-tight">
            {{ PT_MONTHS[day.getMonth()].slice(0, 3) }}
          </div>
        </div>
      </div>

      <!-- Grade de tempo -->
      <div class="flex" :style="{ height: gridHeight + 'px', minWidth: 'max-content' }">

        <!-- Coluna de horas -->
        <div class="w-14 flex-shrink-0 border-r bg-white relative">
          <div v-for="h in hours" :key="'th-' + h"
               class="absolute right-0 pr-2 -translate-y-1/2 text-[10px] text-slate-400 text-right tabular-nums"
               :style="{ top: `${(h - START_HOUR) * 60 * pxPerMin}px` }">
            {{ formatHour(h) }}
          </div>
        </div>

        <!-- Colunas dos dias -->
        <div v-for="day in visibleDays" :key="'dc-' + toDateStr(day)"
             class="flex-1 relative border-r last:border-r-0"
             :class="[
               isToday(day) ? 'bg-blue-50/10' : 'bg-white',
               viewMode === 'week' ? 'min-w-[130px]' : 'min-w-[200px]',
             ]"
             @click="clickSlot(day, $event)">

          <!-- Linhas de hora -->
          <div v-for="h in hours" :key="'hl-' + h"
               class="absolute w-full border-t border-slate-100"
               :style="{ top: `${(h - START_HOUR) * 60 * pxPerMin}px` }" />

          <!-- Linhas de meia hora (opcional) -->
          <template v-if="settings.showSecondaryGrid">
            <div v-for="h in hours.slice(0, -1)" :key="'hhl-' + h"
                 class="absolute w-full border-t border-dashed border-slate-100/80"
                 :style="{ top: `${(h - START_HOUR) * 60 * pxPerMin + 30 * pxPerMin}px` }" />
          </template>

          <!-- Banda de almoço -->
          <div v-if="lunchBandStyle"
               class="absolute left-0 right-0 pointer-events-none z-[1] bg-slate-50/70 border-y border-slate-200/40"
               :style="lunchBandStyle">
            <span class="text-[9px] text-slate-300 px-1 select-none">almoço</span>
          </div>

          <!-- Linha do horário atual -->
          <div v-if="settings.showNowLine && isToday(day) && nowTop >= 0 && nowTop <= gridHeight"
               class="absolute left-0 right-0 z-10 pointer-events-none flex items-center"
               :style="{ top: `${nowTop}px` }">
            <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1.5 flex-shrink-0 shadow-md ring-2 ring-red-200" />
            <div class="flex-1 h-px bg-red-400/70" />
          </div>

          <!-- Cards de agendamento (premium) -->
          <div v-for="appt in byDay[toDateStr(day)]" :key="appt.id"
               data-appt="1"
               class="absolute border-l-[3px] overflow-visible cursor-pointer select-none
                      shadow-sm hover:shadow-lg ring-1 ring-black/[0.04]
                      transition-all duration-150 hover:-translate-y-px"
               :class="[
                 s(appt.status).bg,
                 s(appt.status).border,
                 settings.compactMode ? 'rounded-md' : 'rounded-lg',
                 isPastAppt(appt) ? 'opacity-60 saturate-50' : '',
               ]"
               :style="apptStyle(appt)"
               @mouseenter="showTooltipDelayed(appt, $event)"
               @mouseleave="hideTooltip"
               @click.stop="openPopover(appt, $event)">

            <!-- Indicador de status -->
            <div class="absolute top-1 right-1 z-20">
              <StatusIndicator
                :status="resolveStatus(appt, nowRef)"
                :delay-minutes="getDelayMinutes(appt, nowRef)"
                size="sm" />
            </div>

            <div class="h-full flex flex-col justify-start gap-px"
                 :class="settings.compactMode ? 'px-1 pt-0.5 pr-4' : 'px-1.5 pt-1 pr-4'">
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
                   class="text-[9px] text-slate-400 leading-tight tabular-nums">
                {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Tooltip rico (hover) — Teleport ──────────────────────────────────── -->
<Teleport to="body">
  <Transition name="tooltip-fade">
    <div v-if="tooltipAppt && !activePopover"
         class="fixed z-[80] pointer-events-none"
         :style="tooltipStyle">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden">

        <!-- Header colorido -->
        <div class="px-3 py-2.5 border-b" :class="s(tooltipAppt.status).bg">
          <div class="font-semibold text-sm text-slate-800 leading-tight truncate">
            {{ tooltipAppt.patient?.nome }} {{ tooltipAppt.patient?.sobrenome }}
          </div>
          <div class="text-[10px] text-slate-500 mt-0.5 tabular-nums">
            {{ formatTime(tooltipAppt.start) }} – {{ formatTime(tooltipAppt.end) }}
            <span v-if="tooltipTimeRemaining(tooltipAppt)" class="ml-1 text-emerald-600">
              · {{ tooltipTimeRemaining(tooltipAppt) }}
            </span>
          </div>
        </div>

        <!-- Detalhes -->
        <div class="px-3 py-2 space-y-1.5">
          <div v-if="tooltipAppt.treatment?.nome" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Procedimento</span>
            <span class="text-[10px] text-slate-700 leading-snug">{{ tooltipAppt.treatment.nome }}</span>
          </div>
          <div class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Profissional</span>
            <span class="text-[10px] text-slate-700">{{ tooltipAppt.professional?.name || '—' }}</span>
          </div>
          <div v-if="tooltipAppt.patient?.telefone" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Telefone</span>
            <span class="text-[10px] text-slate-700 tabular-nums">{{ tooltipAppt.patient.telefone }}</span>
          </div>
          <div class="flex gap-2 items-center">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Status</span>
            <StatusIndicator
              :status="resolveStatus(tooltipAppt, nowRef)"
              :delay-minutes="getDelayMinutes(tooltipAppt, nowRef)"
              show-label />
          </div>
          <div v-if="tooltipAppt.treatment?.preco_base" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Valor</span>
            <span class="text-[10px] text-slate-700 tabular-nums">{{ formatCurrency(tooltipAppt.treatment.preco_base) }}</span>
          </div>
          <div v-if="tooltipAppt.notes" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Obs.</span>
            <span class="text-[10px] text-slate-500 leading-snug line-clamp-2">{{ tooltipAppt.notes }}</span>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</Teleport>

<!-- ── Popover (clique) — Teleport ─────────────────────────────────────── -->
<Teleport to="body">
  <div v-if="activePopover"
       ref="popoverRef"
       class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden no-print"
       :style="popoverStyle">

    <!-- Header -->
    <div class="px-4 py-3 border-b" :class="s(activePopover.status).bg">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="font-semibold text-sm text-slate-800 leading-tight truncate">
            {{ activePopover.patient?.nome }} {{ activePopover.patient?.sobrenome }}
          </div>
          <div class="text-xs text-slate-500 mt-0.5 tabular-nums">
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
        <span class="text-xs text-slate-700 tabular-nums">{{ activePopover.patient.telefone }}</span>
      </div>
      <div v-if="activePopover.notes" class="flex items-start gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Obs.</span>
        <span class="text-xs text-slate-500 leading-snug">{{ activePopover.notes }}</span>
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

<!-- ── Layout de impressão (oculto na tela, visível ao imprimir) ─────────── -->
<div class="print-only hidden">
  <div class="print-header mb-6 pb-4 border-b-2 border-slate-300">
    <h1 class="text-2xl font-bold text-slate-800">Agenda</h1>
    <p class="text-slate-500 text-sm mt-1">{{ periodLabel }}</p>
    <p class="text-slate-400 text-xs mt-0.5">Gerado em {{ new Date().toLocaleDateString('pt-BR', { dateStyle: 'long' }) }}</p>
  </div>

  <div v-for="day in visibleDays" :key="'prt-' + toDateStr(day)" class="mb-8">
    <h2 class="text-base font-semibold text-slate-700 mb-3 pb-1 border-b border-slate-200">
      {{ PT_DAYS[dayIndex(day)] }}, {{ day.getDate() }} de {{ PT_MONTHS[day.getMonth()] }}
    </h2>
    <div v-if="byDay[toDateStr(day)]?.length" class="space-y-2">
      <div v-for="appt in byDay[toDateStr(day)]" :key="'prt-a-' + appt.id"
           class="flex gap-4 py-2 border-b border-slate-100 text-sm">
        <span class="text-slate-400 tabular-nums w-28 flex-shrink-0">
          {{ formatTime(appt.start) }} – {{ formatTime(appt.end) }}
        </span>
        <span class="font-medium text-slate-800 w-40 flex-shrink-0">
          {{ appt.patient?.nome }} {{ appt.patient?.sobrenome }}
        </span>
        <span class="text-slate-500 w-36 flex-shrink-0 truncate">{{ appt.treatment?.nome }}</span>
        <span class="text-slate-400 flex-shrink-0">{{ appt.professional?.name }}</span>
        <span class="text-slate-400 ml-auto flex-shrink-0">{{ s(appt.status).label }}</span>
      </div>
    </div>
    <p v-else class="text-slate-400 text-sm italic">Nenhum agendamento</p>
  </div>
</div>
</AppLayout>
</template>

<style scoped>
/* ── Sidebar collapse ─────────────────────────────────────────────────── */
.agenda-sidebar-enter-active,
.agenda-sidebar-leave-active {
  transition: max-width 0.22s ease, opacity 0.18s ease;
  overflow: hidden;
}
.agenda-sidebar-enter-from,
.agenda-sidebar-leave-to  { max-width: 0 !important; opacity: 0; }
.agenda-sidebar-enter-to,
.agenda-sidebar-leave-from { max-width: 13rem; opacity: 1; }

/* ── Mini calendário collapse ─────────────────────────────────────────── */
.agenda-collapse-enter-active,
.agenda-collapse-leave-active {
  transition: max-height 0.2s ease, opacity 0.15s ease;
  overflow: hidden;
}
.agenda-collapse-enter-from,
.agenda-collapse-leave-to  { max-height: 0; opacity: 0; }
.agenda-collapse-enter-to,
.agenda-collapse-leave-from { max-height: 300px; opacity: 1; }

/* ── Settings dropdown ────────────────────────────────────────────────── */
.settings-drop-enter-active,
.settings-drop-leave-active { transition: opacity 0.12s ease, transform 0.12s ease; }
.settings-drop-enter-from,
.settings-drop-leave-to     { opacity: 0; transform: translateY(-4px) scale(0.97); }
.settings-drop-enter-to,
.settings-drop-leave-from   { opacity: 1; transform: translateY(0) scale(1); }

/* ── Tooltip fade ─────────────────────────────────────────────────────── */
.tooltip-fade-enter-active,
.tooltip-fade-leave-active { transition: opacity 0.12s ease; }
.tooltip-fade-enter-from,
.tooltip-fade-leave-to     { opacity: 0; }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
  .no-print  { display: none !important; }
  .print-only { display: block !important; }

  body, html { font-size: 12px; }

  @page {
    margin: 1.5cm;
    size: A4 portrait;
  }
}
</style>
