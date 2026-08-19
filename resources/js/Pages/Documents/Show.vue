<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import DocumentStatusBadge from '@/Components/Documents/DocumentStatusBadge.vue'
import DocumentSignaturePanel from '@/Components/Documents/DocumentSignaturePanel.vue'
import ShareDocumentModal from '@/Components/Documents/ShareDocumentModal.vue'
import DocumentSharesPanel from '@/Components/Documents/DocumentSharesPanel.vue'

const props = defineProps({
    patient: Object,
    document: Object,
})

const page = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id)
const canSignProfessional = computed(() => document.professional_id === currentUserId.value)

const cancelDocument = () => {
    const reason = prompt('Motivo do cancelamento (opcional):', '')
    if (reason === null) return
    router.post(route('patients.documents.cancel', [props.patient.id, props.document.id]), { reason })
}

const showShareModal = ref(false)
const shareRefreshKey = ref(0)
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <Link :href="route('patients.show', { patient: patient.id, tab: 'documents' })" class="text-[11px] text-slate-400 hover:text-teal-600 transition-colors">← Voltar ao prontuário</Link>
                    <div class="flex items-center gap-3 mt-1">
                        <h1 class="text-xl font-bold text-slate-900">{{ document.template_name }}</h1>
                        <DocumentStatusBadge :status="document.status" :status-label="document.status_label" :status-color="document.status_color" />
                    </div>
                    <p class="text-[12px] text-slate-400 mt-1">{{ document.document_code }} · {{ document.category }} · Emitido em {{ document.issued_at }} por {{ document.professional }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="document.pdf_url" :href="document.pdf_url" target="_blank" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Ver PDF</a>
                    <a v-else :href="route('patients.documents.pdf', [patient.id, document.id])" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Gerar PDF</a>
                    <button
                        v-if="document.pdf_url"
                        @click="showShareModal = true"
                        class="rounded-xl border border-teal-200 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 transition-colors"
                    >Compartilhar</button>
                    <button
                        v-if="document.status !== 'cancelled' && document.status !== 'completed'"
                        @click="cancelDocument"
                        class="rounded-xl border border-red-100 px-4 py-2 text-sm font-medium text-red-500 hover:bg-red-50 transition-colors"
                    >Cancelar</button>
                </div>
            </div>

            <ShareDocumentModal
                :show="showShareModal"
                :patient-id="patient.id"
                :document-id="document.id"
                :default-email="patient.email"
                :default-name="patient.nome_completo || `${patient.nome} ${patient.sobrenome}`"
                @close="showShareModal = false"
                @shared="shareRefreshKey++"
            />

            <div class="grid lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6">
                        <div class="prose prose-sm prose-slate max-w-none" v-html="document.rendered_html" />
                    </div>

                    <div v-if="document.related_treatments?.length || document.related_budgets?.length" class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="text-sm font-bold text-slate-900 mb-3">Vinculado a</h3>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in document.related_treatments" :key="'t' + t.id" class="text-[11px] rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-slate-600">🦷 {{ t.nome }}</span>
                            <span v-for="b in document.related_budgets" :key="'b' + b.id" class="text-[11px] rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-slate-600">💰 Orçamento #{{ b.id }}</span>
                        </div>
                    </div>

                    <DocumentSharesPanel
                        :patient-id="patient.id"
                        :document-id="document.id"
                        :refresh-key="shareRefreshKey"
                    />
                </div>

                <div>
                    <DocumentSignaturePanel
                        v-if="document.required_roles?.length"
                        :patient-id="patient.id"
                        :document-id="document.id"
                        :patient-name="patient.nome_completo || `${patient.nome} ${patient.sobrenome}`"
                        :patient-phone="patient.telefone"
                        :can-sign-professional="canSignProfessional"
                        :pdf-url="document.pdf_url"
                    />
                    <div v-else class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-[12px] text-slate-400">
                        Este documento não exige assinaturas.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
