<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { STATUS_CONFIG } from '@/composables/useAppointmentStatus'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    appointments: Object, // { data, pagination: { current_page, last_page, total, per_page } }
    filters: Object,
    perPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
    patients: Array,
    professionals: Array,
    chairs: Array,
    statuses: Array,
})

const filters = ref({
    patient_id: props.filters?.patient_id || '',
    professional_id: props.filters?.professional_id || '',
    chair_id: props.filters?.chair_id || '',
    status: props.filters?.status || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
})
const perPage = ref(props.filters?.per_page || 10)

// Mesmo padrão de Patients/Index.vue: `page` só entra em `extra` quando
// veio de goToPage/onPerPageChange — omitido, o backend usa página 1
// (paginate() sem query param `page`), o que já cobre "toda troca de
// filtro volta pra primeira página" sem precisar zerar nada à mão aqui.
function applyFilters(extra = {}) {
    router.get(route('clinical-records.index'), {
        ...filters.value,
        per_page: perPage.value,
        ...extra,
    }, { preserveState: true, replace: true, only: ['appointments', 'filters'] })
}

function clearFilters() {
    filters.value = { patient_id: '', professional_id: '', chair_id: '', status: '', from: '', to: '' }
    applyFilters()
}

function goToPage(page) {
    applyFilters({ page })
}

function onPerPageChange(newPerPage) {
    perPage.value = newPerPage
    // Tamanho de página novo pode não ter mais a página atual (ex: pág. 5
    // de 10-em-10 não existe mais em 100-em-100) — mesma regra de Patients.
    applyFilters({ page: 1 })
}

// Só CSV foi pedido (Patients tem Excel+CSV via dropdown; aqui é um único
// formato, então um link direto basta — sem inventar um menu de um item
// só). Nunca inclui per_page/page: exportação é sobre TODOS os registros
// que os filtros atuais retornam, não sobre a página visível.
const exportUrl = () => {
    const params = new URLSearchParams()
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) params.set(key, value)
    })
    return `${route('clinical-records.export')}?${params.toString()}`
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'

// appointment.status é o valor cru do backend (scheduled/confirmed/
// in_attendance/completed/cancelled/no_show) — STATUS_CONFIG é a mesma
// fonte de rótulo/cor já usada na Agenda, só que indexada pelas chaves
// "resolvidas" (em_atendimento em vez de in_attendance); esse mapa só
// traduz o nome, não inventa um status novo.
const STATUS_KEY = {
    scheduled: 'scheduled', confirmed: 'confirmed', in_attendance: 'em_atendimento',
    completed: 'completed', cancelled: 'cancelled', no_show: 'no_show',
}
const statusLabel = (status) => STATUS_CONFIG[STATUS_KEY[status]]?.label || status
const statusBadgeClass = (status) => STATUS_CONFIG[STATUS_KEY[status]]?.badge || 'bg-slate-100 text-slate-600'
</script>

<template>
<AppLayout>
  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Atendimentos</h1>
      <p class="text-sm text-slate-500 mt-1">Histórico das consultas/agendamentos realizados</p>
    </div>
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-2xl border p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <select v-model="filters.patient_id" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos os pacientes</option>
        <option v-for="p in patients" :key="p.id" :value="p.id">
          {{ p.nome }} {{ p.sobrenome }}
        </option>
      </select>

      <select v-model="filters.professional_id" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos os profissionais</option>
        <option v-for="prof in professionals" :key="prof.id" :value="prof.id">
          {{ prof.name }}
        </option>
      </select>

      <select v-model="filters.chair_id" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todas as cadeiras</option>
        <option v-for="c in chairs" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>

      <select v-model="filters.status" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos os status</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>

      <input v-model="filters.from" type="date" class="border rounded-lg px-3 py-2 text-sm" />
      <input v-model="filters.to" type="date" class="border rounded-lg px-3 py-2 text-sm" />
    </div>

    <div class="flex gap-2 mt-3">
      <button @click="applyFilters()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Filtrar
      </button>
      <button @click="clearFilters" class="text-slate-500 hover:text-slate-700 px-4 py-2 text-sm">
        Limpar
      </button>
    </div>
  </div>

  <div class="bg-white rounded-2xl border overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="p-4 text-left font-medium text-slate-600">Data</th>
          <th class="p-4 text-left font-medium text-slate-600">Paciente</th>
          <th class="p-4 text-left font-medium text-slate-600">Profissional</th>
          <th class="p-4 text-left font-medium text-slate-600">Cadeira</th>
          <th class="p-4 text-left font-medium text-slate-600">Status</th>
          <th class="p-4 text-right font-medium text-slate-600">Ação</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <tr v-for="appt in appointments.data" :key="appt.id" class="hover:bg-slate-50/50">
          <td class="p-4 text-slate-700">{{ fmtDate(appt.start) }}</td>
          <td class="p-4 font-medium">
            <Link :href="route('patients.show', appt.patient.id)" class="hover:underline">
              {{ appt.patient.nome }} {{ appt.patient.sobrenome }}
            </Link>
          </td>
          <td class="p-4 text-slate-600">{{ appt.professional?.name || '—' }}</td>
          <td class="p-4 text-slate-600">
            <span v-if="appt.chair" class="inline-flex items-center gap-1.5">
              <span class="h-2 w-2 rounded-full shrink-0" :style="{ backgroundColor: appt.chair.color }" />
              {{ appt.chair.name }}
            </span>
            <span v-else>—</span>
          </td>
          <td class="p-4">
            <span class="text-xs font-medium rounded-full px-2.5 py-1" :class="statusBadgeClass(appt.status)">
              {{ statusLabel(appt.status) }}
            </span>
          </td>
          <td class="p-4 text-right">
            <Link :href="route('clinical-records.show', appt.id)"
                  class="text-emerald-600 hover:text-emerald-800 font-medium">
              Ver detalhes →
            </Link>
          </td>
        </tr>

        <tr v-if="!appointments.data.length">
          <td colspan="6" class="p-8 text-center text-slate-400">Nenhum atendimento encontrado.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Rodapé: exportar + paginação + itens por página — mesmo layout de
       Patients/Index.vue (grid 3 colunas no desktop, empilhado no mobile). -->
  <div v-if="appointments.data.length > 0"
       class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
    <div class="justify-self-center sm:justify-self-start">
      <a :href="exportUrl()"
         class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors">
        <ArrowDownTrayIcon class="w-4 h-4" />
        Exportar CSV
      </a>
    </div>

    <div class="justify-self-center">
      <Pagination :pagination="appointments.pagination" :bordered="false" @change="goToPage" />
    </div>

    <div class="justify-self-center sm:justify-self-end">
      <label class="inline-flex items-center gap-1.5 text-xs text-slate-500">
        Itens por página:
        <select :value="perPage" @change="onPerPageChange(Number($event.target.value))"
                class="border rounded-lg px-2 py-1 text-xs text-slate-600">
          <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
        </select>
      </label>
    </div>
  </div>
</AppLayout>
</template>
