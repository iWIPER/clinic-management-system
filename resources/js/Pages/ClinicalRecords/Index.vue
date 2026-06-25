<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    records: Object,
    filters: Object,
    patients: Array,
    professionals: Array,
    statuses: Array,
})

const filters = ref({
    patient_id: props.filters?.patient_id || '',
    professional_id: props.filters?.professional_id || '',
    procedure: props.filters?.procedure || '',
    status: props.filters?.status || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
})

const applyFilters = () => {
    router.get(route('clinical-records.index'), filters.value, { preserveState: true })
}

const clearFilters = () => {
    filters.value = { patient_id: '', professional_id: '', procedure: '', status: '', from: '', to: '' }
    applyFilters()
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtCurrency = (val) => Number(val || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const statusLabel = (status) => {
    if (status === 'concluido') return 'Concluído'
    if (status === 'cancelado') return 'Cancelado'
    return status
}

const statusClass = (status) => {
    if (status === 'concluido') return 'bg-emerald-100 text-emerald-700'
    if (status === 'cancelado') return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-600'
}
</script>

<template>
<AppLayout>
  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Atendimentos</h1>
      <p class="text-sm text-slate-500 mt-1">Histórico permanente de procedimentos realizados</p>
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

      <input v-model="filters.procedure"
             placeholder="Procedimento..."
             class="border rounded-lg px-3 py-2 text-sm" />

      <select v-model="filters.status" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos os status</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>

      <input v-model="filters.from" type="date" class="border rounded-lg px-3 py-2 text-sm" />
      <input v-model="filters.to" type="date" class="border rounded-lg px-3 py-2 text-sm" />
    </div>

    <div class="flex gap-2 mt-3">
      <button @click="applyFilters" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
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
          <th class="p-4 text-left font-medium text-slate-600">Procedimento</th>
          <th class="p-4 text-left font-medium text-slate-600">Valor</th>
          <th class="p-4 text-left font-medium text-slate-600">Status</th>
          <th class="p-4 text-right font-medium text-slate-600">Ação</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <tr v-for="record in records.data" :key="record.id" class="hover:bg-slate-50/50">
          <td class="p-4 text-slate-700">{{ fmtDate(record.finished_at) }}</td>
          <td class="p-4 font-medium">
            <Link :href="route('patients.show', record.patient.id)" class="hover:underline">
              {{ record.patient.nome }} {{ record.patient.sobrenome }}
            </Link>
          </td>
          <td class="p-4 text-slate-600">{{ record.professional?.name || '—' }}</td>
          <td class="p-4 text-slate-700">{{ record.procedure_name }}</td>
          <td class="p-4 text-slate-700">{{ fmtCurrency(record.price) }}</td>
          <td class="p-4">
            <span class="text-xs font-medium rounded-full px-2.5 py-1" :class="statusClass(record.status)">
              {{ statusLabel(record.status) }}
            </span>
          </td>
          <td class="p-4 text-right">
            <Link :href="route('clinical-records.show', record.id)"
                  class="text-emerald-600 hover:text-emerald-800 font-medium">
              Ver detalhes →
            </Link>
          </td>
        </tr>

        <tr v-if="!records.data.length">
          <td colspan="7" class="p-8 text-center text-slate-400">Nenhum atendimento encontrado.</td>
        </tr>
      </tbody>
    </table>
  </div>
</AppLayout>
</template>