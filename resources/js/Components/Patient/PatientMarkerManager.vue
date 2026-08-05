<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import PatientMarkerAdminModal from '@/Components/Patient/PatientMarkerAdminModal.vue'

const props = defineProps({
    patient: Object,
    markers: { type: Array, default: () => [] },
    availableMarkers: { type: Array, default: () => [] },
})

// Falecido/Inativo não são marcadores — são o próprio Patient.status exibido
// como badge, para não duplicar um dado que já existe.
const statusBadge = computed(() => {
    if (props.patient.status === 'falecido') return { label: 'Falecido', color: '#475569' }
    if (props.patient.status === 'inativo') return { label: 'Inativo', color: '#64748b' }
    return null
})

const isSelected = (markerId) => props.markers.some((m) => m.id === markerId)

const toggleMarker = (markerId) => {
    const current = props.markers.map((m) => m.id)
    const next = isSelected(markerId) ? current.filter((id) => id !== markerId) : [...current, markerId]

    router.put(route('patients.markers.sync', props.patient.id), { marker_ids: next }, {
        preserveScroll: true,
        preserveState: true,
        only: ['patientMarkers'],
    })
}

// "Categorizar" é uso clínico (atribuir/remover no paciente). Criar,
// editar e excluir marcador é administração — fica só no modal.
const showAdminModal = ref(false)
</script>

<template>
    <span v-if="statusBadge"
          class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
          :style="{ borderColor: statusBadge.color, color: statusBadge.color, backgroundColor: statusBadge.color + '10' }">
        {{ statusBadge.label }}
    </span>

    <span v-for="marker in markers" :key="marker.id"
          class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
          :style="{ borderColor: marker.color, color: marker.color, backgroundColor: marker.color + '10' }">
        🏷 {{ marker.name }}
    </span>

    <NavbarDropdown align="left" width="w-72">
        <template #trigger>
            <button type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-500 hover:border-slate-400 hover:text-slate-700">
                🏷 Categorizar
            </button>
        </template>
        <template #default="{ close }">
            <div class="p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-700">Categorizar</span>
                    <button type="button" title="Gerenciar Marcadores"
                            @click="close(); showAdminModal = true"
                            class="inline-flex items-center justify-center w-6 h-6 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        ⚙
                    </button>
                </div>
                <div v-if="availableMarkers.length" class="flex flex-wrap gap-1.5">
                    <button v-for="m in availableMarkers" :key="m.id" type="button"
                            @click="toggleMarker(m.id)"
                            class="rounded-full px-2.5 py-1 text-xs border transition-colors"
                            :class="isSelected(m.id) ? 'text-white' : 'bg-white'"
                            :style="isSelected(m.id)
                                ? { backgroundColor: m.color, borderColor: m.color }
                                : { borderColor: m.color, color: m.color }">
                        {{ m.name }}
                    </button>
                </div>
                <p v-else class="text-xs text-slate-400">Nenhum marcador cadastrado ainda.</p>
            </div>
        </template>
    </NavbarDropdown>

    <PatientMarkerAdminModal
        :show="showAdminModal"
        :markers="availableMarkers"
        @close="showAdminModal = false"
    />
</template>
