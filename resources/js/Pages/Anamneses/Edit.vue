<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AnamnesisCategoryCard from '@/Components/Anamnesis/AnamnesisCategoryCard.vue'
import AnamnesisSummaryBar from '@/Components/Anamnesis/AnamnesisSummaryBar.vue'
import AnamnesisSidebarNav from '@/Components/Anamnesis/AnamnesisSidebarNav.vue'
import AnamnesisAddQuestionModal from '@/Components/Anamnesis/AnamnesisAddQuestionModal.vue'
import AnamnesisSignatureModal from '@/Components/Anamnesis/AnamnesisSignatureModal.vue'
import AnamnesisDentistSignatureModal from '@/Components/Anamnesis/AnamnesisDentistSignatureModal.vue'
import AnamnesisSignatureCard from '@/Components/Anamnesis/AnamnesisSignatureCard.vue'
import { categorySlug, sortCategories } from '@/composables/useAnamnesisCategories'
import { filterRenderableQuestions } from '@/composables/useAnamnesisQuestions'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import axios from 'axios'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const friendlyError = (err, fallback) => err?.response?.data?.message || err?.response?.data?.error || fallback

const props = defineProps({
    patient: Object,
    editor: Object,
})

const page = usePage()

const answers = reactive({})
const saving = ref(false)
const savedAt = ref(null)
const activeSlug = ref('')
let saveTimer = null
let observer = null

// Estado das assinaturas
const patientSignatureData = ref(props.editor?.signature || null)
const dentistSignatureData  = ref(props.editor?.dentist_signature || null)
const instanceStatus        = ref(props.editor?.instance?.status || 'draft')
const isFullySigned         = computed(() => instanceStatus.value === 'fully_signed')
const isPatientSigned       = computed(() => patientSignatureData.value !== null)
const isDentistSigned       = computed(() => dentistSignatureData.value !== null)

// Qualquer assinatura bloqueia edição
const isReadonly = computed(() => isPatientSigned.value || isFullySigned.value)

// O profissional logado é o responsável pela instância?
const currentUserId       = computed(() => page.props.auth?.user?.id)
const instanceProfId      = computed(() => props.editor?.instance?.professional_id)
const isResponsible       = computed(() => currentUserId.value && currentUserId.value === instanceProfId.value)

// Pode assinar como profissional? Precisa: paciente assinou, dentista não assinou, é o responsável
const canSignDentist = computed(() =>
    isPatientSigned.value && !isDentistSigned.value && isResponsible.value && !isFullySigned.value
)

// Disabled question IDs — reactive Set para O(1) lookup
const disabledIds = reactive(new Set(props.editor?.instance?.disabled_question_ids || []))

// Modal state
const showSignModal        = ref(false)
const showDentistSignModal = ref(false)
const showAddModal         = ref(false)
const addingToCategory     = ref('')

const normalizeCategories = (categories) => {
    if (!categories) return []
    if (Array.isArray(categories)) return categories
    return Object.values(categories)
}

const editorCategories = computed(() => normalizeCategories(props.editor?.categories))

const initAnswers = () => {
    editorCategories.value.forEach((cat) => {
        ;(cat.questions || []).forEach((q) => {
            answers[q.id] = {
                question_id: q.id,
                value: q.value || '',
                supplementary_text: q.supplementary_text || '',
            }
        })
    })
}

initAnswers()

const sortedCategories = computed(() =>
    sortCategories(editorCategories.value)
        .map((cat) => ({
            ...cat,
            questions: filterRenderableQuestions(cat.questions || []),
        }))
        .filter((cat) => cat.questions.length)
)

const categoryNames = computed(() =>
    sortedCategories.value.map((c) => c.name)
)

const activeQuestions = computed(() => {
    const all = []
    sortedCategories.value.forEach((cat) => {
        cat.questions.forEach((q) => {
            if (!disabledIds.has(q.id)) all.push(q)
        })
    })
    return all
})

const totalQuestions = computed(() => activeQuestions.value.length)

const answeredCount = computed(() =>
    activeQuestions.value.filter((q) => {
        const a = answers[q.id]
        return a && (a.value || a.supplementary_text)
    }).length
)

const progress = computed(() => {
    if (!totalQuestions.value) return 0
    const p = Math.round((answeredCount.value / totalQuestions.value) * 100)
    return props.editor?.instance?.status === 'completed' ? 100 : Math.min(99, p)
})

const alertCount = computed(() => {
    let count = 0
    sortedCategories.value.forEach((cat) => {
        cat.questions.forEach((q) => {
            if (!disabledIds.has(q.id) && q.has_alert && answers[q.id]?.value === 'sim') count++
        })
    })
    return count
})

const scrollToCategory = (slug) => {
    const el = document.getElementById('anamnesis-cat-' + slug)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
        activeSlug.value = slug
    }
}

const scheduleSave = () => {
    if (isReadonly.value) return
    clearTimeout(saveTimer)
    saveTimer = setTimeout(saveDraft, 800)
}

const saveDraft = async () => {
    if (isReadonly.value) return
    saving.value = true
    try {
        await axios.put(
            route('patients.anamneses.answers', [props.patient.id, props.editor.instance.id]),
            { answers: Object.values(answers) }
        )
        savedAt.value = new Date()
    } finally {
        saving.value = false
    }
}

const complete = async () => {
    if (isReadonly.value) return
    saving.value = true
    try {
        await axios.put(
            route('patients.anamneses.answers', [props.patient.id, props.editor.instance.id]),
            { answers: Object.values(answers), complete: true }
        )
        router.visit(route('patients.show', { patient: props.patient.id, tab: 'anamneses' }))
    } finally {
        saving.value = false
    }
}

const toggleQuestion = async (questionId) => {
    if (isReadonly.value) return
    if (disabledIds.has(questionId)) {
        disabledIds.delete(questionId)
    } else {
        disabledIds.add(questionId)
    }
    try {
        await axios.post(
            route('patients.anamneses.toggle-question', [props.patient.id, props.editor.instance.id]),
            { question_id: questionId }
        )
    } catch {
        if (disabledIds.has(questionId)) {
            disabledIds.delete(questionId)
        } else {
            disabledIds.add(questionId)
        }
    }
}

const renameInstance = async (newName) => {
    try {
        await axios.patch(
            route('patients.anamneses.rename', [props.patient.id, props.editor.instance.id]),
            { name: newName }
        )
        router.reload({ only: ['editor'] })
    } catch (e) {
        toast.error(friendlyError(e, 'Não foi possível renomear a anamnese.'))
    }
}

const updateAnamnesisDate = async (dateIso) => {
    try {
        await axios.patch(
            route('patients.anamneses.update-date', [props.patient.id, props.editor.instance.id]),
            { anamnesis_date: dateIso }
        )
        router.reload({ only: ['editor'] })
    } catch (e) {
        toast.error(friendlyError(e, 'Não foi possível atualizar a data da anamnese.'))
    }
}

// Assinatura do paciente
const signError = ref('')
const submitPatientSignature = async (formData) => {
    signError.value = ''
    try {
        const { data } = await axios.post(
            route('patients.anamneses.sign', [props.patient.id, props.editor.instance.id]),
            formData
        )
        patientSignatureData.value = data.signature
        instanceStatus.value       = data.instance.status
        showSignModal.value        = false
        router.reload({ only: ['editor'] })
    } catch (e) {
        signError.value = friendlyError(e, 'Não foi possível registrar a assinatura. Tente novamente.')
    }
}

// Assinatura do dentista
const dentistSignError = ref('')
const submitDentistSignature = async (formData) => {
    dentistSignError.value = ''
    try {
        const { data } = await axios.post(
            route('patients.anamneses.sign-professional', [props.patient.id, props.editor.instance.id]),
            formData
        )
        dentistSignatureData.value = data.dentist_signature
        instanceStatus.value       = data.instance.status
        showDentistSignModal.value = false
        router.reload({ only: ['editor'] })
    } catch (e) {
        dentistSignError.value = friendlyError(e, 'Não foi possível registrar a assinatura. Tente novamente.')
    }
}

// Add instance-specific question
const addQuestionError = ref('')
const openAddModal = (categoryName) => {
    if (isReadonly.value) return
    addingToCategory.value = categoryName
    addQuestionError.value = ''
    showAddModal.value = true
}

const saveInstanceQuestion = async (formData) => {
    addQuestionError.value = ''
    try {
        const { data } = await axios.post(
            route('patients.anamneses.add-question', [props.patient.id, props.editor.instance.id]),
            formData
        )
        if (data.question?.id) {
            answers[data.question.id] = {
                question_id: data.question.id,
                value: '',
                supplementary_text: '',
            }
        }
        showAddModal.value = false
        router.reload({ only: ['editor'] })
    } catch (e) {
        addQuestionError.value = friendlyError(e, 'Não foi possível adicionar a pergunta.')
    }
}

// Dados do profissional logado para o modal
const authUser = computed(() => page.props.auth?.user || {})
const professionalCro = computed(() => {
    const cro = authUser.value.cro || ''
    const uf  = authUser.value.cro_uf || ''
    return cro ? (cro + (uf ? '/' + uf : '')) : ''
})

onMounted(() => {
    const sections = sortedCategories.value
        .map((c) => document.getElementById('anamnesis-cat-' + categorySlug(c.name)))
        .filter(Boolean)

    if (!sections.length) return

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
            if (visible?.target?.id) {
                activeSlug.value = visible.target.id.replace('anamnesis-cat-', '')
            }
        },
        { rootMargin: '-20% 0px -60% 0px', threshold: [0, 0.25, 0.5] }
    )

    sections.forEach((el) => observer.observe(el))
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto px-1 pb-24">
            <!-- Breadcrumb -->
            <div class="mb-3">
                <Link
                    :href="route('patients.show', { patient: patient.id, tab: 'anamneses' })"
                    class="text-[11px] text-slate-400 hover:text-slate-600 transition-colors"
                >← Ficha do paciente</Link>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ patient.nome }} {{ patient.sobrenome }}</p>
            </div>

            <!-- Aviso: paciente assinou (aguardando dentista) -->
            <div v-if="isPatientSigned && !isFullySigned" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-3.5 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                <div>
                    <p class="text-[13px] font-semibold text-amber-800">Paciente já assinou esta anamnese</p>
                    <p class="text-[11px] text-amber-600 mt-0.5">Documento bloqueado para edição. Aguardando assinatura do dentista.</p>
                </div>
            </div>

            <!-- Aviso: documento completamente assinado -->
            <div v-if="isFullySigned" class="mb-4 rounded-2xl border border-teal-200 bg-teal-50/60 px-5 py-3.5 flex items-center gap-3">
                <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div>
                    <p class="text-[13px] font-semibold text-teal-800">Documento completamente assinado</p>
                    <p class="text-[11px] text-teal-600 mt-0.5">Este documento é somente leitura. Ambas as assinaturas foram coletadas.</p>
                </div>
            </div>

            <!-- Summary bar -->
            <AnamnesisSummaryBar
                v-if="editor?.instance"
                :template-name="editor.instance.template_name"
                :custom-name="editor.instance.custom_name"
                :display-name="editor.instance.display_name"
                :progress="progress"
                :answered-count="answeredCount"
                :total-questions="totalQuestions"
                :alert-count="alertCount"
                :saving="saving"
                :saved-at="savedAt"
                :anamnesis-date="editor.instance.anamnesis_date"
                class="mb-4"
                @rename="renameInstance"
                @update-date="updateAnamnesisDate"
            />

            <!-- Mobile category tabs -->
            <div class="lg:hidden mb-3 -mx-1 overflow-x-auto">
                <div class="flex gap-1 px-1 pb-1 min-w-max">
                    <button
                        v-for="cat in sortedCategories"
                        :key="cat.name"
                        type="button"
                        @click="scrollToCategory(categorySlug(cat.name))"
                        class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium border transition-colors"
                        :class="activeSlug === categorySlug(cat.name)
                            ? 'bg-teal-50 border-teal-200 text-teal-700'
                            : 'bg-white border-slate-200 text-slate-500'"
                    >{{ cat.name.toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) }}</button>
                </div>
            </div>

            <div class="flex gap-5 items-start">
                <!-- Sidebar -->
                <aside class="hidden lg:block w-40 shrink-0 sticky top-20">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1.5 px-2.5">Categorias</p>
                    <AnamnesisSidebarNav
                        :categories="sortedCategories"
                        :active-slug="activeSlug"
                        :answers="answers"
                        :disabled-ids="disabledIds"
                        @navigate="scrollToCategory"
                    />
                </aside>

                <!-- Main content -->
                <div class="flex-1 min-w-0 space-y-4">

                    <!-- Dois cards de assinatura -->
                    <AnamnesisSignatureCard
                        :patient-signature="patientSignatureData"
                        :dentist-signature="dentistSignatureData"
                        :can-sign-patient="!isPatientSigned && !isFullySigned"
                        :can-sign-dentist="canSignDentist"
                        :on-sign-patient="() => { signError = ''; showSignModal = true }"
                        :on-sign-dentist="() => { dentistSignError = ''; showDentistSignModal = true }"
                    />

                    <AnamnesisCategoryCard
                        v-for="(category, idx) in sortedCategories"
                        :key="category.name + idx"
                        :category="category"
                        :answers="answers"
                        :disabled-ids="disabledIds"
                        :readonly="isReadonly"
                        @change="scheduleSave"
                        @toggle="toggleQuestion"
                        @add-question="openAddModal"
                    />
                </div>
            </div>

            <!-- Fixed bottom bar -->
            <div class="fixed bottom-0 inset-x-0 z-20 border-t border-slate-100 bg-white/95 backdrop-blur-sm">
                <div class="max-w-6xl mx-auto px-4 py-2 flex justify-between items-center gap-2">
                    <!-- Status indicator -->
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-semibold border"
                            :class="isFullySigned
                                ? 'bg-teal-50 text-teal-700 border-teal-100'
                                : isPatientSigned
                                    ? 'bg-amber-50 text-amber-700 border-amber-100'
                                    : editor?.instance?.status === 'completed'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                                        : 'bg-slate-50 text-slate-500 border-slate-200'"
                        >
                            {{
                                isFullySigned ? '✔ Documento completamente assinado'
                                : isPatientSigned ? '✎ Paciente assinou — aguardando dentista'
                                : editor?.instance?.status_label || 'Rascunho'
                            }}
                        </span>
                    </div>

                    <!-- Antes de qualquer assinatura: pode editar -->
                    <div v-if="!isReadonly" class="flex gap-2">
                        <button
                            v-if="!isPatientSigned"
                            @click="signError = ''; showSignModal = true"
                            class="rounded-lg border border-teal-200 px-3 py-1.5 text-[12px] font-medium text-teal-600 hover:bg-teal-50 transition-colors"
                        >✎ Assinar (paciente)</button>

                        <button
                            @click="saveDraft"
                            :disabled="saving"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors disabled:opacity-50"
                        >Salvar</button>
                        <button
                            @click="complete"
                            :disabled="saving"
                            class="rounded-lg bg-teal-600 px-4 py-1.5 text-[12px] font-medium text-white hover:bg-teal-700 transition-colors disabled:opacity-50"
                        >Concluir</button>
                    </div>

                    <!-- Paciente assinou, aguardando dentista -->
                    <div v-else-if="isPatientSigned && !isFullySigned" class="flex gap-2">
                        <a
                            :href="route('patients.anamneses.show', [patient.id, editor.instance.id])"
                            class="rounded-lg bg-emerald-600 px-4 py-1.5 text-[12px] font-semibold text-white hover:bg-emerald-700 transition-colors"
                        >✍ Visualizar e assinar</a>
                        <a
                            :href="route('patients.anamneses.pdf', [patient.id, editor.instance.id])"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                        >PDF</a>
                    </div>

                    <!-- Completamente assinado -->
                    <div v-else class="flex gap-2">
                        <a
                            :href="route('patients.anamneses.show', [patient.id, editor.instance.id])"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                        >📄 Visualizar documento</a>
                        <a
                            :href="route('patients.anamneses.pdf', [patient.id, editor.instance.id])"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                        >Baixar PDF</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal assinatura paciente -->
        <AnamnesisSignatureModal
            :show="showSignModal"
            :patient-name="patient.nome + ' ' + patient.sobrenome"
            :server-error="signError"
            @close="showSignModal = false"
            @signed="submitPatientSignature"
        />

        <!-- Modal assinatura dentista -->
        <AnamnesisDentistSignatureModal
            :show="showDentistSignModal"
            :professional-name="authUser.name || ''"
            :professional-cro="professionalCro"
            :server-error="dentistSignError"
            @close="showDentistSignModal = false"
            @signed="submitDentistSignature"
        />

        <!-- Add question modal -->
        <AnamnesisAddQuestionModal
            :show="showAddModal"
            :initial-category="addingToCategory"
            :categories="categoryNames"
            :server-error="addQuestionError"
            @close="showAddModal = false"
            @save="saveInstanceQuestion"
        />
    </AppLayout>
</template>
