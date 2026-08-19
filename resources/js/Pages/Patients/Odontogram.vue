<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import OdontogramChart from '@/Components/Prontuario/OdontogramChart.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    patient:           Object,
    odontogram:        { type: Object, default: () => ({}) },
    toothStatuses:     { type: Array,  default: () => [] },
    treatmentsByTooth: { type: Object, default: () => ({}) },
    hub:               { type: Object, default: () => ({}) },
    photos:            { type: Array,  default: () => [] },
    isDriveConnected:  Boolean,
    responsibleTeam:   { type: Array,  default: () => [] },
    lastAppointmentAt: { default: null },
    initialPhotoId:    { default: null },
})

// ── Odontogram state ────────────────────────────────────────────────────────
const teethData  = ref({ ...(props.odontogram?.teeth_data ?? {}) })
const isDirty    = ref(false)
const isSaving   = ref(false)
const saveStatus = ref('saved')

const onUpdate = (data) => {
    teethData.value  = data
    isDirty.value    = true
    saveStatus.value = 'unsaved'
}

const save = (updatedData) => {
    if (updatedData) teethData.value = updatedData
    isSaving.value   = true
    saveStatus.value = 'saving'
    router.put(route('patients.prontuario.odontogram', props.patient.id), {
        teeth_data: teethData.value,
    }, {
        preserveScroll: true,
        preserveState:  true,
        onSuccess: () => { isDirty.value = false; isSaving.value = false; saveStatus.value = 'saved' },
        onError:   () => { isSaving.value = false; saveStatus.value = 'error' },
    })
}

// ── Stats ───────────────────────────────────────────────────────────────────
const MAXILA    = ['11','12','13','14','15','16','17','18','21','22','23','24','25','26','27','28']
const MANDIBULA = ['31','32','33','34','35','36','37','38','41','42','43','44','45','46','47','48']

function regionStats(teeth) {
    let withProc = 0, inProgress = 0, removed = 0
    for (const t of teeth) {
        const tooth = teethData.value[t]
        if (!tooth) continue
        if (tooth.removed || tooth.status === 'ausente') { removed++; continue }
        const treatments = props.treatmentsByTooth[t] ?? []
        if (treatments.length) withProc++
        if (treatments.some(x => x.status === 'em_andamento')) inProgress++
    }
    return { withProc, inProgress, removed, hasAlert: inProgress > 0 || removed > 0 }
}

const maxila    = computed(() => regionStats(MAXILA))
const mandibula = computed(() => regionStats(MANDIBULA))

const stats = computed(() => {
    let completed = 0, inProgress = 0, removed = 0
    const all = [...MAXILA, ...MANDIBULA]
    for (const t of all) {
        const tooth = teethData.value[t]
        if (!tooth) continue
        if (tooth.removed || tooth.status === 'ausente') { removed++; continue }
        const treatments = props.treatmentsByTooth[t] ?? []
        if (treatments.length) {
            if (treatments.some(x => x.status === 'em_andamento')) inProgress++
            else if (treatments.every(x => x.status === 'concluido')) completed++
        }
    }
    return { completed, inProgress, removed }
})

const goToTreatments = (tooth) => {
    router.visit(route('patients.show', props.patient.id) + `?tab=treatments` + (tooth ? `&tooth=${tooth}` : ''))
}

// ── Patient info ────────────────────────────────────────────────────────────
const patientFullName = computed(() =>
    `${props.patient.nome} ${props.patient.sobrenome}`.trim()
)

const patientAge = computed(() => {
    if (!props.patient.nascimento) return null
    const birth = new Date(props.patient.nascimento)
    const today = new Date()
    let age = today.getFullYear() - birth.getFullYear()
    const m = today.getMonth() - birth.getMonth()
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--
    return age
})

const fmtDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const fmtCurrency = (v) =>
    v ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : null

// ── Hub data ────────────────────────────────────────────────────────────────
const lastEvolutions = computed(() =>
    (props.hub?.timeline ?? [])
        .filter(e => ['evolution', 'procedure_completed', 'attendance_started'].includes(e.type))
        .slice(0, 6)
)

const lastTreatments = computed(() =>
    (props.hub?.treatments ?? [])
        .filter(t => t.status !== 'cancelado')
        .slice(0, 6)
)

const patientTimeline = computed(() =>
    (props.hub?.timeline ?? []).slice(0, 12)
)

const evIcon = (type) => {
    if (type === 'evolution') return '📝'
    if (type === 'procedure_completed') return '✓'
    if (type === 'attendance_started') return '👨‍⚕️'
    return '•'
}

const tlDotClass = (type) => {
    const map = {
        procedure_completed:   'bg-emerald-400',
        appointment_confirmed: 'bg-emerald-400',
        check_in:              'bg-emerald-400',
        file_restored:         'bg-emerald-400',
        payment_received:      'bg-emerald-500',
        budget_aprovado:       'bg-emerald-400',
        evolution:             'bg-blue-400',
        attendance_started:    'bg-blue-400',
        file_added:            'bg-purple-400',
        appointment_created:   'bg-slate-300',
        budget_rascunho:       'bg-slate-300',
        appointment_cancelled: 'bg-red-400',
        appointment_no_show:   'bg-amber-400',
        budget_rejeitado:      'bg-red-400',
    }
    return map[type] ?? 'bg-slate-300'
}

// Nota: esta lista alimenta `hub.treatments`, um feed combinado de
// clinical_records + orçamentos + patient_treatments (ver
// PatientHubService::treatments()) — "planejado" aqui é status de
// ORÇAMENTO (Budget), não de PatientTreatment (esse não existe mais como
// status de tratamento; ver PatientTreatment::STATUSES). Não remover.
const treatStatusCls = (s) => {
    if (s === 'concluido')    return 'bg-emerald-100 text-emerald-700'
    if (s === 'futuro')       return 'bg-purple-100 text-purple-700'
    if (s === 'planejado')    return 'bg-blue-100 text-blue-700'
    if (s === 'em_andamento') return 'bg-orange-100 text-orange-700'
    return 'bg-slate-100 text-slate-600'
}

const treatStatusLabel = (s) => {
    if (s === 'concluido')    return 'Finalizado'
    if (s === 'futuro')       return 'Futuro'
    if (s === 'planejado')    return 'Planejado'
    if (s === 'em_andamento') return 'Em andamento'
    return s
}

// ── Image comparison ────────────────────────────────────────────────────────
const selectedPhoto   = ref(null)
const imageZoom       = ref(1)
const imageRotation   = ref(0)
const imageFullscreen  = ref(false)
const comparisonMode   = ref(false)
const comparisonLayout = ref('stacked') // 'stacked' | 'split'
const brokenThumbs     = ref(new Set())

const selectPhoto = (photo) => {
    if (selectedPhoto.value?.id === photo.id) { clearPhoto(); return }
    selectedPhoto.value = photo
    imageZoom.value     = 1
    imageRotation.value = 0
}

const clearPhoto = () => {
    selectedPhoto.value   = null
    imageZoom.value       = 1
    imageRotation.value   = 0
    imageFullscreen.value = false
    comparisonMode.value  = false
}

const zoomIn  = () => { imageZoom.value = Math.min(5, +(imageZoom.value + 0.25).toFixed(2)) }
const zoomOut = () => { imageZoom.value = Math.max(0.25, +(imageZoom.value - 0.25).toFixed(2)) }
const resetZoom = () => { imageZoom.value = 1 }
const rotate    = () => { imageRotation.value = (imageRotation.value + 90) % 360 }

const driveUrl = (photo) => photo?.drive_file_id
    ? `https://drive.google.com/file/d/${photo.drive_file_id}/view`
    : null

const downloadPhoto = (photo) => {
    if (!photo) return
    const a = document.createElement('a')
    a.href     = route('patients.photos.view', [props.patient.id, photo.id])
    a.download = photo.filename || photo.subcategoria || 'foto'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
}

const imageStyle = computed(() => ({
    transform:       `scale(${imageZoom.value}) rotate(${imageRotation.value}deg)`,
    transition:      'transform 0.2s ease',
    transformOrigin: 'center center',
    maxWidth:        '100%',
    maxHeight:       '100%',
}))

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (imageFullscreen.value) { imageFullscreen.value = false; return }
        if (comparisonMode.value)  { comparisonMode.value  = false; return }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown)
    if (props.initialPhotoId && props.photos?.length) {
        const found = props.photos.find(p => p.id === props.initialPhotoId)
        if (found) selectedPhoto.value = found
    }
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
    <AppLayout>

        <!-- ── Navegação de volta ──────────────────────────────────────────── -->
        <div class="mb-5">
            <Link :href="route('patients.show', patient.id)"
                  class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Voltar para paciente
            </Link>
        </div>

        <!-- ── Info do paciente ───────────────────────────────────────────── -->
        <div class="mb-6 bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ patientFullName }}</h1>
                    <p class="text-sm text-slate-400 mt-0.5">Odontograma clínico</p>
                </div>
                <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold border"
                      :class="patient.status === 'ativo' || !patient.status
                          ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                          : 'bg-slate-100 text-slate-500 border-slate-200'">
                    {{ patient.status ?? 'ativo' }}
                </span>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-8 gap-y-3">
                <div v-if="patient.doc_numero">
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400">
                        {{ patient.doc_tipo || 'CPF' }}
                    </p>
                    <p class="text-sm text-slate-800 mt-0.5">{{ patient.doc_numero }}</p>
                </div>
                <div v-if="patientAge !== null">
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400">Idade</p>
                    <p class="text-sm text-slate-800 mt-0.5">{{ patientAge }} anos</p>
                </div>
                <div v-if="patient.telefone">
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400">Telefone</p>
                    <p class="text-sm text-slate-800 mt-0.5">{{ patient.telefone }}</p>
                </div>
                <div v-if="lastAppointmentAt">
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400">Último atendimento</p>
                    <p class="text-sm text-slate-800 mt-0.5">{{ fmtDate(lastAppointmentAt) }}</p>
                </div>
                <div v-if="responsibleTeam.length">
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400">Profissional</p>
                    <p class="text-sm text-slate-800 mt-0.5">{{ responsibleTeam[0].name }}</p>
                    <p v-if="responsibleTeam[0].job_title" class="text-[11px] text-slate-400">{{ responsibleTeam[0].job_title }}</p>
                </div>
            </div>
        </div>

        <!-- ── Odontograma ────────────────────────────────────────────────── -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        🦷 Odontograma
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Toque ou clique em um dente para consultar e editar
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <span v-if="stats.completed"
                          class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200">
                        ✓ {{ stats.completed }} concluído{{ stats.completed !== 1 ? 's' : '' }}
                    </span>
                    <span v-if="stats.inProgress"
                          class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-orange-50 text-orange-700 border-orange-200">
                        ● {{ stats.inProgress }} em andamento
                    </span>
                    <span v-if="stats.removed"
                          class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-red-50 text-red-700 border-red-200">
                        ✕ {{ stats.removed }} removido{{ stats.removed !== 1 ? 's' : '' }}
                    </span>

                    <span v-if="saveStatus === 'saved' && !isDirty"
                          class="text-[11px] text-slate-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Salvo
                    </span>
                    <span v-if="saveStatus === 'error'" class="text-[11px] text-red-500 flex items-center gap-1">
                        ⚠ Erro ao salvar
                    </span>
                    <button v-if="isDirty"
                            @click="save(null)"
                            :disabled="isSaving"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-teal-600 text-white hover:bg-teal-700 shadow-sm disabled:opacity-60">
                        <svg v-if="isSaving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ isSaving ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>

            <!-- Region badges -->
            <div class="flex flex-wrap gap-2 mb-5">
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium"
                     :class="maxila.hasAlert ? 'bg-teal-50 border-teal-200 text-teal-700' : 'bg-slate-50 border-slate-200 text-slate-500'">
                    <span class="font-bold">Maxila</span>
                    <span v-if="maxila.withProc" class="text-[10px] opacity-70">{{ maxila.withProc }} proc.</span>
                    <span v-if="maxila.removed" class="text-[10px] opacity-70">{{ maxila.removed }} aus.</span>
                    <span v-if="maxila.hasAlert"
                          class="w-4 h-4 rounded-full bg-orange-400 text-white text-[9px] font-black flex items-center justify-center leading-none shrink-0">
                        !
                    </span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium"
                     :class="mandibula.hasAlert ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-slate-50 border-slate-200 text-slate-500'">
                    <span class="font-bold">Mandíbula</span>
                    <span v-if="mandibula.withProc" class="text-[10px] opacity-70">{{ mandibula.withProc }} proc.</span>
                    <span v-if="mandibula.removed" class="text-[10px] opacity-70">{{ mandibula.removed }} aus.</span>
                    <span v-if="mandibula.hasAlert"
                          class="w-4 h-4 rounded-full bg-orange-400 text-white text-[9px] font-black flex items-center justify-center leading-none shrink-0">
                        !
                    </span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium bg-slate-50 border-slate-200 text-slate-500">
                    <span class="font-bold">Arcada Superior</span>
                    <span class="text-[10px] opacity-70">{{ MAXILA.length }} dentes</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium bg-slate-50 border-slate-200 text-slate-500">
                    <span class="font-bold">Arcada Inferior</span>
                    <span class="text-[10px] opacity-70">{{ MANDIBULA.length }} dentes</span>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-gradient-to-b from-slate-50/80 to-white border border-slate-200 rounded-2xl px-4 py-6 shadow-sm">
                <OdontogramChart
                    :teeth-data="teethData"
                    :tooth-statuses="toothStatuses"
                    :treatments-by-tooth="treatmentsByTooth"
                    @update:teeth-data="onUpdate"
                    @save="save"
                    @open-treatments="goToTreatments" />
            </div>
        </div>

        <!-- ── Comparação de imagens ───────────────────────────────────────── -->
        <div v-if="photos.length" class="mt-6 bg-white rounded-2xl border border-slate-200 overflow-hidden">

            <!-- Cabeçalho da seção -->
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-slate-800">Comparação com Imagens</h3>
                    <span v-if="selectedPhoto" class="text-xs text-slate-400 truncate">
                        · {{ selectedPhoto.subcategoria || selectedPhoto.filename }}
                    </span>
                </div>
                <button v-if="selectedPhoto"
                        @click="clearPhoto"
                        class="shrink-0 text-xs text-slate-400 hover:text-slate-700 flex items-center gap-1 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Fechar comparação
                </button>
            </div>

            <!-- Imagem selecionada -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2">
                <div v-if="selectedPhoto" class="p-6 border-b border-slate-100">

                    <!-- Controles -->
                    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                        <!-- Zoom + rotação -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button @click="zoomOut"
                                    :disabled="imageZoom <= 0.25"
                                    title="Zoom -"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                                </svg>
                            </button>
                            <span class="min-w-[48px] text-center text-xs font-mono text-slate-600 border border-slate-200 rounded-lg px-2 h-8 flex items-center justify-center select-none">
                                {{ Math.round(imageZoom * 100) }}%
                            </span>
                            <button @click="zoomIn"
                                    :disabled="imageZoom >= 5"
                                    title="Zoom +"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                </svg>
                            </button>
                            <button @click="resetZoom"
                                    class="h-8 px-3 text-xs font-medium rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                100%
                            </button>
                            <button @click="rotate"
                                    title="Rotacionar"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Ações -->
                        <div class="flex items-center gap-1.5">
                            <button @click="comparisonMode = true"
                                    title="Modo comparação"
                                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-teal-300 bg-teal-50 text-teal-700 hover:bg-teal-100 text-xs font-medium transition-colors">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                                </svg>
                                Comparar
                            </button>
                            <button @click="imageFullscreen = true"
                                    title="Tela cheia"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                                </svg>
                            </button>
                            <a v-if="driveUrl(selectedPhoto)"
                               :href="driveUrl(selectedPhoto)"
                               target="_blank"
                               rel="noopener"
                               title="Abrir no Drive"
                               class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <button @click="downloadPhoto(selectedPhoto)"
                                    title="Download"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Container da imagem -->
                    <div class="relative bg-slate-900 rounded-xl flex items-center justify-center overflow-hidden"
                         style="min-height: 420px; max-height: 640px;">
                        <img :src="route('patients.photos.view', [patient.id, selectedPhoto.id])"
                             :alt="selectedPhoto.subcategoria || selectedPhoto.filename"
                             :style="imageStyle"
                             class="rounded select-none" />
                    </div>

                    <!-- Metadados da imagem -->
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="selectedPhoto.categoria"
                              class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full">
                            {{ selectedPhoto.categoria }}
                        </span>
                        <span v-if="selectedPhoto.subcategoria"
                              class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-full font-medium">
                            {{ selectedPhoto.subcategoria }}
                        </span>
                        <span v-if="selectedPhoto.dente"
                              class="px-2 py-0.5 bg-teal-50 text-teal-700 rounded-full border border-teal-200">
                            Dente {{ selectedPhoto.dente }}
                        </span>
                        <span v-if="selectedPhoto.taken_at" class="text-slate-400">
                            {{ fmtDate(selectedPhoto.taken_at) }}
                        </span>
                    </div>
                    <div v-if="selectedPhoto.observacao"
                         class="mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-slate-700 leading-relaxed">
                        <span class="font-semibold text-amber-700 mr-1">Obs:</span>{{ selectedPhoto.observacao }}
                    </div>
                </div>
            </Transition>

            <!-- Carrossel de miniaturas -->
            <div class="px-6 py-5">
                <p class="text-[10px] uppercase tracking-wide font-semibold text-slate-400 mb-3">
                    {{ photos.length }} imagem{{ photos.length !== 1 ? 's' : '' }}
                    <template v-if="!selectedPhoto">
                        <span class="normal-case font-normal ml-1">· Clique para comparar com o odontograma</span>
                    </template>
                </p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="photo in photos"
                            :key="photo.id"
                            @click="selectPhoto(photo)"
                            class="relative rounded-xl overflow-hidden border-2 transition-all duration-150 shrink-0 group"
                            :class="selectedPhoto?.id === photo.id
                                ? 'border-teal-500 shadow-md shadow-teal-100 ring-2 ring-teal-200'
                                : 'border-slate-200 hover:border-teal-300'"
                            style="width: 76px; height: 76px;">
                        <img v-if="!brokenThumbs.has(photo.id)"
                             :src="route('patients.photos.view', [patient.id, photo.id])"
                             :alt="photo.subcategoria || photo.filename"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                             @error="brokenThumbs = new Set([...brokenThumbs, photo.id])" />
                        <div v-else
                             class="w-full h-full flex items-center justify-center bg-slate-100 text-slate-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <!-- Checkmark quando selecionada -->
                        <div v-if="selectedPhoto?.id === photo.id"
                             class="absolute inset-0 bg-teal-500/20 flex items-center justify-center">
                            <div class="w-6 h-6 rounded-full bg-teal-500 flex items-center justify-center shadow">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <!-- Label no rodapé -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-1.5 pt-3 pb-1">
                            <p class="text-[8px] text-white truncate leading-tight">
                                {{ photo.subcategoria || photo.categoria || '—' }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Histórico clínico ───────────────────────────────────────────── -->
        <div class="mt-6 grid md:grid-cols-2 gap-4">

            <!-- Últimas Evoluções -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                    <span class="w-5 h-5 bg-blue-100 text-blue-600 rounded-md flex items-center justify-center text-[10px] font-black shrink-0">E</span>
                    <h3 class="text-sm font-semibold text-slate-800">Últimas Evoluções</h3>
                </div>
                <div v-if="lastEvolutions.length" class="divide-y divide-slate-50">
                    <div v-for="ev in lastEvolutions" :key="ev.type + ev.occurred_at"
                         class="flex gap-3 px-4 py-3 text-xs hover:bg-slate-50 transition-colors">
                        <span class="text-slate-300 shrink-0 mt-0.5 text-sm">{{ evIcon(ev.type) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-800 leading-snug">{{ ev.title }}</p>
                            <p v-if="ev.detail" class="text-slate-400 truncate mt-0.5">{{ ev.detail }}</p>
                            <p v-if="ev.meta?.preview"
                               class="text-slate-400 text-[10px] leading-relaxed mt-0.5 line-clamp-2 italic">
                                {{ ev.meta.preview }}
                            </p>
                        </div>
                        <span class="text-[10px] text-slate-300 shrink-0 mt-0.5 tabular-nums whitespace-nowrap">
                            {{ fmtDate(ev.occurred_at) }}
                        </span>
                    </div>
                </div>
                <div v-else class="px-4 py-6 text-center">
                    <p class="text-xs text-slate-400">Nenhuma evolução registrada.</p>
                </div>
            </div>

            <!-- Últimos Tratamentos -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                    <span class="w-5 h-5 bg-teal-100 text-teal-600 rounded-md flex items-center justify-center text-[10px] font-black shrink-0">T</span>
                    <h3 class="text-sm font-semibold text-slate-800">Últimos Tratamentos</h3>
                </div>
                <div v-if="lastTreatments.length" class="divide-y divide-slate-50">
                    <div v-for="t in lastTreatments" :key="t.id"
                         class="flex gap-3 px-4 py-3 text-xs hover:bg-slate-50 transition-colors">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                <p class="font-medium text-slate-800 truncate">{{ t.name }}</p>
                                <span class="shrink-0 px-1.5 py-0.5 rounded-full text-[9px] font-semibold"
                                      :class="treatStatusCls(t.status)">
                                    {{ treatStatusLabel(t.status) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2 text-[10px] text-slate-400">
                                <span v-if="t.professional">{{ t.professional }}</span>
                                <span v-if="t.category" class="text-slate-300">·</span>
                                <span v-if="t.category">{{ t.category }}</span>
                                <span v-if="t.value && fmtCurrency(t.value)" class="text-emerald-600 font-medium">
                                    {{ fmtCurrency(t.value) }}
                                </span>
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-300 shrink-0 mt-0.5 tabular-nums whitespace-nowrap">
                            {{ fmtDate(t.started_at) }}
                        </span>
                    </div>
                </div>
                <div v-else class="px-4 py-6 text-center">
                    <p class="text-xs text-slate-400">Nenhum tratamento registrado.</p>
                </div>
            </div>
        </div>

        <!-- Linha do tempo clínica -->
        <div v-if="patientTimeline.length" class="mt-4 bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-sm font-semibold text-slate-800">Linha do Tempo Clínica</h3>
                <span class="text-[10px] text-slate-400 ml-auto">Últimos {{ patientTimeline.length }} eventos</span>
            </div>
            <div class="px-4 py-4">
                <div class="relative">
                    <div class="absolute left-[7px] top-3 bottom-3 w-px bg-slate-100"></div>
                    <div class="space-y-3">
                        <div v-for="ev in patientTimeline" :key="ev.type + ev.occurred_at"
                             class="relative pl-5 text-xs">
                            <div class="absolute left-0 top-1.5 w-3.5 h-3.5 rounded-full border-2 border-white shadow-sm shrink-0"
                                 :class="tlDotClass(ev.type)"></div>
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-700 leading-snug">{{ ev.title }}</p>
                                    <p v-if="ev.detail" class="text-slate-400 truncate text-[10px] mt-0.5">{{ ev.detail }}</p>
                                </div>
                                <span class="text-[10px] text-slate-300 shrink-0 mt-0.5 tabular-nums whitespace-nowrap">
                                    {{ fmtDate(ev.occurred_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>

    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <!--  MODO COMPARAÇÃO — Odontograma + Imagem, tela inteira                   -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-[0.99]"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-[0.99]">
            <div v-if="comparisonMode && selectedPhoto"
                 class="fixed inset-0 z-[70] flex flex-col bg-slate-100 overflow-hidden">

                <!-- ── Barra de controles ──────────────────────────────────── -->
                <div class="flex items-center gap-2 px-4 py-2 bg-white border-b border-slate-200 shrink-0 flex-wrap">

                    <!-- Alternar layout -->
                    <div class="flex gap-0.5 bg-slate-100 rounded-lg p-0.5 shrink-0">
                        <button @click="comparisonLayout = 'stacked'"
                                :class="comparisonLayout === 'stacked'
                                    ? 'bg-white text-slate-800 shadow-sm'
                                    : 'text-slate-400 hover:text-slate-600'"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-all">
                            <!-- ícone empilhado vertical -->
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            Empilhado
                        </button>
                        <button @click="comparisonLayout = 'split'"
                                :class="comparisonLayout === 'split'
                                    ? 'bg-white text-slate-800 shadow-sm'
                                    : 'text-slate-400 hover:text-slate-600'"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-all">
                            <!-- ícone lado a lado -->
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h4M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M9 3v18M15 3v18"/>
                            </svg>
                            Lado a lado
                        </button>
                    </div>

                    <!-- Separador -->
                    <div class="w-px h-5 bg-slate-200 shrink-0"></div>

                    <!-- Identificação: paciente + foto -->
                    <div class="flex items-center gap-2 min-w-0 shrink">
                        <span class="text-xs font-semibold text-slate-700 truncate">{{ patientFullName }}</span>
                        <span class="text-slate-300 shrink-0">·</span>
                        <span class="text-xs text-slate-500 truncate">
                            {{ selectedPhoto.subcategoria || selectedPhoto.filename }}
                        </span>
                    </div>

                    <!-- Separador -->
                    <div class="w-px h-5 bg-slate-200 shrink-0"></div>

                    <!-- Controles da imagem -->
                    <div class="flex items-center gap-1 shrink-0">
                        <button @click="zoomOut" :disabled="imageZoom <= 0.25"
                                title="Zoom -"
                                class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                            </svg>
                        </button>
                        <span class="text-[11px] font-mono text-slate-600 w-11 text-center select-none">
                            {{ Math.round(imageZoom * 100) }}%
                        </span>
                        <button @click="zoomIn" :disabled="imageZoom >= 5"
                                title="Zoom +"
                                class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </button>
                        <button @click="resetZoom"
                                title="Ajustar à tela"
                                class="h-7 px-2 text-[11px] font-medium rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                            100%
                        </button>
                        <button @click="rotate"
                                title="Rotacionar"
                                class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <a v-if="driveUrl(selectedPhoto)"
                           :href="driveUrl(selectedPhoto)" target="_blank" rel="noopener"
                           title="Abrir no Drive"
                           class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        <button @click="downloadPhoto(selectedPhoto)"
                                title="Download"
                                class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Espaço flexível -->
                    <div class="flex-1"></div>

                    <!-- Status de salvamento do odontograma -->
                    <span v-if="saveStatus === 'saved' && !isDirty"
                          class="text-[11px] text-slate-400 flex items-center gap-1 shrink-0">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Salvo
                    </span>
                    <span v-if="saveStatus === 'error'" class="text-[11px] text-red-500 shrink-0">⚠ Erro ao salvar</span>
                    <button v-if="isDirty"
                            @click="save(null)"
                            :disabled="isSaving"
                            class="inline-flex items-center gap-1.5 h-7 px-3 rounded-md text-xs font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-60 shrink-0 transition-colors">
                        <svg v-if="isSaving" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ isSaving ? 'Salvando...' : 'Salvar odontograma' }}
                    </button>

                    <!-- Separador -->
                    <div class="w-px h-5 bg-slate-200 shrink-0"></div>

                    <!-- Sair do modo comparação -->
                    <button @click="comparisonMode = false"
                            class="inline-flex items-center gap-1.5 h-7 px-3 rounded-md text-xs font-medium border border-slate-200 text-slate-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Sair do modo comparação
                    </button>
                </div>

                <!-- ── Painéis principais ───────────────────────────────────── -->
                <div class="flex-1 overflow-hidden min-h-0"
                     :class="comparisonLayout === 'split' ? 'flex flex-row' : 'flex flex-col'">

                    <!-- Painel do Odontograma -->
                    <div class="bg-white overflow-auto min-h-0 flex flex-col"
                         :class="comparisonLayout === 'split'
                             ? 'w-1/2 border-r border-slate-200'
                             : 'h-1/2 border-b border-slate-200'">
                        <!-- Mini header -->
                        <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-100 bg-slate-50/60 shrink-0">
                            <span class="text-sm font-semibold text-slate-700">🦷 Odontograma</span>
                            <div class="flex gap-1.5 ml-2 flex-wrap">
                                <span v-if="stats.completed"
                                      class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold">
                                    ✓ {{ stats.completed }}
                                </span>
                                <span v-if="stats.inProgress"
                                      class="text-[10px] px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-700 font-semibold">
                                    ● {{ stats.inProgress }}
                                </span>
                                <span v-if="stats.removed"
                                      class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">
                                    ✕ {{ stats.removed }}
                                </span>
                            </div>
                        </div>
                        <!-- Chart com scroll -->
                        <div class="flex-1 overflow-auto px-4 py-4">
                            <OdontogramChart
                                :teeth-data="teethData"
                                :tooth-statuses="toothStatuses"
                                :treatments-by-tooth="treatmentsByTooth"
                                @update:teeth-data="onUpdate"
                                @save="save"
                                @open-treatments="goToTreatments" />
                        </div>
                    </div>

                    <!-- Painel da Imagem -->
                    <div class="bg-slate-900 overflow-hidden min-h-0 flex flex-col"
                         :class="comparisonLayout === 'split' ? 'w-1/2' : 'h-1/2'">
                        <!-- Mini header dark -->
                        <div class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
                            <svg class="w-3.5 h-3.5 text-white/40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs font-medium text-white/70 truncate">
                                {{ selectedPhoto.subcategoria || selectedPhoto.categoria || 'Imagem' }}
                            </span>
                            <span v-if="selectedPhoto.dente"
                                  class="text-[10px] px-1.5 py-0.5 rounded-full bg-teal-500/30 text-teal-300 shrink-0">
                                Dente {{ selectedPhoto.dente }}
                            </span>
                        </div>

                        <!-- Área da imagem (expansível) -->
                        <div class="flex-1 overflow-auto flex items-center justify-center p-4 min-h-0">
                            <img :src="route('patients.photos.view', [patient.id, selectedPhoto.id])"
                                 :alt="selectedPhoto.subcategoria || selectedPhoto.filename"
                                 :style="imageStyle"
                                 class="rounded select-none" />
                        </div>

                        <!-- Mini carrossel (fixo no rodapé do painel) -->
                        <div v-if="photos.length > 1"
                             class="shrink-0 bg-slate-800/80 border-t border-white/10 px-4 py-2 flex items-center gap-2 overflow-x-auto">
                            <span class="text-[10px] text-white/30 shrink-0 whitespace-nowrap">
                                {{ photos.length }} imgs
                            </span>
                            <button v-for="photo in photos" :key="photo.id"
                                    @click="selectPhoto(photo)"
                                    class="relative shrink-0 rounded-lg overflow-hidden border-2 transition-all duration-150"
                                    :class="selectedPhoto?.id === photo.id
                                        ? 'border-teal-400 ring-1 ring-teal-400/50'
                                        : 'border-transparent hover:border-white/40'"
                                    style="width: 44px; height: 44px;">
                                <img :src="route('patients.photos.view', [patient.id, photo.id])"
                                     :alt="photo.subcategoria || photo.filename"
                                     class="w-full h-full object-cover" />
                                <div v-if="selectedPhoto?.id === photo.id"
                                     class="absolute inset-0 bg-teal-400/20 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <!--  MODAL — Tela cheia da imagem                                           -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="imageFullscreen && selectedPhoto"
                 class="fixed inset-0 z-50 bg-black/95 flex flex-col"
                 @click.self="imageFullscreen = false">

                <!-- Barra de controles -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/10 shrink-0 gap-3">
                    <div class="min-w-0">
                        <p class="text-white text-sm font-medium truncate">
                            {{ selectedPhoto.subcategoria || selectedPhoto.filename }}
                        </p>
                        <p class="text-white/40 text-[11px]">
                            {{ selectedPhoto.categoria }}
                            <template v-if="selectedPhoto.dente"> · Dente {{ selectedPhoto.dente }}</template>
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button @click="zoomOut" :disabled="imageZoom <= 0.25"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 disabled:opacity-40 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                            </svg>
                        </button>
                        <span class="text-xs text-white/60 font-mono w-12 text-center select-none">
                            {{ Math.round(imageZoom * 100) }}%
                        </span>
                        <button @click="zoomIn" :disabled="imageZoom >= 5"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 disabled:opacity-40 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </button>
                        <button @click="resetZoom"
                                class="h-8 px-3 text-xs text-white bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                            100%
                        </button>
                        <button @click="rotate"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <a v-if="driveUrl(selectedPhoto)"
                           :href="driveUrl(selectedPhoto)"
                           target="_blank" rel="noopener"
                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                           title="Abrir no Drive">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        <button @click="downloadPhoto(selectedPhoto)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                                title="Download">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </button>
                        <button @click="imageFullscreen = false"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-red-500/80 transition-colors ml-2"
                                title="Fechar (Esc)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Área da imagem em tela cheia (com scroll para zoom alto) -->
                <div class="flex-1 overflow-auto flex items-center justify-center p-6">
                    <img :src="route('patients.photos.view', [patient.id, selectedPhoto.id])"
                         :alt="selectedPhoto.subcategoria || selectedPhoto.filename"
                         :style="imageStyle"
                         class="rounded select-none" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
