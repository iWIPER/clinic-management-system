<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { resolveConsultationStatus } from '@/composables/useAppointmentStatus'

const props = defineProps({
    consultations: Object,
    filters: Object,
})

const filters = ref({
    status: props.filters?.status || '',
    search: props.filters?.search || '',
})

const applyFilters = () => {
    router.get(route('consultations.index'), filters.value, { preserveState: true })
}

// Label dinâmico do botão de ação
const actionLabel = (status) => {
    if (status === 'aguardando')     return 'Iniciar atendimento'
    if (status === 'em_atendimento') return 'Em atendimento →'
    if (status === 'finalizado')     return 'Ver consulta'
    return 'Ver'
}

const actionClass = (status) => {
    if (status === 'aguardando')     return 'text-blue-600 hover:text-blue-800 font-medium'
    if (status === 'em_atendimento') return 'text-violet-600 hover:text-violet-800 font-semibold'
    return 'text-slate-500 hover:text-slate-700'
}

// ── Alerta de espera (15 min) ─────────────────────────────────────────────
const nowRef = ref(new Date())
let _clockTimer = null

const waitMinutes = (checkInAt) => {
    if (!checkInAt) return 0
    return Math.floor((nowRef.value - new Date(checkInAt)) / 60000)
}

const isLateWaiting = (cons) =>
    cons.status === 'aguardando' && waitMinutes(cons.check_in_at) >= 15

// ── Polling ────────────────────────────────────────────────────────────────
let _pollTimer = null

onMounted(() => {
    _clockTimer = setInterval(() => { nowRef.value = new Date() }, 60000)
    _pollTimer = setInterval(() => {
        router.reload({ only: ['consultations'], preserveState: true, preserveScroll: true })
    }, 30000)
})
onUnmounted(() => {
    clearInterval(_clockTimer)
    clearInterval(_pollTimer)
})
</script>

<template>
<AppLayout>
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Consultas / Atendimentos</h1>
  </div>

  <!-- Filtros -->
  <div class="flex gap-3 mb-4">
    <input v-model="filters.search"
           @keyup.enter="applyFilters"
           placeholder="Buscar paciente..."
           class="border rounded-lg px-4 py-2 text-sm flex-1" />
    <select v-model="filters.status" @change="applyFilters" class="border rounded-lg px-4 py-2 text-sm">
      <option value="">Todas</option>
      <option value="aguardando">Aguardando</option>
      <option value="em_atendimento">Em Atendimento</option>
      <option value="finalizado">Finalizadas</option>
    </select>
    <button @click="applyFilters" class="bg-slate-800 text-white px-4 rounded-lg text-sm">Filtrar</button>
  </div>

  <div class="bg-white rounded-2xl border overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="p-4 text-left font-medium text-slate-600">Check-in</th>
          <th class="p-4 text-left font-medium text-slate-600">Paciente</th>
          <th class="p-4 text-left font-medium text-slate-600">Profissional</th>
          <th class="p-4 text-left font-medium text-slate-600">Status</th>
          <th class="p-4 text-right font-medium text-slate-600">Ação</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <tr v-for="cons in consultations.data" :key="cons.id"
            :class="isLateWaiting(cons) ? 'bg-red-50/60' : ''">

          <!-- Check-in horário + alerta de demora -->
          <td class="p-4">
            <div class="text-slate-700">
              {{ cons.check_in_at
                  ? new Date(cons.check_in_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                  : '—' }}
            </div>
            <div v-if="isLateWaiting(cons)"
                 class="flex items-center gap-1 mt-0.5 text-[11px] font-semibold text-red-600">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
              {{ waitMinutes(cons.check_in_at) }} min esperando
            </div>
          </td>

          <!-- Paciente -->
          <td class="p-4 font-medium">
            <Link :href="route('patients.show', cons.patient.id)" class="hover:underline">
              {{ cons.patient.nome }} {{ cons.patient.sobrenome }}
            </Link>
          </td>

          <!-- Profissional -->
          <td class="p-4 text-slate-600">{{ cons.professional?.name || '—' }}</td>

          <!-- Status -->
          <td class="p-4">
            <StatusIndicator
              :status="resolveConsultationStatus(cons)"
              show-label />
          </td>

          <!-- Botão de ação dinâmico -->
          <td class="p-4 text-right">
            <Link :href="route('consultations.show', cons.id)"
                  :class="actionClass(cons.status)">
              {{ actionLabel(cons.status) }}
            </Link>
          </td>
        </tr>

        <tr v-if="!consultations.data.length">
          <td colspan="5" class="p-8 text-center text-slate-400">Nenhuma consulta encontrada.</td>
        </tr>
      </tbody>
    </table>
  </div>
</AppLayout>
</template>
