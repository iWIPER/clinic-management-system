<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { STATUS_CONFIG } from '@/composables/useAppointmentStatus'

const props = defineProps({
    appointment: Object,
})

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtTime = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '—'

const STATUS_KEY = {
    scheduled: 'scheduled', confirmed: 'confirmed', in_attendance: 'em_atendimento',
    completed: 'completed', cancelled: 'cancelled', no_show: 'no_show',
}
const statusLabel = computed(() => STATUS_CONFIG[STATUS_KEY[props.appointment.status]]?.label || props.appointment.status)
const statusBadgeClass = computed(() => STATUS_CONFIG[STATUS_KEY[props.appointment.status]]?.badge || 'bg-slate-100 text-slate-600')

const durationMinutes = computed(() => {
    if (!props.appointment.start || !props.appointment.end) return null
    return Math.round((new Date(props.appointment.end) - new Date(props.appointment.start)) / 60000)
})

const RETURN_STATUS_LABELS = { pending: 'Pendente', scheduled: 'Agendado', dismissed: 'Dispensado' }
</script>

<template>
<AppLayout>
  <div class="mb-6">
    <Link :href="route('clinical-records.index')" class="text-sm text-slate-500 hover:text-slate-700">
      ← Voltar aos atendimentos
    </Link>
  </div>

  <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Detalhes do Atendimento</h1>
      <p class="text-sm text-slate-500 mt-1">Agendamento #{{ appointment.id }}</p>
    </div>
    <span class="text-xs font-medium rounded-full px-3 py-1.5" :class="statusBadgeClass">
      {{ statusLabel }}
    </span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-4">Dados do atendimento</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Paciente</dt>
            <dd class="font-medium">
              <Link :href="route('patients.show', appointment.patient.id)" class="text-emerald-700 hover:underline">
                {{ appointment.patient.nome }} {{ appointment.patient.sobrenome }}
              </Link>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Profissional</dt>
            <dd class="font-medium">{{ appointment.professional?.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Cadeira</dt>
            <dd class="font-medium">
              <span v-if="appointment.chair" class="inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full shrink-0" :style="{ backgroundColor: appointment.chair.color }" />
                {{ appointment.chair.name }}
              </span>
              <span v-else>—</span>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Data</dt>
            <dd>{{ fmtDate(appointment.start) }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Horário</dt>
            <dd>{{ fmtTime(appointment.start) }} – {{ fmtTime(appointment.end) }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Duração</dt>
            <dd>{{ durationMinutes ? durationMinutes + ' min' : '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Confirmação/lembrete</dt>
            <dd>{{ appointment.confirmation_requested ? 'Solicitada' : 'Não solicitada' }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="appointment.tags?.length" class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-3">Etiquetas</h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="tag in appointment.tags" :key="tag.id"
                class="text-xs font-medium rounded-full px-2.5 py-1"
                :style="{ backgroundColor: tag.color + '20', color: tag.color }">
            {{ tag.name }}
          </span>
        </div>
      </div>

      <div v-if="appointment.notes" class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-3">Observação</h2>
        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ appointment.notes }}</p>
      </div>

      <div v-if="appointment.appointment_return" class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-4">Retorno</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Data</dt>
            <dd>{{ fmtDate(appointment.appointment_return.due_date) }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Situação</dt>
            <dd>{{ RETURN_STATUS_LABELS[appointment.appointment_return.status] || appointment.appointment_return.status }}</dd>
          </div>
          <div v-if="appointment.appointment_return.reason" class="sm:col-span-3">
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Motivo</dt>
            <dd>{{ appointment.appointment_return.reason }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</AppLayout>
</template>
