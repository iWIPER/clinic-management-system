<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import PatientOverviewTab from './PatientOverviewTab.vue'
import PatientAnamnesesTab from './PatientAnamnesesTab.vue'
import PatientDocumentsTab from './PatientDocumentsTab.vue'
import PatientTreatmentsTab from './PatientTreatmentsTab.vue'
import PatientNotesTab from './PatientNotesTab.vue'

const emit = defineEmits(['tab-change'])

const props = defineProps({
    patient:                   Object,
    hub:                       Object,
    anamnesisHub:              Object,
    documentHub:                { type: Object, default: () => ({ documents: [], pagination: null, templates: [] }) },
    patientNotes:              Array,
    availableMarkers:          { type: Array, default: () => [] },
    activeTab:                 { type: String, default: 'overview' },
    fmtDate:                   Function,
    hasAddress:                Boolean,
    streetLine:                String,
    cityStateLine:             String,
    notesPagination:           { type: Object, default: () => null },
    odontogram:                { type: Object, default: () => null },
    toothStatuses:             { type: Array,  default: () => [] },
    treatmentsByTooth:         { type: Object, default: () => ({}) },
    patientTreatments:         { type: Object, default: () => ({ data: [], pagination: null }) },
    catalogTreatments:         { type: Array,  default: () => [] },
    convenios:                 { type: Array,  default: () => [] },
    eligibleProfessionals:     { type: Array,  default: () => [] },
    treatmentStatuses:         { type: Array,  default: () => [] },
    patientFullName:           String,
    patientAge:                { type: Number, default: null },
})

const tab = ref(props.activeTab)

watch(() => props.activeTab, (v) => { tab.value = v })

watch(tab, (v) => { emit('tab-change', v) }, { immediate: true })

const tabs = [
    { id: 'overview',    label: 'Visão Geral' },
    { id: 'anamneses',   label: 'Anamneses' },
    { id: 'documents',   label: 'Documentos' },
    { id: 'treatments',  label: 'Tratamentos' },
    { id: 'notes',       label: 'Observações' },
]

// ─── Sticky tab bar (Intersection Observer, sem scroll listeners) ────────────
const stickySentinel = ref(null)
const isStuck         = ref(false)
let stickyObserver = null

onMounted(() => {
    stickyObserver = new IntersectionObserver(
        ([entry]) => { isStuck.value = !entry.isIntersecting },
        { threshold: 0 }
    )
    if (stickySentinel.value) stickyObserver.observe(stickySentinel.value)
})

onBeforeUnmount(() => {
    stickyObserver?.disconnect()
})
</script>

<template>
    <div>
        <span ref="stickySentinel" class="block h-px" aria-hidden="true"></span>

        <div class="sticky top-0 z-20 -mx-6 px-6 bg-white transition-shadow duration-200"
             :class="isStuck ? 'shadow-sm' : ''">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1">
                <div v-if="isStuck" class="flex items-center justify-between gap-4 pt-3">
                    <p class="text-sm font-semibold text-slate-800 truncate">
                        {{ patientFullName }}<span v-if="patientAge !== null" class="text-slate-400 font-normal"> · {{ patientAge }} anos</span>
                    </p>
                    <div class="hidden sm:flex items-center gap-2 flex-shrink-0">
                        <Link :href="route('patients.odontogram', patient.id)"
                              class="inline-flex items-center gap-2 px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                            🦷 Odontograma
                        </Link>
                        <Link :href="route('patients.edit', patient.id)" :cache-for="0"
                              class="px-3 py-1.5 border rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                            Editar
                        </Link>
                    </div>
                </div>
            </Transition>

            <nav class="flex flex-wrap gap-1 border-b border-slate-200 mb-6">
                <button v-for="t in tabs" :key="t.id"
                        @click="tab = t.id"
                        class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px whitespace-nowrap"
                        :class="tab === t.id
                            ? 'border-teal-600 text-teal-700'
                            : 'border-transparent text-slate-500 hover:text-slate-700'">
                    {{ t.label }}
                </button>
            </nav>
        </div>

        <PatientOverviewTab v-if="tab === 'overview'"
            :patient="patient"
            :hub="hub"
            :fmt-date="fmtDate"
            :has-address="hasAddress"
            :street-line="streetLine"
            :city-state-line="cityStateLine"
            :odontogram="odontogram"
            :tooth-statuses="toothStatuses"
            :treatments-by-tooth="treatmentsByTooth" />

        <PatientAnamnesesTab v-else-if="tab === 'anamneses'"
            :patient="patient"
            :anamnesis-hub="anamnesisHub" />

        <PatientDocumentsTab v-else-if="tab === 'documents'"
            :patient="patient"
            :document-hub="documentHub" />

        <PatientTreatmentsTab v-else-if="tab === 'treatments'"
            :patient="patient"
            :odontogram="odontogram"
            :tooth-statuses="toothStatuses"
            :treatments-by-tooth="treatmentsByTooth"
            :patient-treatments="patientTreatments"
            :catalog-treatments="catalogTreatments"
            :convenios="convenios"
            :professionals="eligibleProfessionals"
            :treatment-statuses="treatmentStatuses" />

        <PatientNotesTab v-else-if="tab === 'notes'"
            :patient="patient"
            :notes="patientNotes"
            :available-markers="availableMarkers"
            :notes-pagination="notesPagination" />
    </div>
</template>
