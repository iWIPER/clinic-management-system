<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    record: Object,
    photos: Array,
    attachments: Array,
})

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtTime = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '—'
const fmtCurrency = (val) => Number(val || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const generatePdf = () => {
    window.location.href = route('clinical-records.pdf', props.record.id)
}
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
      <p class="text-sm text-slate-500 mt-1">Registro permanente #{{ record.id }}</p>
    </div>
    <button @click="generatePdf"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      Gerar PDF
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Dados principais -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-4">Dados do atendimento</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Paciente</dt>
            <dd class="font-medium">
              <Link :href="route('patients.show', record.patient.id)" class="text-emerald-700 hover:underline">
                {{ record.patient.nome }} {{ record.patient.sobrenome }}
              </Link>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Profissional</dt>
            <dd class="font-medium">{{ record.professional?.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Procedimento</dt>
            <dd class="font-medium">{{ record.procedure_name }}</dd>
          </div>
          <div v-if="record.procedure_category">
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Categoria</dt>
            <dd>{{ record.procedure_category }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Data</dt>
            <dd>{{ fmtDate(record.finished_at) }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Horário</dt>
            <dd>{{ fmtTime(record.started_at) }} – {{ fmtTime(record.finished_at) }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Duração</dt>
            <dd>{{ record.duration_minutes ? record.duration_minutes + ' min' : '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-400 text-xs uppercase tracking-wide mb-1">Valor</dt>
            <dd class="text-lg font-semibold text-emerald-700">{{ fmtCurrency(record.price) }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="record.notes" class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-3">Observações</h2>
        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ record.notes }}</p>
      </div>

      <!-- Anexos (procedimentos executados) -->
      <div v-if="attachments.length" class="bg-white rounded-2xl border p-6">
        <h2 class="font-medium text-slate-900 mb-4">Anexos / Procedimentos registrados</h2>
        <ul class="divide-y">
          <li v-for="att in attachments" :key="att.id" class="py-3 flex justify-between text-sm">
            <span class="font-medium">{{ att.treatment?.nome || 'Procedimento' }}</span>
            <span class="text-slate-500">{{ fmtCurrency(att.price_charged) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Fotos clínicas -->
    <div class="bg-white rounded-2xl border p-6">
      <h2 class="font-medium text-slate-900 mb-4">Fotos clínicas</h2>
      <div v-if="photos.length" class="grid grid-cols-2 gap-3">
        <a v-for="photo in photos" :key="photo.id"
           :href="route('patients.photos.view', [record.patient.id, photo.id])"
           target="_blank"
           class="group relative aspect-square rounded-lg overflow-hidden bg-slate-100 border">
          <img :src="route('patients.photos.view', [record.patient.id, photo.id])"
               :alt="photo.subcategoria || photo.filename"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
          <div class="absolute inset-x-0 bottom-0 bg-black/50 text-white text-[10px] px-2 py-1 truncate">
            {{ photo.subcategoria || photo.categoria || photo.filename }}
          </div>
        </a>
      </div>
      <p v-else class="text-sm text-slate-400">Nenhuma foto clínica neste período.</p>
    </div>
  </div>
</AppLayout>
</template>