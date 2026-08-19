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
import { resolveStatus, getDelayMinutes, sortByPriority, cardAppearance, STATUS_CONFIG, STATUS_DROPDOWN_OPTIONS } from '@/composables/useAppointmentStatus'
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

// ── Constantes do grid ─────────────────────────────────────────────────────
// END_HOUR é o teto ABSOLUTO da grade, nunca ultrapassado — gridStartHour
// (ver useEffectiveSchedule.js) é o piso ABSOLUTO, sempre 07:00, também
// nunca ultrapassado. A grade visual é sempre 07:00→21:00, independente do
// horário configurado (clínica ou profissional); é outOfHoursBandsFor()
// mais abaixo quem decora as horas fora do expediente, não o tamanho da
// grade em si.
const END_HOUR = GRID_CEIL_HOUR

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
// Card da Agenda: nome curto (até 3 palavras) pra caber numa linha só —
// o nome completo continua disponível no hover (tooltip) e no clique
// (popover), que já usam patient.nome/sobrenome sem esse corte.
const shortPatientName = (appt) => {
    const full = `${appt.patient?.nome || ''} ${appt.patient?.sobrenome || ''}`.trim()
    return full.split(/\s+/).filter(Boolean).slice(0, 3).join(' ')
}
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
// Painel lateral (mini-calendário + Cadeiras + Agendas) começa recolhido
// abaixo de 1440px — medido na R5 nos 8 breakpoints exigidos: com o painel
// aberto, a grade só mostra a semana inteira sem rolagem em 1440/1920;
// em 1280 já fica ~93% (um dia cortado), em 1024/768 cai pra ~56% (só 3-4
// de 7 colunas), e em celular vira uma tira de ~21px. Recolhido, a semana
// inteira coube sem nenhuma rolagem horizontal em TODOS os tamanhos testados
// de 375 a 1280px. Continua um estado de UI comum (não persistido) — só o
// valor INICIAL passou a depender da largura da tela; o botão de
// recolher/expandir continua funcionando exatamente como antes em qualquer
// tamanho.
const showSidebar    = ref(typeof window === 'undefined' || window.innerWidth >= 1440)
const showMiniCal    = ref(true)
const showChairsSection = ref(true)
const showAgendasSection = ref(true)
// 'all' = "Todas" (sem filtro, comportamento de sempre); id numérico = agenda
// de um profissional específico. Mesmo sentinel usado por chairFilter, por
// consistência — mas aqui não há "default" especial: sem professional_id na
// URL já é 'all' (ver AppointmentController::resolveProfessionalFilter).
const profFilter     = ref(props.filters?.professional_id || 'all')
// 'all' = "Todas" (escolha explícita); um id numérico = cadeira específica.
// O servidor sempre resolve isso antes de chegar aqui — sem chair_id na URL,
// já vem com a cadeira default (a mais antiga, "Cadeira 01"); "Todas" só
// acontece quando o usuário escolhe explicitamente (ver AppointmentController
// ::resolveChairFilter). Cópia local (não a prop direta) porque cadeiras são
// criadas/editadas/excluídas sem recarregar a página inteira (ver
// onChairSaved/onChairDeleted).
const chairFilter    = ref(props.filters?.chair_id || 'all')
const chairsList     = ref([...(props.chairs || [])])
const showChairModal = ref(false)
const editingChair   = ref(null)
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

// Comparação por string (YYYY-MM-DD), não por Date — "we" é meia-noite do
// último dia da semana, então comparar Date objects direto (today <= we)
// falha sempre que "agora" for depois da meia-noite desse dia (ou seja,
// quase sempre), fazendo o dia atual nunca "bater" e cair no fallback (ws).
// É por isso que reabrir a Agenda não marcava mais hoje como selecionado.
const selectedDay = ref((() => {
    const today = new Date()
    const ws    = parseLocalDate(props.weekStart)
    const we    = addDays(ws, 6)
    const t     = toDateStr(today)
    return (t >= toDateStr(ws) && t <= toDateStr(we)) ? today : ws
})())

const pendingDayAfterNav = ref(null)
watch(() => props.weekStart, () => {
    if (pendingDayAfterNav.value) {
        selectedDay.value = parseLocalDate(pendingDayAfterNav.value)
        pendingDayAfterNav.value = null
    }
})

// ── Zoom ───────────────────────────────────────────────────────────────────
// Limites únicos do zoom — toda mutação (botões, slider de configurações,
// CTRL+Scroll) passa por clampZoom(), então não há como ultrapassá-los por
// outro caminho.
const ZOOM_MIN  = 0.9
const ZOOM_MAX  = 1.8
const ZOOM_STEP = 0.1
const clampZoom = (v) => Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, Math.round(v * 10) / 10))

const zoomLevel = computed({
    // O getter também clampa: um valor persistido antes desse limite existir
    // (ex.: zoom salvo em 40% ou 300%) é normalizado ao ler, não só ao setar.
    get: () => clampZoom(settings.zoomLevel),
    set: (v) => { settings.zoomLevel = clampZoom(v) },
})
const isZoomAtMin = computed(() => zoomLevel.value <= ZOOM_MIN)
const isZoomAtMax = computed(() => zoomLevel.value >= ZOOM_MAX)
const pxPerMin = computed(() => zoomLevel.value)
const gridHeight = computed(() => TOTAL_MIN.value * pxPerMin.value)

// ── Datas da semana ────────────────────────────────────────────────────────
const weekDates = computed(() => {
    const monday = parseLocalDate(props.weekStart)
    return Array.from({ length: 7 }, (_, i) => addDays(monday, i))
})

// Chaves na mesma ordem de ClinicUserPivot::DAY_KEYS (backend) — índice
// aqui é o dayIndex() já usado no resto do componente (0=Seg..6=Dom).
const WORKING_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

// Só relevante quando uma agenda específica está selecionada — "Todos" nunca
// filtra dia nenhum (ver regra: sábado continua existindo em "Todos" mesmo
// que só parte dos profissionais atenda nele).
const selectedProfessional = computed(() =>
    profFilter.value !== 'all'
        ? props.professionals.find(p => String(p.id) === String(profFilter.value))
        : null)

const visibleDays = computed(() => {
    if (viewMode.value === 'day') return [selectedDay.value]
    const days = [...weekDates.value.slice(0, 5)]
    if (settings.showSaturday) days.push(weekDates.value[5])
    if (settings.showSunday)   days.push(weekDates.value[6])

    const workingDays = selectedProfessional.value?.working_days
    if (!workingDays) return days

    // Composição, não substituição: só remove dias que o showSaturday/
    // showSunday já deixou visíveis E que esse profissional não atende.
    const filtered = days.filter(d => workingDays[WORKING_DAY_KEYS[dayIndex(d)]] !== false)
    // Salvaguarda: um profissional sem nenhum dia ativo não pode deixar a
    // grade sem colunas — melhor mostrar a semana normal (sem horários
    // disponíveis) do que uma grade quebrada.
    return filtered.length ? filtered : days
})

// ── Regras administrativas da clínica (horário/dia efetivo) ────────────────
// Só decide a APARÊNCIA da grade — o bloqueio real de criação continua no
// backend (AppointmentController::assertProfessionalAvailable), que já usa
// a mesma regra via ClinicUserPivot::effective*. Única fonte dessa lógica
// no frontend (ver useEffectiveSchedule.js) — Fullscreen.vue reaproveita a
// mesma composable, não duplica a matemática.
const scheduleRules = useAgendaScheduleRules({
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

// Só afeta a largura MÍNIMA das colunas (ver min-w mais abaixo) — o
// container continua sempre no mesmo teto de max-w-7xl (content-width=
// "full"), igual 5/6 dias. Com Sáb+Dom juntos (7 colunas), 130px cada
// (o mínimo usado em 5/6 dias) não cabia dentro desse teto — sidebar
// (320px) + coluna de horas (56px) + 7×130px passava da largura
// disponível (~1216px), cortando domingo pra fora com scroll horizontal.
// Com 110px de mínimo, o flex-1 de cada coluna se acomoda sozinho em
// ~120px dentro do MESMO espaço de sempre — nunca precisa alargar o
// container. 6 dias ou menos continuam com min-w-[130px], inalterados.
const isSevenDayWeek = computed(() => viewMode.value === 'week' && visibleDays.value.length === 7)

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
// TOTAL_MIN/hours agora derivam de gridStartHour (dinâmico) — END_HOUR
// continua fixo (teto absoluto de sempre, 21h).
const TOTAL_MIN = computed(() => (END_HOUR - gridStartHour.value) * 60)
const hours = computed(() => Array.from({ length: END_HOUR - gridStartHour.value }, (_, i) => gridStartHour.value + i))

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
            let ok = a.start.slice(0, 10) === ds
            if (profFilter.value !== 'all') ok = ok && a.professional_id == profFilter.value
            if (chairFilter.value && chairFilter.value !== 'all') ok = ok && a.chair_id == chairFilter.value
            return ok
        })
        if (settings.hideCancelled) list = list.filter(a => a.status !== 'cancelled')
        map[ds] = assignColumns(list)
    })
    return map
})

// ── Visão "Todas as cadeiras" — só faz sentido na visão Dia (colunas já são
// usadas pelos dias na visão Semana; dividir também por cadeira ali criaria
// uma grade dias×cadeiras ilegível). Reaproveita o mesmo assignColumns/
// apptStyle de sempre, só troca o que vira coluna: cadeira em vez de dia. ──
const showResourceColumns = computed(() =>
    viewMode.value === 'day' && chairFilter.value === 'all' && chairsList.value.length > 0)

// ── Modal de novo agendamento (substitui a navegação pra Appointments/
// Create.vue ao clicar na grade ou em "+ Novo") — mantém o usuário dentro
// da própria Agenda, sem perder sidebar/zoom/scroll. `redirectWeek/
// ProfessionalId/ChairId` viajam com o form pra o backend devolver o
// usuário pra ESTA mesma visão depois de criar (ver AppointmentController
// ::store), não pra semana atual/"Todos".
const showApptModal    = ref(false)
const apptModalPrefill = ref({})
const editingAppointment = ref(null)

function openApptModal(prefill = {}) {
    editingAppointment.value = null
    apptModalPrefill.value = prefill
    showApptModal.value = true
}

// "Editar" do popover — mesmo modal de criação, agora em modo edição (ver
// AppointmentFormModal.vue, prop `appointment`). Substitui a navegação pra
// Edit.vue dentro da Agenda; a página avulsa continua existindo só pro link
// que vem do prontuário (Consultations/Show.vue).
function openEditApptModal(appt) {
    editingAppointment.value = appt
    showApptModal.value = true
    closePopover()
}

// "+ Novo" herda o contexto em foco: cadeira/agenda específica selecionada
// (se houver) e o dia visível na visão Dia — mesmo comportamento que o
// formulário avulso já tinha pra cadeira, agora também considerando
// profissional/dia (o campo de paciente etc continuam totalmente livres).
function openNewAppointmentModal() {
    openApptModal({
        date: viewMode.value === 'day' ? toDateStr(selectedDay.value) : undefined,
        chairId: chairFilter.value !== 'all' ? chairFilter.value : undefined,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
}

// Agendamentos antigos sem cadeira (chair_id nulo) ganham uma coluna
// "Sem cadeira" só quando existem de fato naquele dia — não cria uma coluna
// vazia à toa, mas também nunca esconde um agendamento legado.
const resourceColumns = computed(() => {
    if (!showResourceColumns.value) return []
    const cols = chairsList.value.map(c => ({ id: c.id, name: c.name, color: c.color }))
    const ds = toDateStr(selectedDay.value)
    const hasUnassigned = props.appointments.some(a => a.start.slice(0, 10) === ds && !a.chair_id)
    if (hasUnassigned) cols.push({ id: null, name: 'Sem cadeira', color: '#94a3b8' })
    return cols
})

const byResource = computed(() => {
    const map = {}
    if (!showResourceColumns.value) return map
    const ds = toDateStr(selectedDay.value)
    resourceColumns.value.forEach(col => {
        let list = props.appointments.filter(a => {
            let ok = a.start.slice(0, 10) === ds
            ok = ok && (col.id === null ? !a.chair_id : a.chair_id === col.id)
            if (profFilter.value !== 'all') ok = ok && a.professional_id == profFilter.value
            return ok
        })
        if (settings.hideCancelled) list = list.filter(a => a.status !== 'cancelled')
        map[col.id ?? 'none'] = assignColumns(list)
    })
    return map
})

// ── Colunas do grid, unificadas ─────────────────────────────────────────
// Dias (padrão) ou cadeiras (Dia + "Todas") descrevem a mesma "coluna" pro
// cabeçalho e pro corpo da grade — evita duplicar o bloco de renderização
// dos cards (que é grande) só pra trocar o que virou coluna.
const gridColumns = computed(() => {
    if (showResourceColumns.value) {
        return resourceColumns.value.map(col => ({
            key: col.id ?? 'none',
            isResource: true,
            label: col.name,
            color: col.color,
            chairId: col.id,
            day: selectedDay.value,
            appts: byResource.value[col.id ?? 'none'] || [],
        }))
    }
    return visibleDays.value.map(day => ({
        key: toDateStr(day),
        isResource: false,
        day,
        chairId: null,
        appts: byDay.value[toDateStr(day)] || [],
    }))
})

// ── Posicionamento dos cards (zoom-aware) ──────────────────────────────────
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

// ── Configuração de status ───────────────────────────────────────────────
// cardAppearance() (useAppointmentStatus.js) é a fonte única de cor —
// chaveada pelo status RESOLVIDO (resolveStatus), não pelo appt.status cru,
// pra "aguardando" e "em atendimento" ficarem visualmente diferentes.
const s = (appt) => cardAppearance(resolveStatus(appt, nowRef.value))

// ── Cadeiras (recursos) ──────────────────────────────────────────────────
// O backend (ChairController::store) é a autoridade real do limite — isto
// aqui só evita abrir um formulário que o servidor sempre rejeitaria.
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
    // A cadeira excluída era o filtro ativo — volta pra "Todas" (que já
    // dispara refetch) em vez de deixar o filtro apontando pro vazio.
    if (String(chairFilter.value) === String(id)) {
        chairFilter.value = 'all'
        onFilterChange()
    } else {
        router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true })
    }
}

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
        { week: toDateStr(d), professional_id: profFilter.value, chair_id: chairFilter.value },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

function navPrev() {
    if (viewMode.value === 'day') {
        const prev = addDays(selectedDay.value, -1)
        const ws   = parseLocalDate(props.weekStart)
        if (prev >= ws) { selectedDay.value = prev; return }
        pendingDayAfterNav.value = toDateStr(prev)
        router.get(route('appointments.index'),
            { week: toDateStr(addDays(ws, -7)), professional_id: profFilter.value, chair_id: chairFilter.value },
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
            { week: toDateStr(addDays(ws, 7)), professional_id: profFilter.value, chair_id: chairFilter.value },
            { preserveState: true, only: ['appointments', 'weekStart'] })
    } else {
        navWeek(1)
    }
}

const goToday = () => {
    const today = new Date()
    router.get(route('appointments.index'),
        { professional_id: profFilter.value, chair_id: chairFilter.value },
        { preserveState: true, only: ['appointments', 'weekStart'],
          onSuccess: () => { selectedDay.value = today } })
}

// Reaproveitado pelos dois selects (Profissional e Cadeira) — cada mudança
// manda os dois filtros juntos, combináveis (ver AppointmentController::index).
const onFilterChange = () => router.get(route('appointments.index'),
    { week: props.weekStart, professional_id: profFilter.value, chair_id: chairFilter.value },
    { preserveState: true, only: ['appointments'] })

// ── Linha do horário atual ─────────────────────────────────────────────────
const nowRef = ref(new Date())
let _clockTimer = null

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

    const delta    = e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP
    const oldZoom  = zoomLevel.value
    const newZoom  = clampZoom(oldZoom + delta)
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
// Único ponto que chama appointments.update-status — botões e o dropdown de
// status do popover (ver STATUS_DROPDOWN_OPTIONS) reutilizam esta mesma
// função, nunca duas implementações da mesma mudança de status.
// Trava simples pra evitar duplo-clique nos botões de ação rápida do
// popover (Confirmar/Check-in/Faltou/Cancelar/dropdown de status) — todos
// passam por changeStatus()/quickCheckin(), então uma flag só já cobre
// todos eles.
const quickActionBusy = ref(false)

function changeStatus(appt, status) {
    if (quickActionBusy.value) return
    quickActionBusy.value = true
    router.patch(route('appointments.update-status', appt.id), { status },
        { preserveState: true, preserveScroll: true,
          onSuccess: () => { closePopover(); router.reload({ only: ['appointments'], preserveState: true, preserveScroll: true }) },
          onFinish: () => { quickActionBusy.value = false } })
}

const quickConfirm = (appt) => changeStatus(appt, 'confirmed')

// Check-in continua com fluxo próprio (cria/atualiza Consultation, não é só
// um status) — endpoint e comportamento inalterados.
const quickCheckin = (appt) => {
    if (quickActionBusy.value) return
    quickActionBusy.value = true
    router.post(route('appointments.check-in', appt.id), {},
        { preserveScroll: true, onSuccess: closePopover, onFinish: () => { quickActionBusy.value = false } })
}

// Cancelar/Faltou nunca apagam o agendamento (ver AppointmentController::
// updateStatus), só mudam o status; cancelado/faltou já não bloqueiam mais
// disponibilidade (assertNoConflict exclui os dois). Confirmação nativa
// antes de disparar, como pedido — ação difícil de desfazer pela Agenda.
const quickCancel = (appt) => {
    if (!confirm('Cancelar este agendamento? O horário será liberado, mas o registro continua no histórico do paciente.')) return
    changeStatus(appt, 'cancelled')
}

const quickNoShow = (appt) => {
    if (!confirm('Marcar esta consulta como falta do paciente? O horário será liberado, mas o registro continua no histórico do paciente.')) return
    changeStatus(appt, 'no_show')
}

// ── Dropdown de status do popover — "controle completo", complementar aos
// botões de atalho acima (mesmo endpoint, ver changeStatus). Guardado pelo
// id do agendamento pra nunca ficar aberto "fantasma" ao trocar de popover.
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

// ── Criar agendamento clicando na grade ────────────────────────────────────
const showHolidayModal = ref(false)
const holidayModalInfo = ref({ name: '', dateLabel: '' })

// ── Seleção por arraste (estilo Excel) ──────────────────────────────────
// mousedown na coluna decide sozinho, no mouseup, se virou um arraste ou
// continua sendo um clique simples (ver useAgendaDragSelect) — clickSlot
// só precisa consultar consumeDragFlag() pra não abrir o modal duas vezes
// (uma pelo drag, outra pelo @click nativo que o navegador sempre dispara
// depois de qualquer mouseup/mousedown na mesma coluna).
// ── Bloqueio administrativo ao criar (não altera agendamentos existentes,
// só decide se o modal de CRIAÇÃO abre) ─────────────────────────────────
// Fonte de verdade real continua no backend (assertProfessionalAvailable);
// isto aqui só evita abrir um formulário que o servidor sempre rejeitaria,
// com um recado melhor do que o erro de validação do submit.
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

function openFromInterval(columnKey, startMinutesFromTop, durationMinutes) {
    const col = gridColumns.value.find(c => c.key === columnKey)
    if (!col) return
    const totalMin = gridStartHour.value * 60 + startMinutesFromTop
    const h = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m = String(totalMin % 60).padStart(2, '0')
    const effectiveChairId = col.chairId || (chairFilter.value !== 'all' ? chairFilter.value : undefined)
    attemptOpenApptModal(col.day, `${h}:${m}`, {
        durationMinutes,
        chairId: effectiveChairId,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
}

const dragSelect = useAgendaDragSelect({
    pxPerMin,
    stepMinutes: 15,
    onSelect: openFromInterval,
})

// Rótulo "HH:MM" pro retângulo de seleção durante o arraste — mesmo snap
// de 15min do useAgendaDragSelect, só formatado pra exibição.
function dragTimeLabel(yPx) {
    const minutes  = Math.round(yPx / pxPerMin.value / 15) * 15
    const totalMin = gridStartHour.value * 60 + Math.max(0, minutes)
    const h = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m = String(totalMin % 60).padStart(2, '0')
    return `${h}:${m}`
}

function clickSlot(day, e, chairId) {
    if (dragSelect.consumeDragFlag()) return
    if (activePopover.value) { closePopover(); return }
    if (e.target !== e.currentTarget && e.target.closest('[data-appt]')) return
    const rect       = e.currentTarget.getBoundingClientRect()
    const minutes    = Math.floor(((e.clientY - rect.top) / pxPerMin.value) / 15) * 15
    const totalMin   = gridStartHour.value * 60 + minutes
    const h          = String(Math.floor(totalMin / 60)).padStart(2, '0')
    const m          = String(totalMin % 60).padStart(2, '0')
    // Coluna de recurso (Dia + Todas) informa a própria cadeira; fora dela,
    // herda a cadeira em foco na sidebar (se houver uma específica ativa).
    const effectiveChairId = chairId || (chairFilter.value !== 'all' ? chairFilter.value : undefined)
    attemptOpenApptModal(day, `${h}:${m}`, {
        chairId: effectiveChairId,
        professionalId: profFilter.value !== 'all' ? profFilter.value : undefined,
    })
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

// Destaque do dia selecionado DENTRO da visão Semana (grade principal) —
// reaproveita o mesmo selectedDay usado pela visão Dia, só que sem trocar
// de view: clicar num dia da semana apenas destaca a coluna; se depois o
// usuário for pra "Dia", já abre nesse mesmo dia.
const isWeekSelectedDay = (date) =>
    viewMode.value === 'week' && toDateStr(date) === toDateStr(selectedDay.value)

// Destaque do mini-calendário: SEMPRE o dia (selectedDay), nunca a semana
// inteira — independe de viewMode. "Semana exibida" (quais dias estão
// carregados, via props.weekStart) e "dia selecionado" são conceitos
// diferentes; o mini-calendário só representa o segundo, nunca pinta a
// semana inteira de verde só porque ela é a semana carregada.
const isMiniCalSelected = (date) => toDateStr(date) === toDateStr(selectedDay.value)

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
        { week: wsStr, professional_id: profFilter.value, chair_id: chairFilter.value },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}

function jumpToWeek(date) {
    const d   = new Date(date)
    const dow = d.getDay()
    const ws  = new Date(d)
    ws.setDate(ws.getDate() + (dow === 0 ? -6 : 1 - dow))
    const wsStr = toDateStr(ws)

    if (wsStr === props.weekStart) {
        // Semana já carregada — só atualiza qual dia fica destacado no
        // mini-calendário (ver isMiniCalSelected), sem navegação nenhuma.
        selectedDay.value = d
        return
    }
    pendingDayAfterNav.value = toDateStr(d)
    router.get(route('appointments.index'),
        { week: wsStr },
        { preserveState: true, only: ['appointments', 'weekStart'] })
}


// ── Banda de almoço (posição zoom-aware) ───────────────────────────────────
const lunchBandStyle = computed(() => {
    if (!settings.showLunchBand) return null
    const [lh, lm] = settings.lunchStart.split(':').map(Number)
    const [eh, em] = settings.lunchEnd.split(':').map(Number)
    const topMin    = (lh - gridStartHour.value) * 60 + lm
    const heightMin = (eh - lh) * 60 + (em - lm)
    return {
        top:    `${topMin    * pxPerMin.value}px`,
        height: `${heightMin * pxPerMin.value}px`,
    }
})

// ── Feriados — regra da clínica (não do profissional), afeta qualquer
// agenda visível, inclusive "Todos". Nome vem resolvido do backend
// (BrazilianHolidayService), nenhuma data fica hardcoded aqui.
const holidayNameFor = (date) =>
    props.considerNationalHolidays ? (props.holidays[toDateStr(date)] || null) : null

// ── Fora do horário de atendimento — agora POR COLUNA (dia), usando a
// janela efetiva (regra da clínica ∩ profissional, ver dayWindow acima).
// Generaliza o antigo outOfHoursBands (um único par pra semana toda, só
// quando um profissional específico estava selecionado): sem regra
// obrigatória ativa, o resultado é idêntico a antes — só decora o horário
// do profissional selecionado, nada no modo "Todos". Com regra obrigatória,
// também funciona no modo "Todos" (só a clínica) e varia por dia da semana.
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
// feriado (que já tinha overlay próprio, ver holidayNameFor/template): só
// dispara quando o motivo é especificamente "clínica fechou esse dia",
// pra não duplicar overlay num dia que já é feriado.
function isClinicDayOff(day) {
    const w = dayWindow(day)
    return w.closed && w.reason === 'clinic-day-off'
}

// ── Agendamentos além do teto/piso absoluto da grade (ver GRID_CEIL_HOUR/
// gridStartHour) — nunca fazem a grade crescer, mas continuam acessíveis
// via OffGridAppointmentsBadge (ver template). Não tem relação com a janela
// de horário "normal" (que só decora com banda cinza, sem esconder nada);
// isto aqui é estritamente sobre o que cabe nas linhas realmente desenhadas.
function isApptOffGrid(appt) {
    const s = new Date(appt.start)
    const hourFrac = s.getHours() + s.getMinutes() / 60
    return hourFrac < gridStartHour.value || hourFrac >= END_HOUR
}

function visibleApptsFor(col) {
    return (col.appts || []).filter(a => !isApptOffGrid(a))
}

function offGridApptsFor(col) {
    return (col.appts || []).filter(isApptOffGrid)
}

// ── Agendamento existente fora da janela efetiva ATUAL (ver item 6/7 do
// pedido) — usa o profissional DONO do agendamento (não o filtro
// selecionado), pra funcionar igual em "Todos" e numa agenda específica.
// Só informativo: nunca bloqueia, move ou cancela o agendamento.
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
        occupancy: Math.min(Math.round((usedMin / TOTAL_MIN.value) * 100), 100),
    }
})

// ── Exportar CSV ───────────────────────────────────────────────────────────
function exportCSV() {
    const appts = [...props.appointments]
        .sort((a, b) => new Date(a.start) - new Date(b.start))
    const rows = [['Data', 'Horário', 'Paciente', 'Telefone', 'Profissional', 'Status', 'Observações']]
    appts.forEach(a => rows.push([
        a.start.slice(0, 10),
        `${formatTime(a.start)}-${formatTime(a.end)}`,
        `${a.patient?.nome || ''} ${a.patient?.sobrenome || ''}`.trim(),
        a.patient?.telefone || '',
        a.professional?.name || '',
        STATUS_CONFIG[resolveStatus(a, nowRef)]?.label || a.status,
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
<AppLayout content-width="full">
<!-- content-width sempre "full" (max-w-7xl/1280px + mx-auto, igual
     Pacientes, Consultas etc) — inclusive com os 7 dias da semana (Sáb+Dom
     juntos). Uma tentativa anterior trocava pra "screen" (sem teto) nesse
     caso, mas isso deixava a superfície inteira ~1520px de largura — quase
     do tamanho do Fullscreen — porque as colunas são flex-1 (crescem pra
     preencher o espaço disponível, não só o mínimo): dar mais espaço ao
     container só fazia cada coluna esticar mais, sem resolver nada. A
     causa real do corte de domingo era só o mínimo de 130px não caber
     nesse teto — ver isSevenDayWeek/min-w mais abaixo, que resolve isso
     sozinho, mantendo o Normal sempre com a MESMA largura de container em
     5/6/7 dias (visualmente nunca parece Fullscreen). Só a tela Fullscreen
     (Appointments/Fullscreen.vue) usa "screen"/largura real do monitor —
     é essa diferença que faz "sair da tela cheia" parecer uma mudança de
     verdade. Sem margem negativa aqui: o shell já entrega um respiro
     consistente (--shell-gutter) acima do card via o próprio wrapper
     sticky da TopIsland — não precisa mais "roubar" espaço de volta. A
     altura mínima usa --shell-top-h (zona sticky: gutter + TopIsland +
     gutter) subtraída de 100vh, menos mais um --shell-gutter de respiro
     inferior (o mesmo pb-6 que main já aplica pra toda página) — assim o
     card preenche exatamente o que sobra da viewport, sem forçar scroll
     da página inteira nem deixar espaço vazio embaixo. `min-height`, não
     `height`: quando a grade de 07:00→21:00 (14h fixas, ver
     GRID_FLOOR_HOUR/GRID_CEIL_HOUR) é mais alta que o espaço disponível,
     é a PÁGINA (o scroll-region do AppLayout, barra de rolagem geral do
     navegador) quem rola — de propósito: a rolagem pertence ao usuário,
     não a um contêiner interno da grade com sua própria barra colada ao
     calendário. ── A Agenda inteira (toolbar + sidebar + grade) vive
     dentro de UMA única superfície — borda fina, sombra sutil, cantos
     levemente arredondados — mesmo vocabulário já usado nos cards de
     Configurações (Cadeiras, Agendas etc:
     rounded-2xl/border-slate-200/shadow-sm/bg-white). Não é "card dentro
     de card": toolbar/sidebar/grade continuam divididos por borda interna
     simples, como já eram. -->
<div class="flex flex-col bg-white border border-slate-200 rounded-2xl shadow-sm no-print" style="min-height: calc(100vh - var(--shell-top-h) - var(--shell-gutter))">

  <!-- ── Barra de ferramentas ────────────────────────────────────────────── -->
  <div class="flex items-center gap-2 px-4 py-2 border-b bg-white rounded-t-2xl flex-shrink-0 flex-wrap no-print">

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

    <!-- Label do período — "Agosto 2026" levemente maior que o padrão
         (text-sm=14px→16px); "10/8 – 16/8" no tamanho original (text-xs=
         12px), lado a lado como sempre foi. O grupo é items-baseline (não
         items-center como o resto da toolbar): centralizar pela CAIXA da
         linha faz o texto menor "flutuar" acima da base do texto maior;
         alinhar pela base do texto é o que faz o meio de "Agosto"
         realmente coincidir com o meio de "10/8 – 16/8" visualmente. Nada
         mais na toolbar/grade/sidebar muda de tamanho ou posição. -->
    <div class="flex items-baseline gap-1.5">
      <span class="text-[16px] font-semibold text-slate-700">{{ periodLabel }}</span>
      <span v-if="periodRangeLabel" class="text-xs text-slate-400 hidden sm:inline">
        {{ periodRangeLabel }}
      </span>
    </div>

    <div class="flex-1" />

    <!-- View toggle: Semana | Dia — sempre visível (não mais `hidden sm:flex`):
         é o único jeito de alternar pro modo Dia, que na R5 passou a ser o
         padrão inicial em telas <640px (ver useAgendaSettings.js). Escondê-lo
         nessas mesmas telas deixaria o usuário sem como voltar pra Semana
         se quisesse. -->
    <div class="flex rounded-lg overflow-hidden border border-slate-200 text-xs">
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

    <!-- Zoom controls: um único controle agrupado (borda fina, cantos
         quadrados, sombra bem sutil) — não três elementos soltos. -->
    <div class="hidden sm:flex items-stretch border border-slate-200 bg-white shadow-sm">
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

    <!-- Ações fixas: Configurações + Tela cheia + Novo viajam juntas e
         sempre coladas à direita — mesmo se o restante da barra quebrar
         linha em telas estreitas, esse grupo nunca se separa nem troca de
         posição (ml-auto se auto-alinha à direita em qualquer linha em que
         caia, ver relato "o ícone de full muda de lugar"). -->
    <div class="flex items-center gap-2 ml-auto flex-shrink-0">
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
            <input type="range" :min="ZOOM_MIN" :max="ZOOM_MAX" :step="ZOOM_STEP"
                   :value="zoomLevel"
                   @input="zoomLevel = parseFloat($event.target.value)"
                   class="w-full h-1 accent-emerald-500" />
            <div class="text-[10px] text-slate-400 text-center mt-1">{{ Math.round(zoomLevel * 100) }}%</div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Novo agendamento -->
    <button @click="openNewAppointmentModal"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors">
      + Novo
    </button>

    <!-- Tela cheia — agora depois de "+ Novo" (mais à direita da toolbar);
         ml-8 soma ao gap-2 do grupo (~40px ≈ 1cm no total) pra separar
         visualmente do botão verde sem quebrar o bloco único ancorado à
         direita. Escondido abaixo de 640px por decisão deliberada: Tela
         cheia é Desktop/Tablet only (ver Fullscreen.vue, que recusa a
         mesma faixa de largura) — não existe versão mobile dela, então o
         botão não pode ficar ali como uma entrada morta. -->
    <Link :href="route('appointments.fullscreen', { week: weekStart })"
          class="ml-8 hidden sm:block p-1.5 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
          title="Tela cheia">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
      </svg>
    </Link>
    </div>
  </div>

  <!-- ── Corpo: sidebar + calendário ────────────────────────────────────── -->
  <div class="flex flex-1 overflow-hidden">

    <!-- ── Sidebar esquerda ─────────────────────────────────────────────── -->
    <transition name="agenda-sidebar">
      <div v-show="showSidebar"
           class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50/40 rounded-bl-2xl flex flex-col gap-3 p-3 overflow-y-auto overflow-x-hidden no-print">

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

        <!-- Mini calendário body -->
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
                        'text-slate-600 hover:bg-slate-100':             day.cur && !isToday(day.date) && !isMiniCalSelected(day.date),
                        'bg-emerald-500 text-white font-semibold rounded-full': day.cur && isMiniCalSelected(day.date),
                        'ring-1 ring-inset ring-emerald-400 rounded-full': day.cur && isToday(day.date) && !isMiniCalSelected(day.date),
                      }">
                {{ day.date.getDate() }}
              </button>
            </div>
          </div>
        </transition>
        </div>
        <!-- /Card: Calendário -->

        <!-- Card: Cadeiras — ordem fixa: cadeiras primeiro (na ordem de
             criação), "Todas" sempre por último — nunca no topo. Mesmo
             padrão visual dos "Escopos" do módulo de Tarefas: bolinha
             colorida + nome, engrenagem/editar só aparece no hover. -->
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

              <!-- "Todas" sempre por último, nunca antes das cadeiras. -->
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

        <!-- Card: Agendas — conceito diferente de Cadeiras: cadeira é
             recurso físico, agenda é do profissional. Usuário logado
             sempre primeiro, demais depois (ordem já resolvida pelo
             backend — ver AppointmentController::agendaProfessionalsPayload),
             "Todos" sempre por último e fora da área com scroll (fica
             sempre acessível sem precisar rolar até o fim da lista). -->
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
              <!-- Até 4 profissionais visíveis, scroll interno a partir daí. -->
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

        <!-- Resumo diário — bloco leve de apoio, não é um dos 3 cards
             principais da sidebar (Calendário/Cadeiras/Agendas). -->
        <div class="px-1 flex-1 flex flex-col">
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

    <!-- ── Grade do calendário ─────────────────────────────────────────── ──
         overflow-x-auto (não overflow-auto): a rolagem vertical deste
         container pertence à PÁGINA, não a ele — só o eixo horizontal
         (necessário quando há muitas colunas de dia/cadeira/recurso) fica
         com scroll próprio aqui. overflow-y-hidden (não visible): a altura
         em px de `gridHeight` (calculada em JS, ver pxPerMin) quase sempre
         bate exatamente com a altura real da caixa, mas sobra de
         arredondamento de subpixel é inevitável — 1-2px de diferença já
         bastam pra o navegador desenhar uma scrollbar overflow-auto
         inteira aqui do lado do calendário (era essa a barra "grudada" que
         devia sumir). hidden aqui só absorve esse resto de subpixel, não
         esconde conteúdo real: nada some, ver QA. (overflow-y: visible
         NÃO resolveria — o spec de CSS promove `visible` de volta pra
         `auto` quando o outro eixo já é `auto`, então o quirk voltaria.) -->
    <div ref="gridScrollRef" class="flex-1 overflow-x-auto overflow-y-hidden bg-slate-50/40 rounded-br-2xl relative">

      <!-- Cabeçalho dos dias/cadeiras (sticky) — em Dia + "Todas" as
           cadeiras, cada coluna vira um recurso (estilo Codental) em vez de
           um dia; nas demais visões continua sendo um dia, como sempre. -->
      <div class="flex bg-white border-b sticky top-0 z-20" style="min-width: max-content">
        <div class="w-14 flex-shrink-0 border-r bg-white sticky left-0 z-40" />
        <div v-for="col in gridColumns" :key="'hd-' + col.key"
             class="flex-1 text-center py-2.5 border-r last:border-r-0 transition-colors"
             :class="[
               col.isResource ? '' : 'cursor-pointer',
               !col.isResource && isWeekSelectedDay(col.day)
                 ? 'bg-emerald-100/70 border-b-2 border-b-emerald-500'
                 : (!col.isResource && isToday(col.day) ? 'bg-emerald-50/80' : 'hover:bg-slate-50'),
               viewMode === 'week' ? (isSevenDayWeek ? 'min-w-[110px]' : 'min-w-[130px]') : (showResourceColumns ? 'min-w-[180px]' : 'min-w-[200px]'),
             ]"
             @click="!col.isResource && viewMode === 'week' && (selectedDay = col.day)">
          <template v-if="col.isResource">
            <div class="flex items-center justify-center gap-1.5">
              <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ backgroundColor: col.color }" />
              <span class="text-sm font-bold leading-tight text-slate-700 truncate">{{ col.label }}</span>
            </div>
            <div class="text-[9px] text-slate-400 leading-tight mt-0.5">
              {{ (col.appts || []).length }} agendamento{{ (col.appts || []).length === 1 ? '' : 's' }}
            </div>
          </template>
          <template v-else>
            <div class="text-[10px] font-semibold uppercase tracking-wide"
                 :class="isWeekSelectedDay(col.day) ? 'text-emerald-700' : (isToday(col.day) ? 'text-emerald-500' : 'text-slate-400')">
              {{ PT_DAYS[dayIndex(col.day)] }}
            </div>
            <div class="text-lg font-bold leading-tight"
                 :class="isWeekSelectedDay(col.day) ? 'text-emerald-800' : (isToday(col.day) ? 'text-emerald-600' : 'text-slate-700')">
              {{ col.day.getDate() }}
            </div>
            <div v-if="viewMode === 'week'" class="text-[9px] text-slate-400 leading-tight">
              {{ PT_MONTHS[col.day.getMonth()].slice(0, 3) }}
            </div>
            <div v-if="holidayNameFor(col.day)" class="text-[8px] font-semibold text-amber-600 leading-tight truncate mt-0.5"
                 :title="holidayNameFor(col.day)">
              Feriado
            </div>
          </template>
        </div>
      </div>

      <!-- Grade de tempo -->
      <div class="flex" :style="{ height: gridHeight + 'px', minWidth: 'max-content' }">

        <!-- Coluna de horas — sticky à esquerda: continua visível mesmo
             rolando a grade horizontalmente (muitas cadeiras/dias). -->
        <div class="w-14 flex-shrink-0 border-r bg-white relative sticky left-0 z-40">
          <div v-for="h in hours" :key="'th-' + h"
               class="absolute right-0 pr-2 text-[10px] text-slate-400 text-right tabular-nums"
               :class="h === gridStartHour ? 'translate-y-0.5' : '-translate-y-1/2'"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px` }">
            {{ formatHour(h) }}
          </div>
        </div>

        <!-- Colunas dos dias/cadeiras -->
        <div v-for="col in gridColumns" :key="'dc-' + col.key"
             class="flex-1 relative border-r last:border-r-0"
             :class="[
               !col.isResource && isWeekSelectedDay(col.day)
                 ? 'bg-emerald-50/40'
                 : (!col.isResource && isToday(col.day) ? 'bg-blue-50/10' : 'bg-white'),
               viewMode === 'week' ? (isSevenDayWeek ? 'min-w-[110px]' : 'min-w-[130px]') : (showResourceColumns ? 'min-w-[180px]' : 'min-w-[200px]'),
             ]"
             @mousedown="dragSelect.onPointerDown($event, col.key)"
             @click="clickSlot(col.day, $event, col.chairId)">

          <!-- Seleção por arraste em andamento (estilo Excel) — só nesta
               coluna, some assim que o mouseup decide abrir o modal. -->
          <div v-if="dragSelect.dragging.value && dragSelect.dragColumnKey.value === col.key"
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

          <!-- Bandas alternadas por hora — leve relevo/organização visual
               (mesmo princípio da "banda de almoço" já existente: tinta
               translúcida, sem cor nova, não interfere no destaque de
               "hoje" por baixo por ser semi-transparente). -->
          <div v-for="(h, i) in hours" :key="'hb-' + h"
               v-show="i % 2 === 1"
               class="absolute w-full pointer-events-none bg-slate-100/50"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px`, height: `${60 * pxPerMin}px` }" />

          <!-- Grade principal, 30 min (:00 e :30) — sempre visível, mesmo
               peso visual nas duas. -->
          <div v-for="h in hours" :key="'hl-' + h"
               class="absolute w-full border-t border-slate-100"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin}px` }" />
          <div v-for="h in hours" :key="'hl30-' + h"
               class="absolute w-full border-t border-slate-100"
               :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 30 * pxPerMin}px` }" />

          <!-- Subdivisão fina, 15 min (:15 e :45) — discreta/pontilhada,
               continua atrás do toggle "Mostrar grade de meia hora" (agora
               controla só este nível mais fino). -->
          <template v-if="settings.showSecondaryGrid">
            <div v-for="h in hours" :key="'hl15-' + h"
                 class="absolute w-full border-t border-dashed border-slate-100/70"
                 :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 15 * pxPerMin}px` }" />
            <div v-for="h in hours" :key="'hl45-' + h"
                 class="absolute w-full border-t border-dashed border-slate-100/70"
                 :style="{ top: `${(h - gridStartHour) * 60 * pxPerMin + 45 * pxPerMin}px` }" />
          </template>

          <!-- Banda de almoço -->
          <div v-if="lunchBandStyle"
               class="absolute left-0 right-0 pointer-events-none z-[1] bg-slate-50/70 border-y border-slate-200/40"
               :style="lunchBandStyle">
            <span class="text-[9px] text-slate-300 px-1 select-none">almoço</span>
          </div>

          <!-- Fora do horário de atendimento — banda cinza suave (área
               "sem novos agendamentos", não invisível — ver
               outOfHoursBandsFor). Sem regra obrigatória ativa, reflete só
               o horário do profissional selecionado (comportamento de
               sempre); com regra ativa, também funciona no modo "Todos" e
               varia por dia. -->
          <div v-for="(band, i) in outOfHoursBandsFor(col.day)" :key="'oh-' + i"
               class="absolute left-0 right-0 pointer-events-none z-[1] bg-slate-200/70"
               :class="band.pos === 'after' ? 'border-t-2 border-slate-300' : 'border-b-2 border-slate-300'"
               :style="band.style">
            <span class="block text-center text-[8px] font-semibold uppercase tracking-wide text-slate-400 pt-1">
              Fora do horário
            </span>
          </div>

          <!-- Feriado — dia inteiro sem atendimento, regra da clínica,
               afeta qualquer agenda visível (inclusive "Todos"). -->
          <div v-if="!col.isResource && holidayNameFor(col.day)"
               class="absolute inset-0 pointer-events-none z-[1] bg-amber-50/50" />

          <!-- Dia bloqueado por regra administrativa obrigatória (sábado/
               domingo "não trabalha", ou horário fechado esse dia) — cinza,
               não âmbar, pra não parecer feriado. Agendamentos existentes
               continuam renderizados normalmente por cima (ver mais abaixo),
               nunca escondidos. -->
          <div v-if="!col.isResource && isClinicDayOff(col.day)"
               class="absolute inset-0 pointer-events-none z-[1] bg-slate-100/70" />
          <div v-if="!col.isResource && isClinicDayOff(col.day)"
               class="absolute top-1.5 left-1.5 right-1.5 z-[1] pointer-events-none text-center text-[9px] font-medium text-slate-400">
            Sem atendimento
          </div>

          <!-- Linha do horário atual -->
          <div v-if="settings.showNowLine && isToday(col.day) && nowTop >= 0 && nowTop <= gridHeight"
               class="absolute left-0 right-0 z-10 pointer-events-none flex items-center"
               :style="{ top: `${nowTop}px` }">
            <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1.5 flex-shrink-0 shadow-md ring-2 ring-red-200" />
            <div class="flex-1 h-px bg-red-400/70" />
          </div>

          <!-- Cards de agendamento (premium) -->
          <div v-for="appt in visibleApptsFor(col)" :key="appt.id"
               data-appt="1"
               class="absolute border-l-[3px] overflow-visible cursor-pointer select-none
                      shadow-sm hover:shadow-lg ring-1 ring-black/[0.04]
                      transition-all duration-150 hover:-translate-y-px"
               :class="[
                 s(appt).bg,
                 s(appt).border,
                 settings.compactMode ? 'rounded-md' : 'rounded-lg',
                 isPastAppt(appt) ? 'opacity-60 saturate-50' : '',
               ]"
               :style="apptStyle(appt)"
               @mouseenter="showTooltipDelayed(appt, $event)"
               @mouseleave="hideTooltip"
               @click.stop="openPopover(appt, $event)">

            <!-- Etiquetas: até 2 bolinhas + "N+" se houver mais, canto
                 superior direito — status já é a cor/borda do card, isto
                 aqui é só a etiqueta de verdade (PatientTag), nunca pisca. -->
            <div v-if="appt.tags?.length" class="absolute top-1 right-1 z-20 flex items-center gap-0.5"
                 :title="appt.tags.map(t => t.name).join(', ')">
              <span v-for="tag in appt.tags.slice(0, 2)" :key="tag.id"
                    class="h-1.5 w-1.5 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />
              <span v-if="appt.tags.length > 2" class="text-[7px] font-bold leading-none text-slate-500">{{ appt.tags.length - 2 }}+</span>
            </div>

            <div class="h-full flex flex-col justify-start gap-px"
                 :class="settings.compactMode ? 'px-1 pt-0.5' : 'px-1.5 pt-1'">
              <!-- Paciente: primeiro e mais proeminente do card — nome
                   curto (até 3 palavras, ver shortPatientName); o nome
                   completo continua acessível no hover/clique (tooltip e
                   popover). -->
              <div class="text-[11px] font-bold leading-tight truncate"
                   :class="s(appt).text">
                {{ shortPatientName(appt) }}
              </div>
              <!-- Horário: logo abaixo do nome, em negrito e um pouco maior. -->
              <div class="text-[10px] font-bold text-slate-500 leading-none tabular-nums">
                {{ formatTime(appt.start) }}–{{ formatTime(appt.end) }}
              </div>
              <!-- Fora do horário atual da clínica — só informativo (ver
                   item 6/7 do pedido); nunca altera/move o agendamento. -->
              <div v-if="apptScheduleNotice(appt)"
                   class="flex items-center gap-0.5 text-[8px] font-semibold text-amber-600 leading-none truncate"
                   :title="apptScheduleNotice(appt)">
                <svg class="w-2 h-2 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.28 11.18c.75 1.334-.213 2.987-1.742 2.987H3.72c-1.53 0-2.493-1.653-1.743-2.987l6.28-11.18zM10 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                Fora do horário
              </div>
            </div>
          </div>

          <!-- Agendamentos além do teto/piso absoluto da grade — nunca
               fazem a grade crescer, continuam acessíveis por aqui. -->
          <OffGridAppointmentsBadge
              class="absolute bottom-0 left-0 right-0 z-20"
              :appointments="offGridApptsFor(col)"
              :format-time="formatTime"
              :patient-name="a => shortPatientName(a)"
              @select="(appt, e) => openPopover(appt, e)" />
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
        <div class="px-3 py-2.5 border-b" :class="s(tooltipAppt).bg">
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
          <div class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Profissional</span>
            <span class="text-[10px] text-slate-700">{{ tooltipAppt.professional?.name || '—' }}</span>
          </div>
          <div class="flex gap-2 items-center">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0">Cadeira</span>
            <span class="text-[10px] text-slate-700 flex items-center gap-1">
              <span v-if="tooltipAppt.chair" class="h-1.5 w-1.5 rounded-full shrink-0" :style="{ backgroundColor: tooltipAppt.chair.color }" />
              {{ tooltipAppt.chair?.name || 'Sem cadeira' }}
            </span>
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
            <StatusIndicator
              :status="resolveStatus(tooltipAppt, nowRef)"
              :delay-minutes="getDelayMinutes(tooltipAppt, nowRef)"
              show-label />
          </div>
          <div v-if="tooltipAppt.tags?.length" class="flex gap-2">
            <span class="text-[9px] uppercase tracking-wide text-slate-400 w-20 flex-shrink-0 pt-px">Etiquetas</span>
            <span class="flex flex-wrap gap-1">
              <span v-for="tag in tooltipAppt.tags" :key="tag.id"
                    class="inline-flex items-center gap-1 text-[9px] text-slate-600">
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

<!-- ── Popover (clique) — Teleport ─────────────────────────────────────── -->
<Teleport to="body">
  <div v-if="activePopover"
       ref="popoverRef"
       class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200 no-print"
       :style="popoverStyle">

    <!-- Header: paciente é o elemento mais importante; confirmação por
         WhatsApp/e-mail logo abaixo do nome, pequenas e lado a lado (só
         interface preparada — ver notifyContactComingSoon, nunca finge um
         envio); status (segundo mais importante) logo abaixo delas —
         dropdown com controle completo (mesmo endpoint dos botões de
         atalho, ver changeStatus). Sem horário aqui — já aparece uma única
         vez, no bloco Data/Horário abaixo — e sem tratamento/procedimento. -->
    <div class="px-4 py-3 border-b rounded-t-xl relative" :class="s(activePopover).bg">
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
        <button @click="closePopover"
                class="p-0.5 rounded hover:bg-black/10 text-slate-400 flex-shrink-0 mt-0.5">
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
      <!-- Só informativo (ver item 6/7 do pedido) — nunca bloqueia a
           consulta nem altera o agendamento, apenas avisa que ele foi
           criado antes de uma mudança de regra administrativa. -->
      <div v-if="apptScheduleNotice(activePopover)" class="flex items-center gap-2 text-xs text-amber-600">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.28 11.18c.75 1.334-.213 2.987-1.742 2.987H3.72c-1.53 0-2.493-1.653-1.743-2.987l6.28-11.18zM10 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
        {{ apptScheduleNotice(activePopover) }}
      </div>
    </div>

    <!-- Info -->
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
          <span v-for="tag in activePopover.tags" :key="tag.id"
                class="inline-flex items-center gap-1 text-[10px] text-slate-600">
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

    <!-- Ações rápidas -->
    <div class="p-3 grid grid-cols-2 gap-2">
      <button v-if="activePopover.status === 'scheduled'"
              :disabled="quickActionBusy"
              @click="quickConfirm(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 transition-colors border border-green-200 disabled:opacity-50 disabled:cursor-not-allowed">
        Confirmar
      </button>
      <button v-if="['scheduled', 'confirmed'].includes(activePopover.status)"
              :disabled="quickActionBusy"
              @click="quickCheckin(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 transition-colors border border-blue-200 disabled:opacity-50 disabled:cursor-not-allowed">
        Check-in
      </button>
      <Link v-if="activePopover.consultation"
            :href="route('consultations.show', activePopover.consultation.id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-violet-50 hover:bg-violet-100 text-violet-700 transition-colors border border-violet-200">
        Prontuário
      </Link>
      <button type="button" @click="openEditApptModal(activePopover)"
              class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 transition-colors border border-slate-200">
        Editar
      </button>
      <Link :href="route('patients.show', activePopover.patient_id)"
            class="text-center text-xs font-medium px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 transition-colors border border-slate-200">
        Ver paciente
      </Link>
      <button v-if="!['cancelled', 'no_show', 'completed'].includes(activePopover.status)"
              :disabled="quickActionBusy"
              @click="quickNoShow(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors border border-red-200 disabled:opacity-50 disabled:cursor-not-allowed">
        Faltou
      </button>
      <button v-if="!['cancelled', 'no_show', 'completed'].includes(activePopover.status)"
              :disabled="quickActionBusy"
              @click="quickCancel(activePopover)"
              class="text-xs font-medium px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors border border-red-200 disabled:opacity-50 disabled:cursor-not-allowed">
        Cancelar
      </button>
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
        <span class="text-slate-500 w-36 flex-shrink-0 truncate">{{ appt.chair?.name }}</span>
        <span class="text-slate-400 flex-shrink-0">{{ appt.professional?.name }}</span>
        <span class="text-slate-400 ml-auto flex-shrink-0">{{ STATUS_CONFIG[resolveStatus(appt, nowRef)]?.label }}</span>
      </div>
    </div>
    <p v-else class="text-slate-400 text-sm italic">Nenhum agendamento</p>
  </div>
</div>

<ChairFormModal :show="showChairModal" :chair="editingChair"
                @close="showChairModal = false"
                @saved="onChairSaved"
                @deleted="onChairDeleted" />

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
/* ── Sidebar collapse ─────────────────────────────────────────────────── */
.agenda-sidebar-enter-active,
.agenda-sidebar-leave-active {
  transition: max-width 0.22s ease, opacity 0.18s ease;
  overflow: hidden;
}
.agenda-sidebar-enter-from,
.agenda-sidebar-leave-to  { max-width: 0 !important; opacity: 0; }
.agenda-sidebar-enter-to,
.agenda-sidebar-leave-from { max-width: 20rem; opacity: 1; }

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
