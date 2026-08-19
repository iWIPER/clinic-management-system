<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/Navigation/PageHeader.vue'
import SettingsTabs from '@/Components/ClinicSettings/SettingsTabs.vue'
import ChairFormModal from '@/Components/Agenda/ChairFormModal.vue'

const props = defineProps({
    chairs: { type: Array, default: () => [] },
    maxChairs: { type: Number, default: 6 },
})

const list = ref(props.chairs)
const showModal = ref(false)
const editingChair = ref(null)

// A primeira cadeira (ordenada por id, ver ChairController::index()) é a
// mesma que AppointmentController::resolveChairFilter() usa como default
// da Agenda — não é uma segunda lógica de "principal", só reflete aqui a
// que já existe.
const defaultChairId = computed(() => list.value[0]?.id ?? null)
const atLimit = computed(() => list.value.length >= props.maxChairs)

function openCreate() {
    if (atLimit.value) return
    editingChair.value = null
    showModal.value = true
}

function openEdit(chair) {
    editingChair.value = chair
    showModal.value = true
}

function onSaved(chair) {
    const i = list.value.findIndex((c) => c.id === chair.id)
    if (i === -1) list.value = [...list.value, { ...chair, appointments_count: 0 }]
    else list.value = list.value.map((c, idx) => (idx === i ? { ...c, name: chair.name, color: chair.color } : c))
    showModal.value = false
}

function onDeleted(id) {
    list.value = list.value.filter((c) => c.id !== id)
    showModal.value = false
}
</script>

<template>
<AppLayout>
    <template #pageHeader>
        <PageHeader title="Configurações da Clínica" description="Gerencie os dados, recursos e áreas da sua clínica." />
    </template>

    <SettingsTabs active="chairs" />

    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Cadeiras</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl">
                Gerencie as cadeiras utilizadas pela sua clínica na Agenda. Cada agendamento pode ter
                um profissional e uma cadeira, de forma independente.
            </p>
        </div>

        <button type="button" @click="openCreate" :disabled="atLimit"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-medium transition-colors"
                :class="atLimit
                    ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Adicionar cadeira
        </button>
    </div>

    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div v-for="chair in list" :key="chair.id"
             class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50/60 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: chair.color }" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-800 truncate">{{ chair.name }}</span>
                        <span v-if="chair.id === defaultChairId"
                              class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                            Principal
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ chair.appointments_count }} agendamento{{ chair.appointments_count === 1 ? '' : 's' }}
                    </p>
                </div>
            </div>
            <button type="button" @click="openEdit(chair)"
                    class="shrink-0 text-xs font-medium text-slate-500 hover:text-emerald-700 transition-colors">
                Editar
            </button>
        </div>
        <p v-if="!list.length" class="px-5 py-10 text-center text-sm text-slate-400">
            Nenhuma cadeira cadastrada ainda.
        </p>
    </div>

    <p v-if="atLimit" class="max-w-3xl mt-3 text-xs text-slate-400">
        Limite de {{ maxChairs }} cadeiras atingido. Exclua uma cadeira existente pra poder adicionar outra.
    </p>

    <ChairFormModal :show="showModal" :chair="editingChair"
                     @close="showModal = false" @saved="onSaved" @deleted="onDeleted" />
</AppLayout>
</template>
