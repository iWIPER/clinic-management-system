<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import ToothPanel         from './ToothPanel.vue'
import ToothHistoryModal  from './ToothHistoryModal.vue'
import PermanentTeeth     from './Odontogram/PermanentTeeth.vue'
import DeciduousTeeth    from './Odontogram/DeciduousTeeth.vue'
import OdontogramLegend   from './Odontogram/OdontogramLegend.vue'
import OdontogramTooltip  from './Odontogram/OdontogramTooltip.vue'
import { STATUS_VISUAL }  from './Odontogram/permanentTeethPaths.js'
import { statusFromTreatments } from './Odontogram/toothStatusPriority.js'
import { useOdontogramZoom, ODONTOGRAM_ZOOM_MIN, ODONTOGRAM_ZOOM_MAX, ODONTOGRAM_ZOOM_STEP } from '@/composables/useOdontogramZoom.js'

const props = defineProps({
    teethData:     { type: Object,  default: () => ({}) },
    // Mapa dente (FDI) → tratamentos ativos ({status, faces}[]). Fonte de
    // verdade das cores/badges — ver App\Models\PatientTreatment::groupedByTooth().
    treatmentsByTooth: { type: Object, default: () => ({}) },
    fdiTeeth:      { type: Array,   default: () => [] },
    toothStatuses: { type: Array,   default: () => [] },
    readonly:      { type: Boolean, default: false },
    // Modo miniatura: só arcada permanente, sem tabs/legenda/dica — usado na
    // Visão Geral do paciente. Reaproveita este mesmo componente (nenhuma
    // segunda implementação do odontograma).
    compact:       { type: Boolean, default: false },
})
const emit = defineEmits(['update:teethData', 'save', 'open-treatments'])

// ── State ──────────────────────────────────────────────────────────────────
const activeTab      = ref('permanent')
const selectedTooth  = ref(null)
const historyTooth   = ref(null)
const hoveredTooth   = ref(null)
const isTouchDevice  = ref(false)

// Tooltip
const ttVisible = ref(false)
const ttStyle   = ref({})
let   ttTimer   = null

onMounted(()  => { isTouchDevice.value = window.matchMedia('(hover: none) and (pointer: coarse)').matches })
onUnmounted(() => clearTimeout(ttTimer))

// ── Tooth data helpers ─────────────────────────────────────────────────────
const DEFAULTS = {
    status: 'saudavel', notes: '',
    removed: false, removed_at: null, removed_by: null, removal_reason: null,
}
const getTooth = (n) => ({ ...DEFAULTS, ...props.teethData?.[n] })
const getTreatments = (n) => props.treatmentsByTooth?.[n] ?? []

// Prioridade em em_andamento > concluido > futuro vem de statusFromTreatments
// (toothStatusPriority.js) — mesma fonte usada por PermanentTeeth.vue e
// DeciduousTeeth.vue pra pintar a coroa/raiz, senão o badge deste tooltip
// pode divergir da cor real do dente.
const visualStatus = (n) => {
    const d = getTooth(n)
    if (d.removed || d.status === 'ausente') return 'removed'
    return statusFromTreatments(getTreatments(n)) ?? 'none'
}
const vs = (n) => STATUS_VISUAL[visualStatus(n)]

const hasMeaningful = (n) => {
    const d = getTooth(n)
    return d.removed || d.status !== 'saudavel' || (getTreatments(n).length > 0) || !!d.notes?.trim()
}

// ── Hover / Tooltip ────────────────────────────────────────────────────────
const onToothHover = (n, e) => {
    hoveredTooth.value = n
    if (isTouchDevice.value || !hasMeaningful(n)) return
    clearTimeout(ttTimer)
    ttTimer = setTimeout(() => {
        const r = e.currentTarget?.getBoundingClientRect?.() ?? e.target?.getBoundingClientRect?.() ?? { right: 0, left: 0, top: 0 }
        let x = r.right + 10
        let y = r.top   - 4
        if (x + 270 > window.innerWidth)  x = r.left - 278
        if (y + 320 > window.innerHeight) y = Math.max(8, window.innerHeight - 328)
        ttStyle.value   = { top: `${y}px`, left: `${x}px` }
        ttVisible.value = true
    }, 180)
}

const onToothLeave = () => {
    hoveredTooth.value = null
    clearTimeout(ttTimer)
    ttVisible.value = false
}

// ── Click / Selection ──────────────────────────────────────────────────────
const onToothClick = (n) => {
    if (props.readonly) return
    onToothLeave()
    selectedTooth.value = selectedTooth.value === n ? null : n
}

const onPanelSave = (data) => {
    const updated = { ...props.teethData, [selectedTooth.value]: data }
    emit('update:teethData', updated)
    emit('save', updated)
    selectedTooth.value = null
}

const switchTab = (tab) => {
    activeTab.value    = tab
    selectedTooth.value = null
    onToothLeave()
}

// Zoom do desenho (persiste por navegador via localStorage — mesmo padrão do
// zoomLevel da Agenda, ver useOdontogramZoom.js). Não se aplica ao modo
// compact (miniatura da Visão Geral), que já é intencionalmente pequeno.
const odontogramZoom = useOdontogramZoom()
const zoomOut = () => { odontogramZoom.zoom = Math.max(ODONTOGRAM_ZOOM_MIN, Math.round((odontogramZoom.zoom - ODONTOGRAM_ZOOM_STEP) * 10) / 10) }
const zoomIn  = () => { odontogramZoom.zoom = Math.min(ODONTOGRAM_ZOOM_MAX, Math.round((odontogramZoom.zoom + ODONTOGRAM_ZOOM_STEP) * 10) / 10) }

// Tooltip computed values (only compute when visible)
const ttTooth = computed(() => ttVisible.value ? hoveredTooth.value : null)
const ttData  = computed(() => ttTooth.value ? getTooth(ttTooth.value) : {})
const ttVs    = computed(() => ttTooth.value ? vs(ttTooth.value) : STATUS_VISUAL.none)
</script>

<template>
<div class="select-none">

    <!-- ── Tabs + Zoom ─────────────────────────────────────────────────────── -->
    <div v-if="!compact" class="flex items-center justify-between gap-3 mb-4">
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl w-fit">
            <button type="button" @click="switchTab('permanent')"
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150"
                    :class="activeTab === 'permanent'
                        ? 'bg-white text-teal-700 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'">
                Permanentes
            </button>
            <button type="button" @click="switchTab('deciduous')"
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150"
                    :class="activeTab === 'deciduous'
                        ? 'bg-white text-teal-700 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'">
                Decíduos
            </button>
        </div>

        <!-- Zoom (persiste no navegador) -->
        <div class="flex items-center gap-0.5 text-slate-500 shrink-0">
            <button type="button" @click="zoomOut"
                    :disabled="odontogramZoom.zoom <= ODONTOGRAM_ZOOM_MIN"
                    class="w-6 h-6 flex items-center justify-center rounded hover:bg-slate-100 text-base leading-none transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    title="Reduzir zoom">−</button>
            <span class="text-[10px] tabular-nums w-8 text-center select-none">{{ Math.round(odontogramZoom.zoom * 100) }}%</span>
            <button type="button" @click="zoomIn"
                    :disabled="odontogramZoom.zoom >= ODONTOGRAM_ZOOM_MAX"
                    class="w-6 h-6 flex items-center justify-center rounded hover:bg-slate-100 text-base leading-none transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    title="Aumentar zoom">+</button>
        </div>
    </div>

    <!-- ── Legend ──────────────────────────────────────────────────────────── -->
    <OdontogramLegend v-if="!compact" class="mb-4" />

    <!-- ── Chart + Side panel ──────────────────────────────────────────────── -->
    <div class="flex flex-col md:flex-row gap-4 items-start">

        <!-- SVG area -->
        <div class="flex-1 min-w-0" :class="compact ? '' : 'overflow-x-auto'">
            <!-- Zoom: escala a LARGURA deste bloco (não a do dente em si — o
                 SVG já é width:100%/height:auto, então redimensionar este
                 wrapper reflui o desenho inteiro proporcionalmente, sem
                 recorte). CSS `zoom` foi tentado primeiro e descartado: como
                 toda a cadeia de largura aqui é 100% até o flex item acima
                 (flex-1), o zoom não se propaga pro tamanho renderizado
                 (quirk conhecido do Chromium/Firefox com zoom + percentuais
                 encadeados) — width em % é reflow de verdade, sem esse
                 problema e sem sobrepor o conteúdo abaixo (ver useOdontogramZoom.js).
                 margin:auto (não flex+justify-center) pra centralizar: com
                 flexbox, um item mais largo que o pai fica com metade do
                 excedente à esquerda do scrollport, e esse lado NUNCA fica
                 alcançável via scroll (scrollLeft não vai negativo) — parte
                 do desenho ficaria inacessível ao aumentar o zoom. margin:auto
                 num bloco comum centraliza quando cabe (encolher) e degrada
                 pra alinhado à esquerda + scroll de verdade quando não cabe
                 (aumentar) — nunca esconde nada. -->
            <div :style="compact ? '' : `width: ${odontogramZoom.zoom * 100}%; margin: 0 auto`">
            <template v-if="activeTab === 'permanent'">
                <PermanentTeeth
                    :teeth-data="teethData"
                    :treatments-by-tooth="treatmentsByTooth"
                    :selected-tooth="selectedTooth"
                    :hovered-tooth="hoveredTooth"
                    :readonly="readonly"
                    @tooth:click="onToothClick"
                    @tooth:hover="onToothHover"
                    @tooth:leave="onToothLeave"
                    :class="compact ? '' : 'min-w-[600px]'" />
            </template>

            <template v-else>
                <DeciduousTeeth
                    :teeth-data="teethData"
                    :treatments-by-tooth="treatmentsByTooth"
                    :selected-tooth="selectedTooth"
                    :hovered-tooth="hoveredTooth"
                    :readonly="readonly"
                    @tooth:click="onToothClick"
                    @tooth:hover="onToothHover"
                    @tooth:leave="onToothLeave"
                    :class="compact ? '' : 'min-w-[600px]'" />
            </template>
            </div>
        </div>

        <!-- Side panel (tooth editor) -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4">
            <ToothPanel v-if="selectedTooth && !readonly"
                        :tooth="selectedTooth"
                        :tooth-data="getTooth(selectedTooth)"
                        :tooth-statuses="toothStatuses"
                        :treatments="getTreatments(selectedTooth)"
                        @close="selectedTooth = null"
                        @save="onPanelSave"
                        @open-history="historyTooth = selectedTooth"
                        @open-treatments="emit('open-treatments', selectedTooth)" />
        </Transition>
    </div>

    <!-- Usage hint -->
    <p v-if="!readonly && !compact" class="text-[10px] text-slate-400 text-center mt-3">
        {{ isTouchDevice
            ? 'Toque em um dente para editar'
            : 'Passe o mouse para consultar · Clique para editar' }}
    </p>

    <!-- ── Tooltip ─────────────────────────────────────────────────────────── -->
    <OdontogramTooltip
        :visible="ttVisible && !!ttTooth"
        :tooth="ttTooth"
        :tooth-data="ttData"
        :treatments="getTreatments(ttTooth)"
        :style="ttStyle"
        :vs-label="ttVs.label"
        :vs-badge="ttVs.badge"
        :vs-badge-color="ttVs.badgeColor ?? '#94a3b8'"
        :tooth-statuses="toothStatuses" />

    <!-- ── History modal ───────────────────────────────────────────────────── -->
    <ToothHistoryModal v-if="historyTooth"
                       :tooth="historyTooth"
                       :tooth-data="getTooth(historyTooth)"
                       :treatments="getTreatments(historyTooth)"
                       @close="historyTooth = null" />
</div>
</template>
