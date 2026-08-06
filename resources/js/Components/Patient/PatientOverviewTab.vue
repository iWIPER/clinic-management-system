<script setup>
import { ref, computed } from 'vue'
import SummaryCards from '@/Components/PatientHub/SummaryCards.vue'
import OdontogramChart from '@/Components/Prontuario/OdontogramChart.vue'
import OdontogramPreviewModal from '@/Components/Prontuario/OdontogramPreviewModal.vue'
import InfoPopover from '@/Components/UI/InfoPopover.vue'
import { resolvePatientDocument } from '@/composables/usePatientDocument.js'
import { UserIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    patient: Object,
    hub: Object,
    fmtDate: Function,
    hasAddress: Boolean,
    streetLine: String,
    cityStateLine: String,
    odontogram: { type: Object, default: () => null },
    toothStatuses: { type: Array, default: () => [] },
    treatmentsByTooth: { type: Object, default: () => ({}) },
})

const showOdontogramModal = ref(false)
const odontogramTeethData = computed(() => props.odontogram?.teeth_data ?? {})

const ORIGEM_LABELS = {
    manual: 'Manual',
    indicacao: 'Indicação',
    google: 'Google',
    instagram: 'Instagram',
    facebook: 'Facebook',
    whatsapp: 'WhatsApp',
    site: 'Site',
    convenio: 'Convênio',
    outro: 'Outro',
}

const BADGE_COLORS = {
    blue: 'bg-blue-50 text-blue-700 border-blue-200',
    orange: 'bg-orange-50 text-orange-700 border-orange-200',
    purple: 'bg-purple-50 text-purple-700 border-purple-200',
    slate: 'bg-slate-50 text-slate-700 border-slate-200',
    red: 'bg-red-50 text-red-700 border-red-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
    indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
}

const categoryIcons = {
    clinico: 'text-teal-600 bg-teal-50',
    financeiro: 'text-emerald-600 bg-emerald-50',
    documentos: 'text-blue-600 bg-blue-50',
    arquivos: 'text-purple-600 bg-purple-50',
}

const showTimeline = ref(false)

function fmtDateTime(iso) {
    if (!iso) return null
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

const codigoInterno = computed(() => '#' + String(props.patient.id).padStart(6, '0'))

// Responsável legal substitui o contato de emergência quando existir — não
// duplica a informação, é uma alternativa ao mesmo cartão. `fields` alimenta
// o InfoPopover — cada bloco informativo do Patient Hub monta a sua própria
// lista, o componente em si não sabe nada sobre "responsável" ou "emergência".
const emergencyCard = computed(() => {
    if (props.patient.possui_responsavel_legal) {
        const doc = resolvePatientDocument({
            cpf: props.patient.responsavel_legal_cpf,
            rg: props.patient.responsavel_legal_rg,
            passaporte: props.patient.responsavel_legal_passaporte,
        })
        // Nome não entra em `fields` — já aparece no gatilho, o popover só
        // mostra os dados complementares (evita repetir a mesma informação).
        const fields = []
        if (props.patient.responsavel_legal_telefone) {
            fields.push({ label: 'Telefone', value: props.patient.responsavel_legal_telefone, copyValue: props.patient.responsavel_legal_telefone })
        }
        if (doc.copyValue) fields.push({ label: doc.label, value: doc.text, copyValue: doc.copyValue })
        if (props.patient.responsavel_legal_parentesco) {
            fields.push({ label: 'Grau de parentesco', value: props.patient.responsavel_legal_parentesco })
        }

        return {
            label: 'Responsável',
            name: props.patient.responsavel_legal_nome || 'Não informado',
            fields,
        }
    }
    if (props.patient.contato_emergencia_nome || props.patient.contato_emergencia_telefone) {
        const fields = []
        if (props.patient.contato_emergencia_telefone) {
            fields.push({ label: 'Telefone', value: props.patient.contato_emergencia_telefone, copyValue: props.patient.contato_emergencia_telefone })
        }

        return {
            label: 'Contato de emergência',
            name: props.patient.contato_emergencia_nome || 'Não informado',
            fields,
        }
    }
    return null
})

// doc_tipo/doc_numero são legado — cpf/rg/passaporte são as colunas atuais.
// Mesmo resolvedor usado em Pages/Patients/Show.vue e no doc do responsável
// legal acima — uma única fonte de verdade para a prioridade CPF > RG > Passaporte.
// Mantém o fallback "—" já usado nesta lista (o resolvedor por si só devolve
// "Sem documento", mas aqui preserva-se o texto original desta tela).
const documentoPrincipal = computed(() => {
    const doc = resolvePatientDocument({ cpf: props.patient.cpf, rg: props.patient.rg, passaporte: props.patient.passaporte })
    return doc.copyValue ? doc.text : '—'
})
</script>

<template>
    <div class="space-y-8">
        <!-- Dados pessoais + Odontograma, lado a lado -->
        <div class="grid md:grid-cols-2 gap-6 items-start">
            <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">Dados pessoais</p>
                <dl class="space-y-1.5 text-sm">
                    <InfoPopover v-if="emergencyCard" :title="emergencyCard.label" :title-icon="UserIcon" :fields="emergencyCard.fields" class="mb-1.5">
                        <template #trigger="{ open }">
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-red-50 border border-red-100/60 px-2.5 py-1.5 cursor-pointer hover:bg-red-100/40 transition-colors">
                                <span class="flex items-baseline gap-2 min-w-0 flex-1">
                                    <dt class="text-red-600 font-medium shrink-0">{{ emergencyCard.label }}</dt>
                                    <dd class="text-red-800 truncate min-w-0" :title="emergencyCard.name">{{ emergencyCard.name }}</dd>
                                </span>
                                <ChevronDownIcon stroke-width="2.5" class="w-4 h-4 text-slate-700 shrink-0 transition-transform duration-[180ms]" :class="{ 'rotate-180': open }" />
                            </div>
                        </template>
                    </InfoPopover>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Nome</dt><dd>{{ patient.nome }} {{ patient.sobrenome }}</dd></div>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Nascimento</dt><dd>{{ fmtDate(patient.nascimento) || '—' }}</dd></div>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Status</dt><dd class="capitalize">{{ patient.status }}</dd></div>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Documento</dt><dd>{{ documentoPrincipal }}</dd></div>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Email</dt><dd class="truncate">{{ patient.email || '—' }}</dd></div>
                    <div class="flex items-baseline gap-1.5"><dt class="text-slate-500 shrink-0 w-24">Telefone</dt><dd>{{ patient.telefone || '—' }}</dd></div>
                    <div class="flex items-baseline gap-1.5">
                        <dt class="text-slate-500 shrink-0 w-24">Endereço</dt>
                        <dd v-if="hasAddress">
                            {{ streetLine }}<template v-if="patient.complemento"> - {{ patient.complemento }}</template>
                            <template v-if="patient.bairro">, {{ patient.bairro }}</template>
                            <template v-if="cityStateLine"><br />{{ cityStateLine }}<span v-if="patient.cep"> - {{ patient.cep }}</span></template>
                        </dd>
                        <dd v-else>—</dd>
                    </div>
                </dl>
            </section>

            <!-- Odontograma (miniatura) — mesmo componente da aba Odontograma, só em escala reduzida -->
            <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">Odontograma</p>
                <button type="button" class="block w-full text-left" @click="showOdontogramModal = true">
                    <OdontogramChart
                        :teeth-data="odontogramTeethData"
                        :tooth-statuses="toothStatuses"
                        :treatments-by-tooth="treatmentsByTooth"
                        readonly compact
                        class="pointer-events-none" />
                    <p class="flex items-center justify-center gap-1 text-[10px] text-slate-400 mt-2">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                        </svg>
                        Clique para ampliar
                    </p>
                </button>
            </section>
        </div>

        <!-- Dados administrativos -->
        <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5">Dados administrativos</p>
            <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500 shrink-0">Código interno</dt><dd class="text-right font-mono">{{ codigoInterno }}</dd></div>
                <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500 shrink-0">Paciente desde</dt><dd class="text-right">{{ fmtDate(patient.created_at) || '—' }}</dd></div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-slate-500 shrink-0">Última atualização</dt>
                    <dd class="text-right">{{ fmtDateTime(patient.updated_at) || '—' }}<span v-if="patient.updated_by" class="text-slate-400"> · {{ patient.updated_by.name }}</span></dd>
                </div>
                <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500 shrink-0">Cadastrado por</dt><dd class="text-right">{{ patient.created_by?.name || '—' }}</dd></div>
                <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500 shrink-0">Origem</dt><dd class="text-right">{{ ORIGEM_LABELS[patient.origem] || '—' }}</dd></div>
                <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500 shrink-0">Tratamentos concluídos</dt><dd class="text-right font-medium">{{ hub?.summary?.clinical?.treatments_completed ?? 0 }}</dd></div>
            </dl>
        </section>

        <!-- Resumo Financeiro -->
        <section v-if="hub?.summary">
            <SummaryCards :summary="hub.summary" />
        </section>

        <!-- Indicadores (sempre visíveis) -->
        <section v-if="hub?.badges?.length">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Indicadores</h3>
            <div class="flex flex-wrap gap-2">
                <span v-for="badge in hub.badges" :key="badge.key"
                      class="rounded-full border px-2.5 py-1 text-xs font-medium"
                      :class="BADGE_COLORS[badge.color] || BADGE_COLORS.slate">
                    {{ badge.label }}
                </span>
            </div>
        </section>

        <!-- Timeline (recolhível: 5 mais recentes por padrão) -->
        <section v-if="hub?.timeline?.length">
            <button @click="showTimeline = !showTimeline" class="flex items-center gap-2 text-sm font-semibold text-slate-900 mb-3">
                Últimos acontecimentos ({{ hub.timeline.length }})
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': showTimeline }"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div v-show="showTimeline" class="space-y-2">
                <div v-for="(event, i) in hub.timeline" :key="i"
                     class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold"
                          :class="categoryIcons[event.category] ?? 'bg-slate-100 text-slate-600'">●</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 leading-tight">{{ event.title }}</p>
                        <p v-if="event.detail" class="text-xs text-slate-500">{{ event.detail }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-slate-400">{{ fmtDateTime(event.occurred_at) }}</span>
                </div>
            </div>
        </section>
    </div>

    <OdontogramPreviewModal v-if="showOdontogramModal"
        :teeth-data="odontogramTeethData"
        :tooth-statuses="toothStatuses"
        :treatments-by-tooth="treatmentsByTooth"
        @close="showOdontogramModal = false" />
</template>
