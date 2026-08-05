<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AnamnesisSignatureCard from '@/Components/Anamnesis/AnamnesisSignatureCard.vue'
import AnamnesisDentistSignatureModal from '@/Components/Anamnesis/AnamnesisDentistSignatureModal.vue'
import { categoryMeta, sortCategories } from '@/composables/useAnamnesisCategories'
import { filterRenderableQuestions } from '@/composables/useAnamnesisQuestions'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    patient: Object,
    editor: Object,
})

const page = usePage()

// ── Assinaturas ───────────────────────────────────────────────────────────
const patientSignatureData = ref(props.editor?.signature || null)
const dentistSignatureData  = ref(props.editor?.dentist_signature || null)
const instanceStatus        = ref(props.editor?.instance?.status || 'draft')

const isPatientSigned  = computed(() => patientSignatureData.value !== null)
const isDentistSigned  = computed(() => dentistSignatureData.value !== null)
const isFullySigned    = computed(() => instanceStatus.value === 'fully_signed')

// ── Permissões do profissional logado ─────────────────────────────────────
const authUser      = computed(() => page.props.auth?.user || {})
const currentUserId = computed(() => authUser.value.id)
const instanceProfId = computed(() => props.editor?.instance?.professional_id)
const isResponsible  = computed(() => currentUserId.value && currentUserId.value === instanceProfId.value)

const canSignDentist = computed(() =>
    isPatientSigned.value && !isDentistSigned.value && isResponsible.value && !isFullySigned.value
)

const professionalCro = computed(() => {
    const cro = authUser.value.cro || ''
    const uf  = authUser.value.cro_uf || ''
    return cro ? (cro + (uf ? '/' + uf : '')) : ''
})

// ── Modal dentista ─────────────────────────────────────────────────────────
const showDentistModal = ref(false)
const signError = ref('')

const submitDentistSignature = async (formData) => {
    signError.value = ''
    try {
        const { data } = await axios.post(
            route('patients.anamneses.sign-professional', [props.patient.id, props.editor.instance.id]),
            formData
        )
        dentistSignatureData.value = data.dentist_signature
        instanceStatus.value       = data.instance.status
        showDentistModal.value     = false
        router.reload({ only: ['editor'] })
    } catch (e) {
        signError.value = e?.response?.data?.message || e?.response?.data?.error || 'Não foi possível registrar a assinatura. Tente novamente.'
    }
}

// ── Categorias / respostas ─────────────────────────────────────────────────
const normalizeCategories = (c) => {
    if (!c) return []
    if (Array.isArray(c)) return c
    return Object.values(c)
}

const sortedCategories = computed(() =>
    sortCategories(normalizeCategories(props.editor?.categories)).map(cat => ({
        ...cat,
        questions: filterRenderableQuestions(cat.questions || []),
    })).filter(cat => cat.questions.length)
)

const displayValue = (q) => {
    if (!q.value && !q.supplementary_text) return '—'
    const labels = { sim: 'Sim', nao: 'Não', nao_sei: 'Não sei' }
    let v = labels[q.value] || q.value || ''
    if (q.supplementary_text) v += (v ? ' — ' : '') + q.supplementary_text
    return v
}

const valueColor = (q) => {
    if (q.value === 'sim' && q.has_alert) return 'text-red-600 font-semibold'
    if (q.value === 'sim') return 'text-slate-800'
    if (q.value === 'nao') return 'text-slate-500'
    return 'text-slate-700'
}

// ── Status badges ──────────────────────────────────────────────────────────
const STATUS_BADGE = {
    draft:              { cls: 'bg-slate-100 text-slate-600 border-slate-200',   label: 'Rascunho',                     icon: '○' },
    in_progress:        { cls: 'bg-blue-50 text-blue-700 border-blue-100',       label: 'Em preenchimento',              icon: '◔' },
    completed:          { cls: 'bg-emerald-50 text-emerald-700 border-emerald-100', label: 'Em revisão',                 icon: '✔' },
    awaiting_signature: { cls: 'bg-amber-50 text-amber-700 border-amber-100',    label: 'Aguardando assinatura',         icon: '⌛' },
    signed:             { cls: 'bg-amber-50 text-amber-700 border-amber-200',    label: 'Paciente assinou',              icon: '✎' },
    fully_signed:       { cls: 'bg-teal-50 text-teal-700 border-teal-100',       label: 'Completamente assinada',        icon: '✔' },
    cancelled:          { cls: 'bg-red-50 text-red-600 border-red-100',          label: 'Cancelada',                     icon: '✖' },
}

const statusBadge = computed(() => STATUS_BADGE[instanceStatus.value] || STATUS_BADGE.draft)

const fmtDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto pb-32">

            <!-- Breadcrumb -->
            <div class="mb-4">
                <Link
                    :href="route('patients.show', { patient: patient.id, tab: 'anamneses' })"
                    class="text-[11px] text-slate-400 hover:text-slate-600 transition-colors"
                >← Ficha do paciente</Link>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ patient.nome }} {{ patient.sobrenome }}</p>
            </div>

            <!-- ── Cabeçalho do documento ──────────────────────────────────── -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-5">

                <!-- Faixa de status -->
                <div class="px-6 py-3 border-b flex items-center justify-between gap-3"
                     :class="{
                         'bg-teal-50 border-teal-100': isFullySigned,
                         'bg-amber-50 border-amber-100': isPatientSigned && !isFullySigned,
                         'bg-slate-50 border-slate-100': !isPatientSigned,
                     }">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-widest"
                              :class="{
                                  'text-teal-700': isFullySigned,
                                  'text-amber-700': isPatientSigned && !isFullySigned,
                                  'text-slate-500': !isPatientSigned,
                              }">
                            {{ editor?.instance?.display_name || editor?.instance?.template_name }}
                        </span>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold"
                        :class="statusBadge.cls"
                    >
                        <span class="text-[8px]">{{ statusBadge.icon }}</span>
                        {{ statusBadge.label }}
                    </span>
                </div>

                <!-- Metadados -->
                <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Paciente</p>
                        <p class="text-[13px] font-semibold text-slate-800 mt-0.5">{{ patient.nome }} {{ patient.sobrenome }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Profissional</p>
                        <p class="text-[13px] text-slate-700 mt-0.5">{{ editor?.instance?.professional || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Data</p>
                        <p class="text-[13px] text-slate-700 mt-0.5">{{ fmtDate(editor?.instance?.anamnesis_date) }}</p>
                    </div>
                </div>

                <!-- Ações -->
                <div class="px-6 py-3 border-t border-slate-100 flex flex-wrap gap-2">
                    <a
                        :href="route('patients.anamneses.edit', [patient.id, editor.instance.id])"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Editar
                    </a>
                    <a
                        :href="route('patients.anamneses.pdf', [patient.id, editor.instance.id])"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Baixar PDF
                    </a>
                    <button
                        type="button"
                        @click="() => window.print()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Imprimir
                    </button>
                    <button
                        type="button"
                        @click="router.post(route('patients.anamneses.duplicate', [patient.id, editor.instance.id]))"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Duplicar
                    </button>
                </div>
            </div>

            <!-- ── Cards de assinatura ─────────────────────────────────────── -->
            <AnamnesisSignatureCard
                class="mb-5"
                :patient-signature="patientSignatureData"
                :dentist-signature="dentistSignatureData"
                :can-sign-patient="false"
                :can-sign-dentist="false"
            />

            <!-- ── Respostas por categoria ──────────────────────────────────── -->
            <div class="space-y-5">
                <section
                    v-for="(category, idx) in sortedCategories"
                    :key="category.name + idx"
                    class="rounded-2xl border border-slate-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden"
                >
                    <!-- Header da categoria -->
                    <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 border-b border-slate-100">
                        <span class="text-base">{{ categoryMeta(category.name).icon }}</span>
                        <div>
                            <h2 class="text-[11px] font-bold uppercase tracking-wide text-slate-700">
                                {{ category.name.toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) }}
                            </h2>
                            <p v-if="categoryMeta(category.name).description" class="text-[10px] text-slate-400 mt-px">
                                {{ categoryMeta(category.name).description }}
                            </p>
                        </div>
                    </div>

                    <!-- Perguntas -->
                    <div class="divide-y divide-slate-50">
                        <div
                            v-for="q in category.questions"
                            :key="q.id"
                            class="px-5 py-3 flex items-start gap-4"
                            :class="q.has_alert && q.value === 'sim' ? 'bg-red-50/40' : ''"
                        >
                            <!-- Indicador de alerta -->
                            <div class="shrink-0 mt-1">
                                <div
                                    v-if="q.has_alert && q.value === 'sim'"
                                    class="w-1.5 h-1.5 rounded-full bg-red-500 mt-1"
                                />
                                <div v-else class="w-1.5 h-1.5 rounded-full bg-slate-200 mt-1" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[12px] font-medium text-slate-700 leading-snug">{{ q.text }}</p>
                                <p class="text-[13px] mt-1 leading-snug" :class="valueColor(q)">{{ displayValue(q) }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

        </div>

        <!-- ── Rodapé fixo: botão de assinatura do dentista ───────────────── -->
        <div
            v-if="isPatientSigned && !isFullySigned"
            class="fixed bottom-0 inset-x-0 z-20 bg-white border-t border-slate-200 shadow-lg"
        >
            <div class="max-w-4xl mx-auto px-4 py-3">
                <!-- Mensagem informativa -->
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-teal-100">
                            <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-800">Paciente assinou esta anamnese</p>
                            <p class="text-[11px] text-slate-500">Revise o documento acima e assine como profissional responsável.</p>
                        </div>
                    </div>
                    <button
                        v-if="canSignDentist"
                        type="button"
                        @click="signError = ''; showDentistModal = true"
                        class="shrink-0 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-[13px] font-bold text-white hover:bg-emerald-700 active:scale-[0.98] transition-all shadow-md"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        ASSINAR COMO DENTISTA
                    </button>
                    <p v-else class="text-[12px] text-slate-400 italic shrink-0">
                        Somente o profissional responsável pode assinar.
                    </p>
                </div>
            </div>
        </div>

        <!-- Modal de assinatura do dentista -->
        <AnamnesisDentistSignatureModal
            :show="showDentistModal"
            :professional-name="authUser.name || ''"
            :professional-cro="professionalCro"
            :server-error="signError"
            @close="showDentistModal = false"
            @signed="submitDentistSignature"
        />

    </AppLayout>
</template>
