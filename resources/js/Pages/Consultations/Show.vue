<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { resolveConsultationStatus } from '@/composables/useAppointmentStatus'

const props = defineProps({ consultation: Object, treatments: Array })

const notes        = ref(props.consultation.notes || '')
const executionForm = ref({ treatment_id: '', notes: '' })

// ── Polling: atualiza props a cada 30s ───────────────────────────────────
let _pollTimer = null

onMounted(() => {
    _pollTimer = setInterval(() => {
        router.reload({ only: ['consultation'], preserveState: true, preserveScroll: true })
    }, 30000)
})
onUnmounted(() => {
    clearInterval(_pollTimer)
})

// ── Ações ─────────────────────────────────────────────────────────────────
const updateNotes = () =>
    router.put(route('consultations.update', props.consultation.id), { notes: notes.value },
        { preserveScroll: true })

const doStart = () =>
    router.post(route('consultations.start', props.consultation.id), {},
        { preserveScroll: true })

const doFinish = () =>
    router.post(route('consultations.finish', props.consultation.id), { notes: notes.value })

const addExecution = () =>
    router.post(route('consultations.add-execution', props.consultation.id), executionForm.value, {
        preserveScroll: true,
        onSuccess: () => { executionForm.value = { treatment_id: '', notes: '' } },
    })

// ── Configuração de status ────────────────────────────────────────────────
const FLOW = {
    aguardando:     { step: 1, label: 'Aguardando',     color: 'bg-amber-500',  text: 'text-amber-700',  badge: 'bg-amber-100 text-amber-700' },
    em_atendimento: { step: 2, label: 'Em atendimento', color: 'bg-blue-500',   text: 'text-blue-700',   badge: 'bg-blue-100 text-blue-700' },
    finalizado:     { step: 3, label: 'Finalizado',      color: 'bg-green-500',  text: 'text-green-700',  badge: 'bg-green-100 text-green-700' },
    cancelado:      { step: 0, label: 'Cancelado',       color: 'bg-red-400',    text: 'text-red-700',    badge: 'bg-red-100 text-red-700' },
}
const currentFlow = computed(() => FLOW[props.consultation.status] ?? FLOW.aguardando)
</script>

<template>
<AppLayout>

  <!-- ── Header ─────────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
      <Link :href="route('consultations.index')" class="text-sm text-slate-500 hover:text-slate-700">
        ← Voltar
      </Link>
      <h1 class="text-2xl font-semibold mt-1">
        {{ consultation.patient.nome }} {{ consultation.patient.sobrenome }}
      </h1>
      <Link :href="route('patients.prontuario', consultation.patient.id)"
            class="text-xs text-teal-600 hover:text-teal-800 font-medium mt-1 inline-block">
        Abrir prontuário →
      </Link>
    </div>

    <!-- Botões principais de fluxo -->
    <div class="flex gap-2">
      <button v-if="consultation.status === 'aguardando'"
              @click="doStart"
              class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
        Iniciar Atendimento
      </button>

      <button v-if="consultation.status === 'em_atendimento'"
              @click="doFinish"
              class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
        Finalizar Consulta
      </button>

      <span v-if="consultation.status === 'finalizado'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-100 text-green-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Consulta concluída
      </span>
    </div>
  </div>

  <!-- ── Grid principal ────────────────────────────────────────────────── -->
  <div class="grid md:grid-cols-3 gap-6">

    <!-- Info lateral -->
    <div class="md:col-span-1 space-y-4">
      <!-- Card de status -->
      <div class="bg-white p-5 rounded-2xl border">
        <div class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Status</div>

        <!-- Fluxo visual de etapas -->
        <div class="space-y-3">
          <div v-for="(step, key) in { aguardando: FLOW.aguardando, em_atendimento: FLOW.em_atendimento, finalizado: FLOW.finalizado }"
               :key="key"
               class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition-all"
                 :class="currentFlow.step >= step.step
                    ? step.color + ' text-white'
                    : 'bg-slate-100 text-slate-400'">
              <svg v-if="currentFlow.step > step.step" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              <span v-else>{{ step.step }}</span>
            </div>
            <div class="flex-1">
              <div class="text-sm" :class="currentFlow.step >= step.step ? 'font-semibold ' + step.text : 'text-slate-400'">
                {{ step.label }}
              </div>
              <div v-if="key === 'aguardando' && consultation.check_in_at"
                   class="text-[11px] text-slate-400">
                {{ new Date(consultation.check_in_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}
              </div>
              <div v-if="key === 'em_atendimento' && consultation.started_at"
                   class="text-[11px] text-slate-400">
                {{ new Date(consultation.started_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}
              </div>
              <div v-if="key === 'finalizado' && consultation.finished_at"
                   class="text-[11px] text-slate-400">
                {{ new Date(consultation.finished_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Badge de status atual -->
        <div class="mt-4 pt-3 border-t">
          <StatusIndicator
            :status="resolveConsultationStatus(consultation)"
            show-label
            size="md" />
        </div>
      </div>

      <!-- Dados do paciente -->
      <div class="bg-white p-5 rounded-2xl border text-sm space-y-2.5">
        <div class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-1">Informações</div>
        <div class="flex justify-between">
          <span class="text-slate-500">Profissional</span>
          <span class="font-medium text-slate-700">{{ consultation.professional?.name || '—' }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Check-in</span>
          <span class="font-medium text-slate-700">
            {{ consultation.check_in_at
                ? new Date(consultation.check_in_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                : '—' }}
          </span>
        </div>
        <div v-if="consultation.appointment">
          <Link :href="route('appointments.edit', consultation.appointment.id)"
                class="text-xs text-emerald-600 hover:underline">
            Ver agendamento →
          </Link>
        </div>
      </div>
    </div>

    <!-- Prontuário + anotações -->
    <div class="md:col-span-2 bg-white p-6 rounded-2xl border">
      <h3 class="font-semibold mb-4 text-slate-800">Prontuário — Anotações (SOAP)</h3>

      <textarea v-model="notes"
                rows="9"
                class="w-full border rounded-xl p-4 font-mono text-sm resize-y focus:outline-none focus:ring-2 focus:ring-emerald-300"
                placeholder="S: Subjetivo&#10;O: Objetivo&#10;A: Avaliação&#10;P: Plano"
                :disabled="consultation.status === 'finalizado'" />

      <div class="mt-3 flex flex-wrap gap-2">
        <button @click="updateNotes"
                :disabled="consultation.status === 'finalizado'"
                class="px-4 py-2 border rounded-lg text-sm hover:bg-slate-50 disabled:opacity-50 transition-colors">
          Salvar anotações
        </button>

        <button v-if="consultation.status === 'em_atendimento'"
                @click="doFinish"
                class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
          Finalizar e salvar
        </button>
      </div>
    </div>
  </div>

  <!-- ── Registrar procedimento ────────────────────────────────────────── -->
  <div class="mt-6 bg-white rounded-2xl border p-6">
    <h3 class="font-semibold mb-4 text-slate-800">Registrar Procedimento Executado</h3>

    <form @submit.prevent="addExecution" class="flex gap-3 items-end flex-wrap">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-slate-600 mb-1">Tratamento</label>
        <select v-model="executionForm.treatment_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-300" required>
          <option value="">Selecione...</option>
          <option v-for="t in treatments" :key="t.id" :value="t.id">
            {{ t.nome }} ({{ t.duracao_padrao }} min)
          </option>
        </select>
      </div>
      <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-medium text-slate-600 mb-1">Observações</label>
        <input v-model="executionForm.notes" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-300" />
      </div>
      <button type="submit"
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
        Registrar
      </button>
    </form>
    <p class="text-xs text-slate-400 mt-2">Materiais vinculados são baixados do estoque automaticamente.</p>
  </div>

</AppLayout>
</template>
