<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import ChairFormModal from '@/Components/Agenda/ChairFormModal.vue'
import HolidayNoticeModal from '@/Components/Agenda/HolidayNoticeModal.vue'
import ScheduleBlockedModal from '@/Components/Agenda/ScheduleBlockedModal.vue'
import OffGridAppointmentsBadge from '@/Components/Agenda/OffGridAppointmentsBadge.vue'
import AppointmentFormModal from '@/Components/Agenda/AppointmentFormModal.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick, onMounted, onUnmounted, toRef } from 'vue'
import { PhoneIcon, EnvelopeIcon, CalendarDaysIcon, ClockIcon, ChevronDownIcon } from '@heroicons/vue/20/solid'
import { resolveStatus, getDelayMinutes, cardAppearance, STATUS_CONFIG, STATUS_DROPDOWN_OPTIONS } from '@/composables/useAppointmentStatus'
import { useAgendaSettings } from '@/composables/useAgendaSettings'
import { useAgendaDragSelect } from '@/composables/useAgendaDragSelect'
import { useAgendaScheduleRules, effectiveDayWindow, GRID_CEIL_HOUR } from '@/composables/useEffectiveSchedule'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    appointments: Array,
    professionals: Array,
    chairs: Array,
    availableMarkers: { type: Array, default: () => [] },
    markerLimit: { type: Number, default: 6 },
    maxChairs: { type: Number, default: 6 },
    weekStart: String,
    filters: Object,
    considerNationalHolidays: { type: Boolean, default: false },
    holidays: { type: Object, default: () => ({}) },
    businessHours: { type: Object, default: () => ({}) },
    businessHoursEnforced: { type: Boolean, default: false },
})

// ── Constantes ──────────────────────────────────────────────────────────────
// END_HOUR é o teto ABSOLUTO da grade (nunca ultrapassado) — gridStartHour é
// dinâmico (ver bloco "Regras administrativas" mais abaixo, depois que
// visibleDays/selectedProfessional existem). Mesma composable de Index.vue,
// única fonte da regra no frontend.
const END_HOUR = GRID_CEIL_HOUR

// ── Helpers ─────────────────────────────────────────────────────────────────
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
// Mesmo corte de nome de Index.vue — nome completo continua disponível no
// hover (tooltip) e no clique (popover).
const shortPatientName = (appt) => {
    const full = `${appt.patient?.nome || ''} ${appt.patient?.sobrenome || ''}`.trim()
    return full.split(/\s+/).filter(Boolean).slice(0, 3).join(' ')
}
const isToday = (date) => {
    const t = new Date()
    return date.getDate() === t.getDate() && date.getMonth() === t.getMonth() && date.getFullYear() === t.getFullYear()
}

// ── Settings (compartilhados com Index.vue) ──────────────────────────────────
const settings = useAgendaSettings()

// ── Estado ─────────────────────────────────────────────────────────────────
// 'all' = Todos/Todas (mesmo sentinel de Index.vue) — sem filtro na URL já
// vem 'all' do backend (ver AppointmentController::fullscreen).
const profFilter     = ref(props.filters?.professional_id || 'all')
const chairFilter    = ref(props.filters?.chair_id || 'all')
const chairsList     = ref([...(props.chairs || [])])
const showChairModal = ref(false)
const editingChair   = ref(null)
const showSidebar        = ref(true)
const showMiniCal        = ref(true)
// Dia destacado na grade e no mini-calendário (fullscreen é sempre
// "semana" — não existe visão Dia aqui) — clicar num cabeçalho ou num dia
// do mini-calendário só marca esse dia, não muda a semana sozinho. Mesma
// lógica de Index.vue: cai em "hoje" se hoje estiver na semana carregada
// (comparando por string de data, não Date — ver comentário lá), senão
// cai na segunda-feira da semana carregada.
const selectedDay = ref((() => {
    const today = new Date()
    const ws    = parseLocalDate(props.weekStart)
    const we    = addDays(ws, 6)
    const t     = toDateStr(today)
    return (t >= toDateStr(ws) && t <= toDateStr(we)) ? today : ws
})())
const isSelectedDay = (date) => toDateStr(date) === toDateStr(selectedDay.value)
// Guarda o dia clicado quando ele exige trocar de semana (a navegação é
// assíncrona — só dá pra marcar selectedDay depois que weekStart mudar).
const pendingDayAfterNav = ref(null)
const showChairsSection  = ref(true)
const showAgendasSection = ref(true)
const activePopover = ref(null)
const popoverStyle  = ref({})
const popoverRef    = ref(null)
const gridScrollRef = ref(null)

// ── Zoom ───────────────────────────────────────────────────────────────────
// Mesmos limites de Appointments/Index.vue — os dois compartilham o mesmo
// settings.zoomLevel persistido (useAgendaSettings), então o limite precisa
// valer aqui também, senão essa tela vira um atalho pra ultrapassá-lo.
const ZOOM_MIN  = 0.9
const ZOOM_MAX  = 1.8
const ZOOM_STEP = 0.1
const clampZoom = (v) => Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, Math.round(v * 10) / 10))

const zoomLevel = computed({
    get: () => clampZoom(settings.zoomLevel),
    set: (v) => { settings.zoomLevel = clampZoom(v) },
})
const isZoomAtMin = computed(() => zoomLevel.value <= ZOOM_MIN)
const isZoomAtMax = computed(() => zoomLevel.value >= ZOOM_MAX)
const pxPerMin   = computed(() => zoomLevel.value)
const gridHeight = computed(() => TOTAL_MIN.value * pxPerMin.value)

// ── Datas ─────────────────────────────────────────────────────────────────
const weekDates = computed(() => {
    const monday = parseLocalDate(props.weekStart)
    return Array.from({ length: 7 }, (_, i) => addDays(monday, i))
})

// Mesma composição de Index.vue: dia de trabalho do profissional filtrado
// (quando houver um específico selecionado) some da grade sem esconder o
// dia globalmente — "Todos" nunca aplica esse filtro (ver regra aprovada).
const WORKING_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

const selectedProfessional = computed(() =>
    profFilter.value !== 'all'
        ? props.professionals.find(p => String(p.id) === String(profFilter.value))
        : null)

const visibleDays = computed(() => {
    const days = [...weekDates.value.slice(0, 5)]
    if (settings.showSaturday) days.push(weekDates.value[5])
    if (settings.showSunday)   days.push(weekDates.value[6])

    const workingDays = selectedProfessional.value?.working_days
    if (!workingDays) return days

    const filtered = days.filter(d => workingDays[WORKING_DAY_KEYS[dayIndex(d)]] !== false)
    return filtered.length ? filtered : days
})

// ── Regras administrativas da clínica (horário/dia efetivo) — mesma
// composable de Index.vue, única fonte da regra no frontend (ver
// useEffectiveSchedule.js). Só decide a APARÊNCIA da grade; o bloqueio real
// continua no backend (AppointmentController::assertProfessionalAvailable).
const scheduleRules = useAgendaScheduleRules({
    visibleDays,
    getProfessionalScopeForDay: () => selectedProfessional.value
        ? { working_days: selectedProfessional.value.working_days, working_hours: selectedProfessional.value.working_hours }
        : null,
    considerNationalHolidays: toRef(props, 'considerNationalHolidays'),
    holidays: toRef(props, 'holidays'),
    businessHours: toRef(props, 'businessHours'),
    businessHoursEnforced: toRef(props, 'businessHoursEnforced'),
    toDateStr,
})
const gridStartHour = scheduleRules.gridStartHour
const dayWindow = scheduleRules.dayWindow

const weekLabel = computed(() => {
    const [f, l] = [weekDates.value[0], weekDates.value[6]]
    return f.getMonth() === l.getMonth()
        ? `${PT_MONTHS[f.getMonth()]} ${f.getFullYear()}`
        : `${PT_MONTHS[f.getMonth()].slice(0, 3)} – ${PT_MONTHS[l.getMonth()].slice(0, 3)} ${l.getFullYear()}`
})

const TOTAL_MIN = computed(() => (END_HOUR - gridStartHour.value) * 60)
const hours = computed(() => Array.from({ length: END_HOUR - gridStartHour.value }, (_, i) => gridStartHour.value + i))

// ── Sobreposição ─────────────────────────────────────────────────────────
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
        let list = props.appointments.filter(a => {
            let ok = a.start.slice(0, 10) === ds
            if (profFilter.value !== 'all') ok = ok && a.professional_id == profFilter.value
            if (chairFilter.value !== 'all') ok = ok && a.chair_id == chairFilter.value
            return ok
        })
        if (settings.hideCancelled) list = list.filter(a => a.status !== 'cancelled')
        map[ds] = assignColumns(list)
    })
    return map
})

function apptStyle(appt) {
    const s   = new Date(appt.start)
    const e   = new Date(appt.end)
    const top = ((s.getHours() - gridStartHour.value) * 60 + s.getMinutes()) * pxPerMin.value
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

// ── Status — cardAppearance() (useAppointmentStatus.js) é a fonte única de
// cor, mesma de Index.vue, chaveada pelo status RESOLVIDO. ────────────────
const st = (appt) => cardAppearance(resolveStatus(appt, nowRef.value))

function isPastAppt(appt) {
    return settings.dimPastAppointments &&
           new Date(appt.end) < nowRef.value &&
           !['cancelled', 'no_show'].includes(appt.status)
}

// ── Navegação ────────────────────────────────────────────────────────────
const navWeek = (delta) => {
    const d = parseLocalDate(props.weekStart)
    d.setDate(d.getDate() + delta * 7)
    router.get(route('appointments.fullscreen'),
        { week: toDateStr(d), professional_id: profFilter.value, chair_id: chairFilter.value },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

const goToday = () => router.get(route('appointments.fullscreen'),
    { professional_id: profFilter.value, chair_id: chairFilter.value },
    { preserveState: true, only: ['appointments', 'weekStart'] })

// Reaproveitado pelas duas seções da sidebar (Agendas e Cadeiras) — mesmo
// padrão de Index.vue::onFilterChange.
const onFilterChange = () => router.get(route('appointments.fullscreen'),
    { week: props.weekStart, professional_id: profFilter.value, chair_id: chairFilter.value },
    { preserveState: true, only: ['appointments'] })

function jumpToWeek(date) {
    const d   = new Date(date)
    const dow = d.getDay()
    const ws  = new Date(d)
    ws.setDate(ws.getDate() + (dow === 0 ? -6 : 1 - dow))
    const wsStr = toDateStr(ws)

    if (wsStr === props.weekStart) {
        // Semana já carregada — só atualiza qual dia fica destacado.
        selectedDay.value = d
        return
    }
    pendingDayAfterNav.value = toDateStr(d)
    router.get(route('appointments.fullscreen'),
        { week: wsStr, professional_id: profFilter.value, chair_id: chairFilter.value },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

watch(() => props.weekStart, () => {
    if (pendingDayAfterNav.value) {
        selectedDay.value = parseLocalDate(pendingDayAfterNav.value)
        pendingDayAfterNav.value = null
    }
})

// ── Mini calendário ─────────────────────────────────────────────────────────
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

// ── Cadeiras (recursos) ──────────────────────────────────────────────────
const atChairLimit = computed(() => chairsList.value.length >= props.maxChairs)

function openCreateChairModal() {
    if (atChairLimit.value) {
        toast.info(`Sua clínica já possui o máximo de ${props.maxChairs} cadeiras.`)
        return
    }
    editingChair.value = null
    showChairModal.value = true
}
function openEditChairModal(chair) {
    editingChair.value = chair
    showChairModal.value = true
}
function onChairSaved(chair) {
    const i = chairsList.value.findIndex(c => c.id === chair.id)
    if (i === -1) chairsList.value = [...chairsList.value, chair]
    else chairsList.value = chairsList.value.map((c, idx) => idx === i ? chair : c)
    showChairModal.value = false
}
function onChairDeleted(id) {
    chairsList.value = chairsList.value.filter(c => c.id !== id)
    showChairModal.value = false
    if (String(chairFilter.value) === String(id)) {
        chairFilter.value = 'all'
        onFilterChange()
    } else {
        router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true })
    }
}

// ── Modal de novo agendamento — mesmo componente/comportamento de
// Index.vue (ver comentário lá): substitui a navegação pra Appointments/
// Create.vue, mantendo o usuário na própria tela cheia.
const showApptModal    = ref(false)
const apptModalPrefill = ref({})
const editingAppointment = ref(null)

function openApptModal(prefill = {}) {
    editingAppointment.value = null
    apptModalPrefill.value = prefill
    showApptModal.value = true
}

// "Editar" do popover — mesmo modal de criação, agora em modo edição (ver
// AppointmentFormModal.vue, prop `appointment`), mesmo critério de Index.vue.
function openEditApptModal(appt) {
    editingAppointment.value = appt
    showApptModal.value = true
    closePopover()
}

// "+ Novo" herda a cadeira/agenda em foco — mesmo critério de Index.vue.
function openNewAppointmentModal() {
    openApptModal({
        chairId: chairFilter.value !== 'all' ? chairFilter.value : undefined,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
}

// ── Linha do horário atual ────────────────────────────────────────────────
const nowRef = ref(new Date())
let _t = null
onMounted(() => { _t = setInterval(() => { nowRef.value = new Date() }, 30000) })
onUnmounted(() => { clearInterval(_t); gridScrollRef.value?.removeEventListener('wheel', onGridWheel) })

const nowTop = computed(() =>
    ((nowRef.value.getHours() - gridStartHour.value) * 60 + nowRef.value.getMinutes()) * pxPerMin.value)

// ── Zoom via CTRL+Scroll ───────────────────────────────────────────────────
function onGridWheel(e) {
    const isCtrl = e.ctrlKey || e.metaKey
    if (settings.ctrlScrollZoom && !isCtrl) return
    if (!settings.ctrlScrollZoom && isCtrl) return
    e.preventDefault()
    const container = gridScrollRef.value
    if (!container) return
    const delta   = e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP
    const oldZoom = zoomLevel.value
    const newZoom = clampZoom(oldZoom + delta)
    if (newZoom === oldZoom) return
    const rect            = container.getBoundingClientRect()
    const mouseYInContent = e.clientY - rect.top + container.scrollTop
    zoomLevel.value = newZoom
    nextTick(() => {
        container.scrollTop = mouseYInContent * (newZoom / oldZoom) - (e.clientY - rect.top)
    })
}

// ── Tooltip ────────────────────────────────────────────────────────────────
const tooltipAppt  = ref(null)
const tooltipStyle = ref({})
let _tooltipTimer  = null

function showTooltipDelayed(appt, e) {
    clearTimeout(_tooltipTimer)
    if (activePopover.value) return
    _tooltipTimer = setTimeout(() => {
        tooltipAppt.value = appt
        const TW = 252
        const TH = 280
        let left = e.clientX + 14
        let top  = e.clientY - TH / 2
        if (left + TW > window.innerWidth - 8) left = e.clientX - TW - 14
        if (left < 8) left = 8
        if (top  < 8) top  = 8
        if (top + TH > window.innerHeight - 8) top = window.innerHeight - TH - 8
        tooltipStyle.value = { left: `${left}px`, top: `${top}px`, width: `${TW}px` }
    }, 280)
}

function hideTooltip() { clearTimeout(_tooltipTimer); tooltipAppt.value = null }

// ── Popover ────────────────────────────────────────────────────────────────
function openPopover(appt, e) {
    hideTooltip()
    if (activePopover.value?.id === appt.id) { activePopover.value = null; return }
    activePopover.value = appt
    const rect = e.currentTarget.getBoundingClientRect()
    const PW   = 340
    let left   = rect.right + 8
    if (left + PW > window.innerWidth - 8) left = rect.left - PW - 8
    if (left < 8) left = 8
    // Reserva mais altura que antes (popover cresceu: status em destaque,
    // data/hora em linha própria, telefone/e-mail) — ainda um teto
    // aproximado, o popover pode ser mais curto que isso sem problema.
    let top    = Math.min(rect.top, window.innerHeight - 480)
    if (top < 8) top = 8
    popoverStyle.value = { left: `${left}px`, top: `${top}px`, width: `${PW}px` }
}
const closePopover = () => { activePopover.value = null; statusMenuOpenFor.value = null }

function onOutside(e) {
    if (popoverRef.value && !popoverRef.value.contains(e.target)) closePopover()
}
onMounted(() => {
    document.addEventListener('mousedown', onOutside)
    nextTick(() => { gridScrollRef.value?.addEventListener('wheel', onGridWheel, { passive: false }) })
})
onUnmounted(() => {
    document.removeEventListener('mousedown', onOutside)
    clearTimeout(_tooltipTimer)
})

// ── Ações rápidas ─────────────────────────────────────────────────────────
// Único ponto que chama appointments.update-status — botões e o dropdown de
// status do popover (ver STATUS_DROPDOWN_OPTIONS) reutilizam esta mesma
// função, nunca duas implementações da mesma mudança de status.
function changeStatus(appt, status) {
    router.patch(route('appointments.update-status', appt.id), { status },
        { preserveState: true, preserveScroll: true,
          onSuccess: () => { closePopover(); router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true }) } })
}

const quickConfirm = (appt) => changeStatus(appt, 'confirmed')

// Check-in continua com fluxo próprio (cria/atualiza Consultation, não é só
// um status) — endpoint e comportamento inalterados.
const quickCheckin = (appt) =>
    router.post(route('appointments.check-in', appt.id), {},
        { preserveScroll: true, onSuccess: closePopover })

// Cancelar/Faltou nunca apagam o agendamento (ver AppointmentController::
// updateStatus), só mudam o status; cancelado/faltou já não bloqueiam mais
// disponibilidade (assertNoConflict exclui os dois).
const quickCancel = (appt) => {
    if (!confirm('Cancelar este agendamento? O horário será liberado, mas o registro continua no histórico do paciente.')) return
    changeStatus(appt, 'cancelled')
}

const quickNoShow = (appt) => {
    if (!confirm('Marcar esta consulta como falta do paciente? O horário será liberado, mas o registro continua no histórico do paciente.')) return
    changeStatus(appt, 'no_show')
}

// ── Dropdown de status do popover — mesmo critério de Index.vue.
const statusMenuOpenFor = ref(null)
function toggleStatusMenu(appt) {
    statusMenuOpenFor.value = statusMenuOpenFor.value === appt.id ? null : appt.id
}
function pickStatus(appt, status) {
    statusMenuOpenFor.value = null
    changeStatus(appt, status)
}

// ── Confirmação por WhatsApp/e-mail — só a interface por enquanto; não há
// infraestrutura de envio real no projeto, então o clique nunca finge um
// envio, só avisa que a funcionalidade ainda não está disponível.
function notifyContactComingSoon(channel) {
    toast.info(channel === 'whatsapp'
        ? 'Confirmação por WhatsApp ainda não está disponível.'
        : 'Confirmação por e-mail ainda não está disponível.')
}

function formatFullDate(iso) {
    const label = new Date(iso).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })
    return label.charAt(0).toUpperCase() + label.slice(1)
}

const showHolidayModal = ref(false)
const holidayModalInfo = ref({ name: '', dateLabel: '' })

// ── Bloqueio administrativo ao criar (mesmo critério de Index.vue) ───────
const showScheduleBlockedModal = ref(false)
const scheduleBlockedInfo = ref({ title: '', message: '' })

function attemptOpenApptModal(day, timeStr, extra = {}) {
    const holidayName = holidayNameFor(day)
    if (holidayName) {
        holidayModalInfo.value = { name: holidayName, dateLabel: `${day.getDate()} de ${PT_MONTHS[day.getMonth()]}` }
        showHolidayModal.value = true
        return
    }

    if (props.businessHoursEnforced) {
        const w = dayWindow(day)
        if (w.closed) {
            scheduleBlockedInfo.value = {
                title: 'Dia sem atendimento',
                message: 'Este dia está configurado pela clínica como dia sem atendimento.',
            }
            showScheduleBlockedModal.value = true
            return
        }
        if ((w.start && timeStr < w.start) || (w.end && timeStr >= w.end)) {
            scheduleBlockedInfo.value = {
                title: 'Fora do horário de atendimento',
                message: `A clínica está configurada para atender das ${w.start} às ${w.end} neste dia. Este horário está fora do período permitido para novos agendamentos.`,
            }
            showScheduleBlockedModal.value = true
            return
        }
    }

    openApptModal({ date: toDateStr(day), time: timeStr, ...extra })
}

// ── Seleção por arraste (estilo Excel) — mesmo composable de Index.vue,
// coluna aqui é sempre um dia (fullscreen não tem visão por cadeira). ─────
function openFromInterval(columnKey, startMinutesFromTop, durationMinutes) {
    const day = visibleDays.value.find(d => toDateStr(d) === columnKey)
    if (!day) return
    const totalMin = gridStartHour.value * 60 + startMinutesFromTop
    const h = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m = String(totalMin % 60).padStart(2, '0')
    attemptOpenApptModal(day, `${h}:${m}`, {
        durationMinutes,
        chairId: chairFilter.value !== 'all' ? chairFilter.value : undefined,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
}

const dragSelect = useAgendaDragSelect({
    pxPerMin,
    stepMinutes: 15,
    onSelect: openFromInterval,
})

function dragTimeLabel(yPx) {
    const minutes  = Math.round(yPx / pxPerMin.value / 15) * 15
    const totalMin = gridStartHour.value * 60 + Math.max(0, minutes)
    const h = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m = String(totalMin % 60).padStart(2, '0')
    return `${h}:${m}`
}

function clickSlot(day, e) {
    if (dragSelect.consumeDragFlag()) return
    if (activePopover.value) { closePopover(); return }
    if (e.target !== e.currentTarget && e.target.closest('[data-appt]')) return
    const rect     = e.currentTarget.getBoundingClientRect()
    const minutes  = Math.floor(((e.clientY - rect.top) / pxPerMin.value) / 15) * 15
    const total    = gridStartHour.value * 60 + minutes
    const h        = String(Math.floor(total / 60)).padStart(2, '0')
    const m        = String(total % 60).padStart(2, '0')
    attemptOpenApptModal(day, `${h}:${m}`, {
        chairId: chairFilter.value !== 'all' ? chairFilter.value : undefined,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
}

// ── Banda de almoço ────────────────────────────────────────────────────────
const lunchBandStyle = computed(() => {
    if (!settings.showLunchBand) return null
    const [lh, lm] = settings.lunchStart.split(':').map(Number)
    const [eh, em] = settings.lunchEnd.split(':').map(Number)
    const topMin    = (lh - gridStartHour.value) * 60 + lm
    const heightMin = (eh - lh) * 60 + (em - lm)
    return { top: `${topMin * pxPerMin.value}px`, height: `${heightMin * pxPerMin.value}px` }
})

// ── Feriados — regra da clínica, mesma lógica de Index.vue.
const holidayNameFor = (date) =>
    props.considerNationalHolidays ? (props.holidays[toDateStr(date)] || null) : null

// ── Fora do horário de atendimento — agora POR DIA, usando a janela efetiva
// (regra da clínica ∩ profissional, ver dayWindow acima). Generaliza o
// antigo outOfHoursBands (um único par pra semana toda, só com profissional
// selecionado): sem regra obrigatória ativa, resultado idêntico a antes; com
// regra obrigatória, também funciona no modo "Todos" e varia por dia.
function outOfHoursBandsFor(day) {
    const w = dayWindow(day)
    if (w.closed || !w.start || !w.end) return []
    const [sh, sm] = w.start.split(':').map(Number)
    const [eh, em] = w.end.split(':').map(Number)
    const startMin = (sh - gridStartHour.value) * 60 + sm
    const endMin   = (eh - gridStartHour.value) * 60 + em
    const bands = []
    if (startMin > 0)             bands.push({ top: 0, height: startMin, pos: 'before' })
    if (endMin < TOTAL_MIN.value) bands.push({ top: endMin, height: TOTAL_MIN.value - endMin, pos: 'after' })
    return bands.map(b => ({
        pos: b.pos,
        style: { top: `${b.top * pxPerMin.value}px`, height: `${b.height * pxPerMin.value}px` },
    }))
}

// Dia inteiro bloqueado pela regra da clínica (obrigatória) — diferente de
// feriado (que já tem overlay próprio); só dispara quando o motivo é
// especificamente "clínica fechou esse dia", pra não duplicar overlay.
function isClinicDayOff(day) {
    const w = dayWindow(day)
    return w.closed && w.reason === 'clinic-day-off'
}

// ── Agendamentos além do teto/piso absoluto da grade — nunca fazem a grade
// crescer, continuam acessíveis via OffGridAppointmentsBadge.
function isApptOffGrid(appt) {
    const s = new Date(appt.start)
    const hourFrac = s.getHours() + s.getMinutes() / 60
    return hourFrac < gridStartHour.value || hourFrac >= END_HOUR
}

function visibleApptsFor(day) {
    return (byDay.value[toDateStr(day)] || []).filter(a => !isApptOffGrid(a))
}

function offGridApptsFor(day) {
    return (byDay.value[toDateStr(day)] || []).filter(isApptOffGrid)
}

// ── Agendamento existente fora da janela efetiva ATUAL — usa o profissional
// DONO do agendamento (não o filtro selecionado), pra funcionar igual em
// "Todos" e numa agenda específica. Só informativo: nunca bloqueia, move ou
// cancela o agendamento.
function apptScheduleNotice(appt) {
    if (!props.businessHoursEnforced) return null
    const prof = props.professionals.find(p => p.id === appt.professional_id)
    const w = effectiveDayWindow({
        date: new Date(appt.start),
        holidayName: props.considerNationalHolidays ? (props.holidays[appt.start.slice(0, 10)] || null) : null,
        considerHolidays: props.considerNationalHolidays,
        clinicBusinessHours: props.businessHours,
        clinicEnforced: props.businessHoursEnforced,
        workingDays: prof?.working_days ?? null,
        workingHours: prof?.working_hours ?? null,
    })
    if (w.closed) return 'Este agendamento está fora do horário atual da clínica.'
    const startStr = formatTime(appt.start)
    const endStr   = formatTime(appt.end)
    if ((w.start && startStr < w.start) || (w.end && endStr > w.end)) {
        return 'Este agendamento está fora do horário atual da clínica.'
    }
    return null
}
</script>

<template>
<AppLayout content-width="screen">
<!-- Mesma superfície única de Index.vue (toolbar + sidebar + grade dentro
     de UMA borda/sombra/canto arredondado) — "tela cheia" aqui significa
     aproveitar melhor o espaço abaixo da navbar principal (que continua
     visível, ver AppLayout), não esconder o menu do Wildental. Mesmo
     -mt-3.5/34px de Index.vue pro respiro até a navbar. content-width="screen"
     é EXCLUSIVO desta tela — Index.vue (Agenda normal) usa o "full" padrão
     do sistema (max-w-7xl/1280px, igual Pacientes/Consultas), sem prop
     nenhuma. É essa diferença de largura entre os dois modos que faz
     "entrar"/"sair" da tela cheia parecer uma mudança de verdade. Além do
     token, aqui a gente também cancela o padding horizontal padrão do
     <main> do AppLayout (-mx-4 sm:-mx-6 lg:-mx-8) e devolve uma margem bem
     menor (px-2 sm:px-3 lg:px-4 = 8/12/16px), pra aproveitar o máximo
     possível do monitor. Sem nenhuma dessas diferenças, tela cheia e
     normal ocupavam exatamente a mesma largura e pareciam o mesmo modo (era a
     causa real do "fullscreen parece sempre ligado"). -->
<div class="-mt-3.5 -mx-4 sm:-mx-6 lg:-mx-8 px-2 sm:px-3 lg:px-4 flex flex-col bg-white border border-slate-200 rounded-2xl shadow-sm" style="min-height: calc(100vh - var(--app-navbar-h) - 34px)">

  <!-- ── Barra superior ─────────────────────────────────────────────────── -->
  <div class="flex items-center gap-2 px-4 py-2 border-b bg-white rounded-t-2xl flex-shrink-0 flex-wrap">

    <!-- Toggle sidebar — mesmo botão/ícone de Index.vue (duas setinhas que
         invertem sentido); sem classes "hidden", nunca some em tela estreita.
         Fica primeiro, igual a Index.vue — o lado esquerdo da toolbar
         (setinhas + navegação) é idêntico nos dois modos; só o ícone que
         alterna o MODO (Tela cheia ⇄ Sair da tela cheia) muda de "entrar" pra
         "sair", e por isso vive sempre no grupo fixo à direita (ver abaixo),
         nunca do lado esquerdo — antes ele ficava à esquerda aqui e à
         direita em Index.vue, fazendo a barra "pular" de layout ao trocar
         de modo. -->
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

    <!-- Zoom: mesmo controle agrupado de Index.vue (borda fina, cantos
         quadrados, sombra sutil) — não três elementos soltos. -->
    <div class="flex items-stretch border border-slate-200 bg-white shadow-sm">
      <button @click="zoomLevel = zoomLevel - ZOOM_STEP" :disabled="isZoomAtMin"
              class="w-7 h-7 flex items-center justify-center text-slate-500 text-base leading-none transition-colors hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
              title="Reduzir zoom">−</button>
      <span class="flex items-center justify-center w-11 text-[11px] font-medium tabular-nums text-slate-600 border-x border-slate-200">
        {{ Math.round(zoomLevel * 100) }}%
      </span>
      <button @click="zoomLevel = zoomLevel + ZOOM_STEP" :disabled="isZoomAtMax"
              class="w-7 h-7 flex items-center justify-center text-slate-500 text-base leading-none transition-colors hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
              title="Aumentar zoom">+</button>
    </div>

    <!-- Sábado / Domingo / Sair da tela cheia / Novo: mesmo grupo fixo à
         direita de Index.vue — não se separa nem troca de posição se a
         barra quebrar linha. -->
    <div class="flex items-center gap-2 ml-auto flex-shrink-0">
    <button @click="settings.showSaturday = !settings.showSaturday"
            class="px-2.5 py-1 text-xs rounded-full border transition-colors"
            :class="settings.showSaturday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
      Sáb
    </button>
    <button @click="settings.showSunday = !settings.showSunday"
            class="px-2.5 py-1 text-xs rounded-full border transition-colors"
            :class="settings.showSunday ? 'bg-slate-800 text-white border-slate-800' : 'text-slate-500 border-slate-200 hover:bg-slate-50'">
      Dom
    </button>

    <!-- Novo agendamento -->
    <button @click="openNewAppointmentModal"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors">
      + Novo
    </button>

    <!-- Sair da tela cheia — agora depois de "+ Novo" (mesma ordem de
         Index.vue); ml-8 soma ao gap-2 do grupo (~40px ≈ 1cm no total). -->
    <Link :href="route('appointments.index', { week: weekStart })"
          class="ml-8 p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
          title="Sair da tela cheia">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
      </svg>
    </Link>
    </div>
  </div>

  <!-- ── Corpo: sidebar da Agenda (Cadeiras/Agendas) + calendário ─────────
       A tela cheia ganha mais espaço reduzindo o "chrome" externo da
       aplicação (navbar, padding da página) — não escondendo a própria
       sidebar da Agenda, que é funcional (filtros de Cadeiras/Agendas). -->
  <div class="flex flex-1 overflow-hidden">

    <transition name="agenda-sidebar">
    <div v-show="showSidebar"
         class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50/40 rounded-bl-2xl flex flex-col gap-3 p-3 overflow-y-auto overflow-x-hidden">

      <!-- Card: Calendário -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden shrink-0">
      <button @click="showMiniCal = !showMiniCal"
              class="flex items-center justify-between w-full px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors">
        <span>Calendário</span>
        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
             :class="{ 'rotate-180': !showMiniCal }"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>

      <transition name="agenda-collapse">
        <div v-if="showMiniCal" class="px-3 pb-3 pt-1 border-t border-slate-100">
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
          <div class="grid grid-cols-7 mb-1">
            <div v-for="(d, i) in ['S','T','Q','Q','S','S','D']" :key="i"
                 class="text-center text-[9px] font-medium text-slate-400">{{ d }}</div>
          </div>
          <div class="grid grid-cols-7 gap-y-0.5">
            <button v-for="(day, i) in miniDays" :key="i"
                    @click="jumpToWeek(day.date)"
                    class="flex items-center justify-center text-[10px] rounded leading-none py-0.5 transition-colors relative"
                    :class="{
                      'text-slate-300':                                !day.cur,
                      'text-slate-600 hover:bg-slate-100':             day.cur && !isToday(day.date) && !isSelectedDay(day.date),
                      'bg-emerald-500 text-white font-semibold rounded-full': day.cur && isSelectedDay(day.date),
                      'ring-1 ring-inset ring-emerald-400 rounded-full': day.cur && isToday(day.date) && !isSelectedDay(day.date),
                    }">
              {{ day.date.getDate() }}
            </button>
          </div>
        </div>
      </transition>
      </div>
      <!-- /Card: Calendário -->

      <!-- Card: Cadeiras -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden shrink-0">
        <button @click="showChairsSection = !showChairsSection"
                class="flex items-center justify-between w-full px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors">
          <span>Cadeiras</span>
          <div class="flex items-center gap-1.5">
            <span title="Nova cadeira"
                  role="button"
                  @click.stop="openCreateChairModal"
                  class="rounded-md p-0.5 transition-colors"
                  :class="atChairLimit ? 'text-slate-300' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
            </span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                 :class="{ 'rotate-180': !showChairsSection }"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </button>

        <transition name="agenda-collapse">
          <div v-if="showChairsSection" class="px-3 pb-3 pt-1 border-t border-slate-100 space-y-0.5">
            <div v-for="chair in chairsList" :key="chair.id"
                 class="group/chair flex items-center rounded-lg transition-colors"
                 :class="String(chairFilter) === String(chair.id) ? 'bg-emerald-50' : 'hover:bg-slate-100'">
              <button @click="chairFilter = String(chair.id); onFilterChange()"
                      class="flex min-w-0 flex-1 items-center gap-2 px-2 py-1.5 text-xs font-medium"
                      :class="String(chairFilter) === String(chair.id) ? 'text-emerald-700' : 'text-slate-600'">
                <span class="h-2 w-2 rounded-full shrink-0" :style="{ backgroundColor: chair.color }" />
                <span class="truncate">{{ chair.name }}</span>
              </button>
              <button @click="openEditChairModal(chair)" title="Editar cadeira"
                      class="mr-1 shrink-0 rounded-md p-1 text-slate-400 opacity-0 transition-opacity hover:bg-slate-200 hover:text-slate-600 group-hover/chair:opacity-100">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
            </div>

            <p v-if="!chairsList.length" class="px-2 py-1.5 text-[11px] text-slate-400">
              Nenhuma cadeira cadastrada.
            </p>

            <button @click="chairFilter = 'all'; onFilterChange()"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium transition-colors"
                    :class="chairFilter === 'all' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'">
              <span class="h-2 w-2 rounded-full bg-slate-300 shrink-0" />
              <span class="truncate">Todas</span>
            </button>

            <p v-if="atChairLimit" class="px-2 pt-1.5 text-[10px] text-slate-400 leading-snug">
              Sua clínica já possui o máximo de {{ maxChairs }} cadeiras.
            </p>
          </div>
        </transition>
      </div>
      <!-- /Card: Cadeiras -->

      <!-- Card: Agendas -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden shrink-0">
        <button @click="showAgendasSection = !showAgendasSection"
                class="flex items-center justify-between w-full px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors">
          <span>Agendas</span>
          <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
               :class="{ 'rotate-180': !showAgendasSection }"
               fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <transition name="agenda-collapse">
          <div v-if="showAgendasSection" class="px-3 pb-3 pt-1 border-t border-slate-100 space-y-0.5">
            <div class="max-h-[124px] overflow-y-auto space-y-0.5 pr-0.5">
              <button v-for="prof in professionals" :key="prof.id"
                      @click="profFilter = String(prof.id); onFilterChange()"
                      class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium transition-colors"
                      :class="String(profFilter) === String(prof.id) ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'">
                <span class="h-2 w-2 rounded-full shrink-0" :class="prof.is_current_user ? 'bg-emerald-500' : 'bg-slate-300'" />
                <span class="truncate">{{ prof.name }}</span>
              </button>
            </div>

            <p v-if="!professionals.length" class="px-2 py-1.5 text-[11px] text-slate-400">
              Nenhuma agenda disponível.
            </p>

            <button @click="profFilter = 'all'; onFilterChange()"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium transition-colors"
                    :class="profFilter === 'all' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'">
              <span class="h-2 w-2 rounded-full bg-slate-300 shrink-0" />
              <span class="truncate">Todos</span>
            </button>
          </div>
        </transition>
      </div>
      <!-- /Card: Agendas -->
    </div>
    </transition>

  <!-- ── Grade do calendário ────────────────────────────────────────────── -->
  <div ref="gridScrollRef" class="flex-1 overflow-auto bg-slate-50/40 rounded-br-2xl">

    <!-- Cabeçalho dos dias (sticky) -->
    <div class="flex bg-white border-b sticky top-0 z-20" style="min-width: max-content">
      <div class="w-14 flex-shrink-0 border-r bg-white sticky left-0 z-40" />
      <div v-for="day in visibleDays" :key="'fhd-' + toDateStr(day)"
           class="flex-1 min-w-[130px] text-center py-2.5 border-r last:border-r-0 cursor-pointer transition-colors"
           :class="isSelectedDay(day) ? 'bg-emerald-100/70 border-b-2 border-b-emerald-500' : (isToday(day) ? 'bg-emerald-50/80' : 'hover:bg-slate-50')"
           @click="selectedDay = day">
        <div class="text-[10px] font-semibold uppercase tracking-wide"
             :class="isSelectedDay(day) ? 'text-emerald-700' : (isToday(day) ? 'text-emerald-500' : 'text-slate-400')">
          {{ PT_DAYS[dayIndex(day)] }}
        </div>
        <div class="text-lg font-bold leading-tight"
             :class="isSelectedDay(day) ? 'text-emerald-800' : (isToday(day) ? 'text-emerald-600' : 'text-slate-700')">
          {{ day.getDate() }}
        </div>
        <div v-if="holidayNameFor(day)" class="text-[8px] font-semibold text-amber-600 leading-tight truncate mt-0.5"
             :title="holidayNameFor(day)">
          Feriado
        </div>
      </div>
    </div>

    <!-- Grade de tempo -->
    <div class="flex" :style="{ height: gridHeight + 'px', minWidth: 'max-content' }">

      <!-- Coluna de horas -->
      <div class="w-14 flex-shrink-0 border-r bg-white relative sticky left-0 z-40">
        <div v-for="h in hours" :key="'fth-' + h"
             class="absolute right-0 pr-2 text-[10px] text-slate-400 text-right tabular-nums"
             :class="h === gridStartHour ? 'translate-y-0.5' : '-translate-y-1/2'"
             :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px` }">
          {{ String(h).padStart(2, '0') }}:00
        </div>
      </div>

      <!-- Colunas dos dias -->
      <div v-for="day in visibleDays" :key="'fdc-' + toDateStr(day)"
           class="flex-1 min-w-[130px] relative border-r last:border-r-0"
           :class="isSelectedDay(day) ? 'bg-emerald-50/40' : (isToday(day) ? 'bg-blue-50/10' : 'bg-white')"
           @mousedown="dragSelect.onPointerDown($event, toDateStr(day))"
           @click="clickSlot(day, $event)">

        <!-- Seleção por arraste em andamento (estilo Excel). -->
        <div v-if="dragSelect.dragging.value && dragSelect.dragColumnKey.value === toDateStr(day)"
             class="absolute left-0 right-0 z-30 pointer-events-none bg-emerald-500/15 border-y-2 border-emerald-500 rounded-sm flex flex-col justify-between px-1.5 py-0.5"
             :style="{
               top: `${Math.min(dragSelect.dragStartY.value, dragSelect.dragCurrentY.value)}px`,
               height: `${Math.max(Math.abs(dragSelect.dragCurrentY.value - dragSelect.dragStartY.value), 4)}px`,
             }">
          <span class="text-[9px] font-semibold text-emerald-700 tabular-nums leading-none">
            {{ dragTimeLabel(Math.min(dragSelect.dragStartY.value, dragSelect.dragCurrentY.value)) }}
          </span>
          <span class="text-[9px] font-semibold text-emerald-700 tabular-nums leading-none">
            {{ dragTimeLabel(Math.max(dragSelect.dragStartY.value, dragSelect.dragCurrentY.value)) }}
          </span>
        </div>

        <!-- Bandas alternadas por hora — mesmo tratamento de Index.vue. -->
        <div v-for="(h, i) in hours" :key="'fhb-' + h"
             v-show="i % 2 === 1"
             class="absolute w-full pointer-events-none bg-slate-100/50"
             :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px`, height: `${60 * pxPerMin}px` }" />

        <!-- Grade principal, 30 min (:00 e :30) — sempre visível. -->
        <div v-for="h in hours" :key="'fhl-' + h"
             class="absolute w-full border-t border-slate-100"
             :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px` }" />
        <div v-for="h in hours" :key="'fhl30-' + h"
             class="absolute w-full border-t border-slate-100"
             :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 30 * pxPerMin}px` }" />

        <!-- Subdivisão fina, 15 min (:15 e :45) — atrás do toggle "Mostrar
             grade de meia hora" (agora controla só este nível mais fino). -->
        <template v-if="settings.showSecondaryGrid">
          <div v-for="h in hours" :key="'fhl15-' + h"
               class="absolute w-full border-t border-dashed border-slate-100/70"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 15 * pxPerMin}px` }" />
          <div v-for="h in hours" :key="'fhl45-' + h"
               class="absolute w-full border-t border-dashed border-slate-100/70"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 45 * pxPerMin}px` }" />
        </template>

        <!-- Banda de almoço -->
        <div v-if="lunchBandStyle"
             class="absolute left-0 right-0 pointer-events-none z-[1] bg-slate-50/70 border-y border-slate-200/40"
             :style="lunchBandStyle" />

        <!-- Fora do horário de atendimento — banda cinza suave. Sem regra
             obrigatória ativa, reflete só o horário do profissional
             selecionado (comportamento de sempre); com regra ativa, também
             funciona no modo "Todos" e varia por dia. -->
        <div v-for="(band, i) in outOfHoursBandsFor(day)" :key="'foh-' + i"
             class="absolute left-0 right-0 pointer-events-none z-[1] bg-slate-200/70"
             :class="band.pos === 'after' ? 'border-t-2 border-slate-300' : 'border-b-2 border-slate-300'"
             :style="band.style">
          <span class="block text-center text-[8px] font-semibold uppercase tracking-wide text-slate-400 pt-1">
            Fora do horário
          </span>
        </div>

        <!-- Feriado — dia inteiro sem atendimento. -->
        <div v-if="holidayNameFor(day)"
             class="absolute inset-0 pointer-events-none z-[1] bg-amber-50/50" />

        <!-- Dia bloqueado por regra administrativa obrigatória (sábado/
             domingo "não trabalha", ou horário fechado esse dia) — cinza,
             não âmbar, pra não parecer feriado. Agendamentos existentes
             continuam renderizados normalmente por cima, nunca escondidos. -->
        <div v-if="isClinicDayOff(day)"
             class="absolute inset-0 pointer-events-none z-[1] bg-slate-100/70" />
        <div v-if="isClinicDayOff(day)"
             class="absolute top-1.5 left-1.5 right-1.5 z-[1] pointer-events-none text-center text-[9px] font-medium text-slate-400">
          Sem atendimento
        </div>

        <!-- Linha do horário atual -->
        <div v-if="settings.showNowLine && isToday(day) && nowTop >= 0 && nowTop <= gridHeight"
             class="absolute left-0 right-0 z-10 pointer-events-none flex items-center"
             :style="{ top: `${nowTop}px` }">
          <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1.5 flex-shrink-0 shadow-md ring-2 ring-red-200" />
          <div class="flex-1 h-px bg-red-400/70" />
        </div>

        <!-- Cards (premium) -->
        <div v-for="appt in visibleApptsFor(day)" :key="appt.id"
             data-appt="1"
             class="absolute border-l-[3px] overflow-visible cursor-pointer select-none
                    shadow-sm hover:shadow-lg ring-1 ring-black/[0.04] rounded-lg
                    transition-all duration-150 hover:-translate-y-px"
             :class="[st(appt).bg, st(appt).border, isPastAppt(appt) ? 'opacity-60 saturate-50' : '']"
             :style="apptStyle(appt)"
             @mouseenter="showTooltipDelayed(appt, $event)"
             @mouseleave="hideTooltip"
             @click.stop="openPopover(appt, $event)">
          <!-- Etiquetas: até 2 bolinhas + "N+" — mesma posição/regra de
               Index.vue, nunca pisca. -->
          <div v-if="appt.tags?.length" class="absolute top-1 right-1 z-20 flex items-center gap-0.5"
               :title="appt.tags.map(t => t.name).join(', ')">
            <span v-for="tag in appt.tags.slice(0, 2)" :key="tag.id"
                  class="h-1.5 w-1.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />
            <span v-if="appt.tags.length > 2" class="text-[7px] font-bold leading-none text-slate-500">{{ appt.tags.length - 2 }}+</span>
          </div>

          <div class="h-full flex flex-col justify-start px-1.5 pt-1 gap-px">
            <!-- Paciente: primeiro e mais proeminente. Nome curto (até 3
                 palavras) — nome completo continua no hover/clique. -->
            <div class="text-[11px] font-bold leading-tight truncate" :class="st(appt).text">
              {{ shortPatientName(appt) }}
            </div>
            <!-- Horário: logo abaixo do nome, em negrito e um pouco maior. -->
            <div class="text-[10px] font-bold text-slate-500 leading-none tabular-nums">
              {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
            </div>
            <!-- Fora do horário atual da clínica — só informativo; nunca
                 altera/move o agendamento. -->
            <div v-if="apptScheduleNotice(appt)"
                 class="flex items-center gap-0.5 text-[8px] font-semibold text-amber-600 leading-none truncate"
                 :title="apptScheduleNotice(appt)">
              <svg class="w-2 h-2 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.28 11.18c.75 1.334-.213 2.987-1.742 2.987H3.72c-1.53 0-2.493-1.653-1.743-2.987l6.28-11.18zM10 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
              Fora do horário
            </div>
          </div>
        </div>

        <!-- Agendamentos além do teto/piso absoluto da grade — nunca fazem
             a grade crescer, continuam acessíveis por aqui. -->
        <OffGridAppointmentsBadge
            class="absolute bottom-0 left-0 right-0 z-20"
            :appointments="offGridApptsFor(day)"
            :format-time="formatTime"
            :patient-name="a => shortPatientName(a)"
            @select="(appt, e) => openPopover(appt, e)" />
      </div>
    </div>
  </div>
  </div>
</div>

<!-- ── Tooltip (hover) ─────────────────────────────────────────────────── -->
<Teleport to="body">
  <Transition name="tooltip-fade">
    <div v-if="tooltipAppt && !activePopover"
         class="fixed z-[80] pointer-events-none"
         :style="tooltipStyle">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden">
        <div class="px-3 py-2.5 border-b" :class="st(tooltipAppt).bg">
          <div class="font-semibold text-sm text-slate-800 leading-tight truncate">
            {{ tooltipAppt.patient?.nome }} {{ tooltipAppt.patient?.sobrenome }}
          </div>
          <div class="text-[10px] text-slate-500 mt-0.5 tabular-nums">
            {{ formatTime(tooltipAppt.start) }} – {{ formatTime(tooltipAppt.end) }}
          </div>
        </div>
        <div class="px-3 py-2 space-y-1.5">
          <div class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Profissional</span>
            <span class="text-[10px] text-slate-700">{{ tooltipAppt.professional?.name || '—' }}</span>
          </div>
          <div v-if="tooltipAppt.patient?.telefone" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Telefone</span>
            <span class="text-[10px] text-slate-700 tabular-nums">{{ tooltipAppt.patient.telefone }}</span>
          </div>
          <div v-if="tooltipAppt.notes" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Observações</span>
            <span class="text-[10px] text-slate-600 leading-snug whitespace-pre-line line-clamp-3">{{ tooltipAppt.notes }}</span>
          </div>
          <div class="flex gap-2 items-center">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Status</span>
            <StatusIndicator :status="resolveStatus(tooltipAppt, nowRef)" :delay-minutes="getDelayMinutes(tooltipAppt, nowRef)" show-label />
          </div>
          <div v-if="tooltipAppt.tags?.length" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Etiquetas</span>
            <span class="flex flex-wrap gap-1">
              <span v-for="tag in tooltipAppt.tags" :key="tag.id" class="inline-flex items-center gap-1 text-[9px] text-slate-600">
                <span class="h-1.5 w-1.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />{{ tag.name }}
              </span>
            </span>
          </div>
          <div v-if="tooltipAppt.appointment_return" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Retorno</span>
            <span class="text-[10px] text-slate-700 leading-snug">
              {{ new Date(tooltipAppt.appointment_return.due_date).toLocaleDateString('pt-BR') }}
              <span v-if="tooltipAppt.appointment_return.reason"> — {{ tooltipAppt.appointment_return.reason }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</Teleport>

<!-- ── Popover ──────────────────────────────────────────────────────────── -->
<Teleport to="body">
  <div v-if="activePopover"
       ref="popoverRef"
       class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200"
       :style="popoverStyle">

    <!-- Header: paciente é o elemento mais importante; confirmação por
         WhatsApp/e-mail logo abaixo do nome, pequenas e lado a lado (só
         interface preparada — ver notifyContactComingSoon, nunca finge um
         envio); status (segundo mais importante) logo abaixo delas —
         dropdown com controle completo (mesmo endpoint dos botões de
         atalho, ver changeStatus). Sem horário aqui — já aparece uma única
         vez, no bloco Data/Horário abaixo — e sem tratamento/procedimento. -->
    <div class="px-4 py-3 border-b rounded-t-xl relative" :class="st(activePopover).bg">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <div class="font-bold text-[15px] text-slate-800 leading-tight truncate">
            {{ activePopover.patient?.nome }} {{ activePopover.patient?.sobrenome }}
          </div>
          <div v-if="activePopover.patient?.telefone || activePopover.patient?.email"
               class="mt-1 flex flex-nowrap items-center gap-1">
            <button v-if="activePopover.patient?.telefone" type="button" @click.stop="notifyContactComingSoon('whatsapp')"
                    class="inline-flex items-center gap-0.5 whitespace-nowrap shrink-0 text-[9px] font-medium px-1.5 py-0.5 rounded-md bg-white/70 text-emerald-700 hover:bg-white transition-colors">
              <PhoneIcon class="w-2.5 h-2.5 shrink-0" />
              Confirmar por WhatsApp
            </button>
            <button v-if="activePopover.patient?.email" type="button" @click.stop="notifyContactComingSoon('email')"
                    class="inline-flex items-center gap-0.5 whitespace-nowrap shrink-0 text-[9px] font-medium px-1.5 py-0.5 rounded-md bg-white/70 text-slate-600 hover:bg-white transition-colors">
              <EnvelopeIcon class="w-2.5 h-2.5 shrink-0" />
              Confirmar por e-mail
            </button>
          </div>
          <button type="button" @click="toggleStatusMenu(activePopover)"
                  class="mt-1 inline-flex items-center gap-1.5 pl-2 pr-1.5 py-1 rounded-full text-xs font-bold bg-white/70 hover:bg-white transition-colors"
                  :class="STATUS_CONFIG[resolveStatus(activePopover, nowRef)]?.text">
            <span class="h-2 w-2 rounded-full flex-shrink-0" :class="STATUS_CONFIG[resolveStatus(activePopover, nowRef)]?.dot" />
            {{ STATUS_CONFIG[resolveStatus(activePopover, nowRef)]?.label }}
            <ChevronDownIcon class="w-3.5 h-3.5 opacity-60" />
          </button>
        </div>
        <button @click="closePopover" class="p-0.5 rounded hover:bg-black/10 text-slate-400 flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div v-if="statusMenuOpenFor === activePopover.id"
           class="absolute left-4 right-4 top-full mt-1 rounded-lg border border-slate-200 bg-white shadow-lg overflow-hidden z-10 py-1">
        <button v-for="opt in STATUS_DROPDOWN_OPTIONS" :key="opt.label" type="button"
                @click="pickStatus(activePopover, opt.value)"
                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 text-left transition-colors">
          <span class="h-2 w-2 rounded-full flex-shrink-0" :class="STATUS_CONFIG[opt.key].dot" />
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Data e horário — linha própria, ícones discretos -->
    <div class="px-4 py-2 border-b space-y-1">
      <div class="flex items-center gap-2 text-xs text-slate-600">
        <CalendarDaysIcon class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
        {{ formatFullDate(activePopover.start) }}
      </div>
      <div class="flex items-center gap-2 text-xs text-slate-600 tabular-nums">
        <ClockIcon class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
        {{ formatTime(activePopover.start) }} – {{ formatTime(activePopover.end) }}
      </div>
      <!-- Só informativo — nunca bloqueia a consulta nem altera o
           agendamento, apenas avisa que ele foi criado antes de uma
           mudança de regra administrativa. -->
      <div v-if="apptScheduleNotice(activePopover)" class="flex items-center gap-2 text-xs text-amber-600">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.28 11.18c.75 1.334-.213 2.987-1.742 2.987H3.72c-1.53 0-2.493-1.653-1.743-2.987l6.28-11.18zM10 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
        {{ apptScheduleNotice(activePopover) }}
      </div>
    </div>

    <div class="px-4 py-2.5 border-b space-y-1.5">
      <div class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Profissional</span>
        <span class="text-xs text-slate-700 truncate">{{ activePopover.professional?.name || '—' }}</span>
      </div>
      <!-- Cadeira: sem bolinha colorida — só o status usa indicador de cor
           aqui, pra não confundir os dois no popover. -->
      <div class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Cadeira</span>
        <span class="text-xs text-slate-700">{{ activePopover.chair?.name || 'Sem cadeira' }}</span>
      </div>
      <!-- Observações: truncada a 2 linhas, sem scrollbar própria — texto
           completo aparece num tooltip discreto ao passar o mouse (mesma
           observação já limitada a 200 caracteres no campo). -->
      <div v-if="activePopover.notes" class="flex items-start gap-2 relative group/notes">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Observações</span>
        <span class="text-xs text-slate-600 leading-snug line-clamp-2">{{ activePopover.notes }}</span>
        <div class="pointer-events-none absolute left-0 top-full mt-1 w-64 max-w-[85vw] opacity-0 group-hover/notes:opacity-100 transition-opacity duration-150 bg-slate-800 text-white text-[11px] leading-snug rounded-lg px-3 py-2 whitespace-pre-line shadow-lg z-20">
          {{ activePopover.notes }}
        </div>
      </div>
      <div v-if="activePopover.tags?.length" class="flex items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Etiquetas</span>
        <span class="flex flex-wrap gap-1.5">
          <span v-for="tag in activePopover.tags" :key="tag.id" class="inline-flex items-center gap-1 text-[10px] text-slate-600">
            <span class="h-1.5 w-1.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />{{ tag.name }}
          </span>
        </span>
      </div>
      <div v-if="activePopover.appointment_return" class="flex items-start gap-2">
        <span class="text-[10px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Retorno</span>
        <span class="text-xs text-slate-700 leading-snug">
          {{ new Date(activePopover.appointment_return.due_date).toLocaleDateString('pt-BR') }}
          <span v-if="activePopover.appointment_return.reason"> — {{ activePopover.appointment_return.reason }}</span>
        </span>
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
      <button type="button" @click="openEditApptModal(activePopover)"
              class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors">
        Editar
      </button>
      <Link :href="route('patients.show', activePopover.patient_id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors">
        Ver paciente
      </Link>
      <button v-if="!['cancelled', 'no_show', 'completed'].includes(activePopover.status)"
              @click="quickNoShow(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transition-colors">
        Faltou
      </button>
      <button v-if="!['cancelled', 'no_show', 'completed'].includes(activePopover.status)"
              @click="quickCancel(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transition-colors">
        Cancelar
      </button>
    </div>
  </div>
</Teleport>

<ChairFormModal :show="showChairModal" :chair="editingChair"
                @close="showChairModal = false" @saved="onChairSaved" @deleted="onChairDeleted" />

<HolidayNoticeModal :show="showHolidayModal" :holiday-name="holidayModalInfo.name" :date-label="holidayModalInfo.dateLabel"
                     @close="showHolidayModal = false" />

<ScheduleBlockedModal :show="showScheduleBlockedModal" :title="scheduleBlockedInfo.title" :message="scheduleBlockedInfo.message"
                       @close="showScheduleBlockedModal = false" />

<AppointmentFormModal :show="showApptModal"
                       :professionals="professionals"
                       :chairs="chairsList"
                       :available-markers="availableMarkers"
                       :marker-limit="markerLimit"
                       :consider-national-holidays="considerNationalHolidays"
                       :holidays="holidays"
                       :business-hours="businessHours"
                       :business-hours-enforced="businessHoursEnforced"
                       :prefill="apptModalPrefill"
                       :appointment="editingAppointment"
                       :redirect-week="weekStart"
                       :redirect-professional-id="profFilter !== 'all' ? profFilter : null"
                       :redirect-chair-id="chairFilter !== 'all' ? chairFilter : null"
                       @close="showApptModal = false" />
</AppLayout>
</template>

<style scoped>
.tooltip-fade-enter-active,
.tooltip-fade-leave-active { transition: opacity 0.12s ease; }
.tooltip-fade-enter-from,
.tooltip-fade-leave-to     { opacity: 0; }

/* Mesma transição de colapso das seções da sidebar em Index.vue — mantém a
   sensação de coerência visual entre a Agenda normal e a tela cheia. */
.agenda-collapse-enter-active,
.agenda-collapse-leave-active {
    transition: max-height 0.2s ease, opacity 0.2s ease;
    overflow: hidden;
}
.agenda-collapse-enter-from,
.agenda-collapse-leave-to  { max-height: 0; opacity: 0; }
.agenda-collapse-enter-to,
.agenda-collapse-leave-from { max-height: 300px; opacity: 1; }

/* ── Sidebar collapse — mesma transição de Index.vue (w-80 = 20rem). ───── */
.agenda-sidebar-enter-active,
.agenda-sidebar-leave-active {
  transition: max-width 0.22s ease, opacity 0.18s ease;
  overflow: hidden;
}
.agenda-sidebar-enter-from,
.agenda-sidebar-leave-to  { max-width: 0 !important; opacity: 0; }
.agenda-sidebar-enter-to,
.agenda-sidebar-leave-from { max-width: 20rem; opacity: 1; }
</style>
