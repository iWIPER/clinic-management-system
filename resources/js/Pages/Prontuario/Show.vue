<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SectionCard from '@/Components/Prontuario/SectionCard.vue'
import OdontogramChart from '@/Components/Prontuario/OdontogramChart.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    patient: Object,
    anamnesis: Object,
    odontogram: Object,
    toothStatuses: Array,
    fdiTeeth: Array,
    clinicBranding: Object,
})

const activeSection = ref('identificacao')

const sections = [
    { id: 'identificacao',  label: 'Identificação',       icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { id: 'anamnese',       label: 'Anamnese',            icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { id: 'historico',      label: 'Histórico Clínico',   icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'procedimentos',  label: 'Procedimentos',       icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
    { id: 'evolucoes',      label: 'Evoluções',           icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
    { id: 'fotos',          label: 'Fotos Clínicas',      icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'documentos',     label: 'Documentos',          icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    { id: 'odontograma',    label: 'Odontograma',         icon: 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
]

const scrollTo = (id) => {
    activeSection.value = id
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'
const fmtCurrency = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const statusLabel = (s) => s === 'concluido' ? 'Concluído' : s === 'cancelado' ? 'Cancelado' : s

const age = computed(() => {
    if (!props.patient.nascimento) return null
    const birth = new Date(props.patient.nascimento)
    const diff = Date.now() - birth.getTime()
    return Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000))
})

// ── Anamnese form ─────────────────────────────────────────────────────────────
const anamnesisForm = useForm({
    queixa_principal: props.anamnesis.queixa_principal ?? '',
    historico_medico: props.anamnesis.historico_medico ?? '',
    alergias: props.anamnesis.alergias ?? '',
    medicamentos_em_uso: props.anamnesis.medicamentos_em_uso ?? '',
    doencas_sistemicas: props.anamnesis.doencas_sistemicas ?? '',
    historico_familiar: props.anamnesis.historico_familiar ?? '',
    gestante: props.anamnesis.gestante ?? false,
    hipertensao: props.anamnesis.hipertensao ?? false,
    diabetes: props.anamnesis.diabetes ?? false,
    cardiopatia: props.anamnesis.cardiopatia ?? false,
    hemorragia: props.anamnesis.hemorragia ?? false,
    fumo: props.anamnesis.fumo ?? false,
    alcool: props.anamnesis.alcool ?? false,
    habitos_outros: props.anamnesis.habitos_outros ?? '',
    cirurgias_previas: props.anamnesis.cirurgias_previas ?? '',
    observacoes: props.anamnesis.observacoes ?? '',
})

const saveAnamnesis = () => {
    anamnesisForm.put(route('patients.prontuario.anamnesis', props.patient.id), { preserveScroll: true })
}

const riskFlags = [
    { key: 'gestante', label: 'Gestante' },
    { key: 'hipertensao', label: 'Hipertensão' },
    { key: 'diabetes', label: 'Diabetes' },
    { key: 'cardiopatia', label: 'Cardiopatia' },
    { key: 'hemorragia', label: 'Hemorragia' },
    { key: 'fumo', label: 'Tabagismo' },
    { key: 'alcool', label: 'Álcool' },
]

// ── Evoluções ─────────────────────────────────────────────────────────────────
const evolutionForm = useForm({ content: '' })

const saveEvolution = () => {
    evolutionForm.post(route('patients.prontuario.evolutions', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => evolutionForm.reset(),
    })
}

// ── Odontograma ───────────────────────────────────────────────────────────────
const teethData = ref({ ...(props.odontogram.teeth_data ?? {}) })
const odontogramNotes = ref(props.odontogram.notes ?? '')
const odontogramForm = useForm({ teeth_data: {}, notes: '' })

const saveOdontogram = () => {
    odontogramForm.teeth_data = teethData.value
    odontogramForm.notes = odontogramNotes.value
    odontogramForm.put(route('patients.prontuario.odontogram', props.patient.id), { preserveScroll: true })
}

// ── Fotos / Documentos ────────────────────────────────────────────────────────
const allPhotos = computed(() => props.patient.photos ?? [])
const clinicalPhotos = computed(() => allPhotos.value.filter(p => p.categoria !== 'Documentação'))
const documents = computed(() => allPhotos.value.filter(p => p.categoria === 'Documentação'))

const generatePdf = () => {
    window.location.href = route('patients.prontuario.pdf', props.patient.id)
}

const fieldClass = 'w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/30 focus:border-teal-400'
const labelClass = 'block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5'
</script>

<template>
<AppLayout>
<div class="min-h-screen bg-slate-100/80 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-6">

    <!-- Cabeçalho do prontuário -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5 bg-gradient-to-r from-teal-700 to-teal-600">
            <div class="flex items-center gap-4">
                <div v-if="clinicBranding.logoUrl" class="h-12 w-12 rounded-lg bg-white/10 p-1 flex items-center justify-center">
                    <img :src="clinicBranding.logoUrl" alt="Logo" class="max-h-full max-w-full object-contain" />
                </div>
                <div v-else class="h-12 w-12 rounded-lg bg-white/20 flex items-center justify-center text-white font-bold text-lg">
                    {{ (clinicBranding.name || 'C')[0] }}
                </div>
                <div class="text-white">
                    <div class="text-xs font-medium text-teal-100 uppercase tracking-widest">Prontuário Odontológico</div>
                    <h1 class="text-xl font-bold">{{ patient.nome }} {{ patient.sobrenome }}</h1>
                    <div v-if="clinicBranding.name" class="text-xs text-teal-100 mt-0.5">
                        {{ clinicBranding.name }}
                        <span v-if="clinicBranding.slogan"> — {{ clinicBranding.slogan }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('patients.show', patient.id)"
                      class="text-xs text-teal-100 hover:text-white border border-white/30 px-3 py-2 rounded-lg transition-colors">
                    Ficha do paciente
                </Link>
                <button @click="generatePdf"
                        class="inline-flex items-center gap-2 bg-white text-teal-700 hover:bg-teal-50 px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Gerar PDF
                </button>
            </div>
        </div>
    </div>

    <div class="flex gap-6 items-start">

        <!-- Navegação lateral -->
        <nav class="hidden lg:block w-52 shrink-0 sticky top-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 space-y-0.5">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 py-2">Seções</p>
                <button v-for="sec in sections" :key="sec.id" type="button"
                        @click="scrollTo(sec.id)"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors text-left"
                        :class="activeSection === sec.id ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50'">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="sec.icon" />
                    </svg>
                    {{ sec.label }}
                </button>
            </div>
        </nav>

        <!-- Conteúdo -->
        <div class="flex-1 min-w-0 space-y-6">

            <!-- 1. Identificação -->
            <SectionCard id="identificacao" title="Identificação do Paciente" subtitle="Dados cadastrais e contato"
                         icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Nome completo</dt>
                        <dd class="text-sm font-semibold text-slate-800 mt-1">{{ patient.nome }} {{ patient.sobrenome }}</dd>
                    </div>
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Nascimento / Idade</dt>
                        <dd class="text-sm text-slate-800 mt-1">{{ fmtDate(patient.nascimento) }} <span v-if="age" class="text-slate-500">({{ age }} anos)</span></dd>
                    </div>
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Documento</dt>
                        <dd class="text-sm text-slate-800 mt-1">{{ patient.doc_tipo || '—' }} {{ patient.doc_numero || '' }}</dd>
                    </div>
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Telefone</dt>
                        <dd class="text-sm text-slate-800 mt-1">{{ patient.telefone || '—' }}</dd>
                    </div>
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">E-mail</dt>
                        <dd class="text-sm text-slate-800 mt-1">{{ patient.email || '—' }}</dd>
                    </div>
                    <div class="bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Status</dt>
                        <dd class="text-sm text-slate-800 mt-1 capitalize">{{ patient.status }}</dd>
                    </div>
                    <div v-if="patient.logradouro" class="sm:col-span-2 lg:col-span-3 bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Endereço</dt>
                        <dd class="text-sm text-slate-800 mt-1">
                            {{ patient.logradouro }}, {{ patient.numero }}
                            <template v-if="patient.complemento"> — {{ patient.complemento }}</template>
                            — {{ patient.bairro }}, {{ patient.cidade }}/{{ patient.estado }}
                            <template v-if="patient.cep"> · CEP {{ patient.cep }}</template>
                        </dd>
                    </div>
                    <div v-if="patient.contato_emergencia_nome" class="sm:col-span-2 bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                        <dt class="text-[10px] font-bold text-slate-400 uppercase">Contato de emergência</dt>
                        <dd class="text-sm text-slate-800 mt-1">{{ patient.contato_emergencia_nome }} — {{ patient.contato_emergencia_telefone }}</dd>
                    </div>
                </dl>
            </SectionCard>

            <!-- 2. Anamnese -->
            <SectionCard id="anamnese" title="Anamnese" subtitle="Questionário clínico e histórico de saúde"
                         icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                <form @submit.prevent="saveAnamnesis" class="space-y-5">
                    <div>
                        <label :class="labelClass">Queixa principal</label>
                        <textarea v-model="anamnesisForm.queixa_principal" rows="2" :class="fieldClass"
                                  placeholder="Motivo da consulta, sintomas relatados pelo paciente..." />
                    </div>

                    <div>
                        <p :class="labelClass">Fatores de risco / Condições</p>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="flag in riskFlags" :key="flag.key"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-sm"
                                   :class="anamnesisForm[flag.key] ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                <input type="checkbox" v-model="anamnesisForm[flag.key]" class="rounded text-teal-600" />
                                {{ flag.label }}
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="field in [
                            { key: 'alergias', label: 'Alergias', ph: 'Medicamentos, alimentos, látex...' },
                            { key: 'medicamentos_em_uso', label: 'Medicamentos em uso', ph: 'Liste os medicamentos atuais...' },
                            { key: 'doencas_sistemicas', label: 'Doenças sistêmicas', ph: 'Condições de saúde relevantes...' },
                            { key: 'historico_medico', label: 'Histórico médico', ph: 'Internações, tratamentos anteriores...' },
                            { key: 'historico_familiar', label: 'Histórico familiar', ph: 'Antecedentes familiares relevantes...' },
                            { key: 'cirurgias_previas', label: 'Cirurgias prévias', ph: 'Procedimentos cirúrgicos realizados...' },
                        ]" :key="field.key">
                            <label :class="labelClass">{{ field.label }}</label>
                            <textarea v-model="anamnesisForm[field.key]" rows="3" :class="fieldClass" :placeholder="field.ph" />
                        </div>
                    </div>

                    <div>
                        <label :class="labelClass">Outros hábitos</label>
                        <input v-model="anamnesisForm.habitos_outros" type="text" :class="fieldClass" placeholder="Bruxismo, respiração bucal, etc." />
                    </div>
                    <div>
                        <label :class="labelClass">Observações complementares</label>
                        <textarea v-model="anamnesisForm.observacoes" rows="2" :class="fieldClass" />
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                        <p v-if="anamnesis.updated_by" class="text-[10px] text-slate-400">
                            Última atualização: {{ fmtDateTime(anamnesis.updated_at) }}
                        </p>
                        <button type="submit" :disabled="anamnesisForm.processing"
                                class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold">
                            Salvar anamnese
                        </button>
                    </div>
                </form>
            </SectionCard>

            <!-- 3. Histórico clínico -->
            <SectionCard id="historico" title="Histórico Clínico" subtitle="Linha do tempo de consultas e atendimentos"
                         icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                <div v-if="patient.consultations?.length" class="relative">
                    <div class="absolute left-[11px] top-3 bottom-3 w-px bg-teal-200" />
                    <div class="space-y-4">
                        <div v-for="cons in patient.consultations" :key="cons.id" class="flex gap-4">
                            <div class="relative shrink-0 mt-1">
                                <span class="flex h-6 w-6 rounded-full border-2 border-white shadow ring-2"
                                      :class="cons.status === 'finalizado' ? 'bg-teal-500 ring-teal-200' : 'bg-slate-300 ring-slate-200'" />
                            </div>
                            <div class="flex-1 bg-slate-50 rounded-lg border border-slate-100 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-xs font-bold text-teal-700">{{ fmtDateTime(cons.finished_at || cons.check_in_at) }}</span>
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600 capitalize">
                                        {{ cons.status?.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600">Profissional: <strong>{{ cons.professional?.name || '—' }}</strong></p>
                                <p v-if="cons.notes" class="text-xs text-slate-700 mt-1.5 whitespace-pre-wrap">{{ cons.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400 text-center py-6">Nenhuma consulta registrada.</p>
            </SectionCard>

            <!-- 4. Procedimentos realizados -->
            <SectionCard id="procedimentos" title="Procedimentos Realizados" subtitle="Histórico permanente de atendimentos concluídos"
                         icon="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z">
                <div v-if="patient.clinical_records?.length" class="overflow-x-auto -mx-2">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-teal-50 text-teal-800">
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Data</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Procedimento</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Profissional</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Duração</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Valor</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="rec in patient.clinical_records" :key="rec.id" class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ fmtDate(rec.finished_at) }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ rec.procedure_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ rec.professional?.name || '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ rec.duration_minutes ? rec.duration_minutes + ' min' : '—' }}</td>
                                <td class="px-4 py-3 text-slate-700 font-medium">{{ fmtCurrency(rec.price) }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-semibold px-2 py-1 rounded-full"
                                          :class="rec.status === 'concluido' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                        {{ statusLabel(rec.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('clinical-records.show', rec.id)"
                                          class="text-teal-600 hover:text-teal-800 text-xs font-semibold">
                                        Detalhes →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-slate-400 text-center py-6">Nenhum procedimento concluído registrado.</p>
            </SectionCard>

            <!-- 5. Evoluções -->
            <SectionCard id="evolucoes" title="Evoluções Clínicas" subtitle="Registro cronológico do acompanhamento"
                         icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                <form @submit.prevent="saveEvolution" class="mb-6 bg-teal-50/50 rounded-xl border border-teal-100 p-4">
                    <label :class="labelClass">Nova evolução</label>
                    <textarea v-model="evolutionForm.content" rows="4" :class="fieldClass"
                              placeholder="Ex: Paciente sem dor. Realizada limpeza. Orientações fornecidas." required />
                    <div class="flex justify-end mt-3">
                        <button type="submit" :disabled="evolutionForm.processing"
                                class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            Registrar evolução
                        </button>
                    </div>
                </form>

                <div v-if="patient.evolutions?.length" class="space-y-4">
                    <div v-for="evo in patient.evolutions" :key="evo.id"
                         class="border-l-4 border-teal-400 bg-white rounded-r-xl border border-slate-100 px-5 py-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-bold text-teal-700">{{ fmtDate(evo.recorded_at) }}</span>
                            <span class="text-[10px] text-slate-400">{{ new Date(evo.recorded_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}</span>
                            <span class="text-[10px] text-slate-500">— {{ evo.professional?.name }}</span>
                        </div>
                        <p class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ evo.content }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400 text-center py-4">Nenhuma evolução registrada.</p>
            </SectionCard>

            <!-- 6. Fotos clínicas -->
            <SectionCard id="fotos" title="Fotos Clínicas" subtitle="Registro fotográfico do tratamento"
                         icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                <div v-if="clinicalPhotos.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <a v-for="photo in clinicalPhotos" :key="photo.id"
                       :href="route('patients.photos.view', [patient.id, photo.id])" target="_blank"
                       class="group relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100 hover:shadow-md transition-shadow">
                        <img :src="route('patients.photos.view', [patient.id, photo.id])"
                             :alt="photo.subcategoria || photo.filename"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 pt-6">
                            <p class="text-[10px] text-white font-medium truncate">{{ photo.subcategoria || photo.categoria }}</p>
                            <p class="text-[9px] text-white/70">{{ fmtDate(photo.taken_at) }} <template v-if="photo.dente"> · Dente {{ photo.dente }}</template></p>
                        </div>
                    </a>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-sm text-slate-400">Nenhuma foto clínica registrada.</p>
                    <Link :href="route('patients.show', patient.id)" class="text-xs text-teal-600 hover:underline mt-2 inline-block">
                        Enviar fotos na ficha do paciente →
                    </Link>
                </div>
            </SectionCard>

            <!-- 7. Documentos -->
            <SectionCard id="documentos" title="Documentos" subtitle="Termos, laudos, receitas e documentação"
                         icon="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                <div v-if="documents.length" class="divide-y divide-slate-100">
                    <div v-for="doc in documents" :key="doc.id"
                         class="flex items-center gap-4 py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg transition-colors">
                        <div class="h-10 w-10 rounded-lg bg-red-50 border border-red-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ doc.subcategoria || doc.filename }}</p>
                            <p class="text-[10px] text-slate-400">{{ fmtDate(doc.taken_at) }} · {{ doc.categoria }}</p>
                        </div>
                        <a :href="route('patients.photos.view', [patient.id, doc.id])" target="_blank"
                           class="text-xs text-teal-600 hover:text-teal-800 font-semibold shrink-0">Abrir →</a>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400 text-center py-6">Nenhum documento registrado.</p>
            </SectionCard>

            <!-- 8. Odontograma -->
            <SectionCard id="odontograma" title="Odontograma" subtitle="Mapa dentário FDI — clique para alterar status"
                         icon="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                <OdontogramChart
                    :teeth-data="teethData"
                    :fdi-teeth="fdiTeeth"
                    :tooth-statuses="toothStatuses"
                    @update:teeth-data="teethData = $event" />

                <div class="mt-5">
                    <label :class="labelClass">Observações do odontograma</label>
                    <textarea v-model="odontogramNotes" rows="2" :class="fieldClass"
                              placeholder="Anotações gerais sobre a arcada dentária..." />
                </div>

                <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                    <button @click="saveOdontogram" :disabled="odontogramForm.processing"
                            class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold">
                        Salvar odontograma
                    </button>
                </div>
            </SectionCard>

        </div>
    </div>
</div>
</AppLayout>
</template>