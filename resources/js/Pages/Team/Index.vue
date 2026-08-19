<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, computed, nextTick, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/Navigation/PageHeader.vue'
import ScrollFadeX from '@/Components/UI/ScrollFadeX.vue'

const props = defineProps({
    members:         { type: Array,  default: () => [] },
    pendingInvites:  { type: Array,  default: () => [] },
    currentUserRole: { type: String, default: 'staff' },
    jobTitles:       { type: Array,  default: () => [] },
})

const toast = useToast()
const isAdmin = computed(() => ['owner', 'admin'].includes(props.currentUserRole))

// Vindo de outra tela (ex.: card Profissionais na ficha do paciente) com
// ?highlight={id} — destaca e rola até o membro correspondente.
const highlightedId = ref(null)

onMounted(() => {
    const id = Number(new URLSearchParams(window.location.search).get('highlight'))
    if (!id) return
    highlightedId.value = id
    nextTick(() => {
        document.getElementById(`member-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
    setTimeout(() => { highlightedId.value = null }, 4000)
})

// ═══════════════════════════════════════════════════════════════════════
// MÁQUINA DE ESTADOS DO MODAL DE CONVITE
// ═══════════════════════════════════════════════════════════════════════
// Etapas:
//   null             → fechado
//   'form'           → formulário de convite
//   'checking'       → verificando cenário (spinner)
//   'blocked'        → usuário já é membro (bloquear)
//   'pending'        → convite pendente — mostrar opções
//   'expired'        → convite expirado — mostrar opções
//   'confirm'        → resumo antes de enviar (NEW / CANCELLED / system_user)
//   'sending'        → enviando (spinner)
//   'dev_mode'       → MAIL_MAILER=log — copiar link/código
//   'smtp_error'     → falha SMTP — retry/copiar

const step         = ref(null)
const form         = ref({ name: '', email: '', job_title: '' })
const formErrors   = ref({})
const scenarioData = ref(null)   // { scenario, invite, system_user }
const actionInvite = ref(null)   // invite em foco para ações de pending/expired
const emailResult  = ref(null)   // resultado do dispatchEmail
const createdInvite = ref(null)  // convite criado/atualizado
const loading      = ref(false)
const copiedKey    = ref(null)   // 'link' | 'code' | null

function openModal() {
    form.value      = { name: '', email: '', job_title: '' }
    formErrors.value = {}
    scenarioData.value = null
    actionInvite.value = null
    emailResult.value  = null
    createdInvite.value = null
    step.value = 'form'
}

function closeModal() {
    step.value = null
}

function closeAndReload() {
    step.value = null
    router.reload({ only: ['members', 'pendingInvites'] })
}

// ── PASSO 1: Verificar cenário ao clicar em "Convidar" ────────────────
async function checkAndProceed() {
    formErrors.value = {}

    if (! form.value.name.trim()) {
        formErrors.value.name = 'Informe o nome completo.'
        return
    }
    if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
        formErrors.value.email = 'Informe um e-mail válido.'
        return
    }
    if (! form.value.job_title) {
        formErrors.value.job_title = 'Selecione um cargo.'
        return
    }

    step.value = 'checking'

    try {
        const { data } = await window.axios.post(route('invites.check'), {
            email: form.value.email,
        })

        scenarioData.value = data

        if (data.scenario === 'MEMBER') {
            step.value = 'blocked'
            return
        }
        if (data.scenario === 'PENDING') {
            actionInvite.value = data.invite
            step.value = 'pending'
            return
        }
        if (data.scenario === 'EXPIRED') {
            actionInvite.value = data.invite
            step.value = 'expired'
            return
        }
        // CANCELLED, NEW, system_user — prosseguir para confirmação
        step.value = 'confirm'
    } catch (err) {
        step.value = 'form'
        toast.error('Não foi possível verificar o e-mail. Tente novamente.')
    }
}

// ── PASSO 2: Enviar convite (após confirmação) ─────────────────────────
async function submitInvite() {
    step.value = 'sending'

    try {
        const { data } = await window.axios.post(route('invites.store'), form.value)
        createdInvite.value = data.invite
        handleEmailResult(data.email_result)
    } catch (err) {
        if (err.response?.status === 422) {
            const errors = err.response?.data?.errors ?? {}
            formErrors.value = errors
            step.value = 'form'
        } else {
            step.value = 'form'
            toast.error('Ocorreu um erro inesperado. Tente novamente.')
        }
    }
}

// ── Processar resultado do e-mail ─────────────────────────────────────
function handleEmailResult(result) {
    emailResult.value = result
    if (result.status === 'sent') {
        toast.success(`Convite enviado para ${createdInvite.value?.email ?? form.value.email}!`)
        closeAndReload()
    } else if (result.status === 'log_driver') {
        step.value = 'dev_mode'
    } else {
        step.value = 'smtp_error'
    }
}

// ── Ação: Reenviar e-mail (convite pendente) ──────────────────────────
async function actionResend() {
    loading.value = true
    try {
        const { data } = await window.axios.post(route('invites.resend', actionInvite.value.id))
        createdInvite.value = data.invite
        handleEmailResult(data.email_result)
    } catch {
        toast.error('Erro ao reenviar. Tente novamente.')
    } finally {
        loading.value = false
    }
}

// ── Ação: Gerar novo código ───────────────────────────────────────────
async function actionRegenerate() {
    loading.value = true
    try {
        const { data } = await window.axios.post(route('invites.regenerate', actionInvite.value.id))
        createdInvite.value = data.invite
        actionInvite.value  = data.invite
        handleEmailResult(data.email_result)
    } catch {
        toast.error('Erro ao gerar novo código. Tente novamente.')
    } finally {
        loading.value = false
    }
}

// ── Ação: Reativar convite expirado ──────────────────────────────────
async function actionReactivate() {
    loading.value = true
    try {
        const { data } = await window.axios.post(route('invites.reactivate', actionInvite.value.id))
        createdInvite.value = data.invite
        actionInvite.value  = data.invite
        handleEmailResult(data.email_result)
    } catch {
        toast.error('Erro ao reativar convite. Tente novamente.')
    } finally {
        loading.value = false
    }
}

// ── Ação: Cancelar convite (pendente) ────────────────────────────────
async function actionCancelInvite() {
    loading.value = true
    try {
        await window.axios.delete(route('invites.destroy', actionInvite.value.id))
        toast.success('Convite cancelado.')
        closeAndReload()
    } catch {
        toast.error('Erro ao cancelar convite.')
    } finally {
        loading.value = false
    }
}

// ── Ação: Cancelar convite da tabela (fora do modal) ─────────────────
async function cancelPendingInvite(invite) {
    if (! confirm(`Cancelar o convite para ${invite.email}?`)) return
    try {
        await window.axios.delete(route('invites.destroy', invite.id))
        toast.success('Convite cancelado.')
        router.reload({ only: ['pendingInvites'] })
    } catch {
        toast.error('Erro ao cancelar convite.')
    }
}

async function resendPendingInvite(invite) {
    try {
        const { data } = await window.axios.post(route('invites.resend', invite.id))
        if (data.email_result?.status === 'sent') {
            toast.success(`E-mail reenviado para ${invite.email}!`)
        } else if (data.email_result?.status === 'log_driver') {
            toast.info('Ambiente local: e-mail gravado no log. Copie o link manualmente.')
        } else {
            toast.error(`Falha ao reenviar: ${data.email_result?.error ?? 'erro desconhecido'}`)
        }
    } catch {
        toast.error('Erro ao reenviar convite.')
    }
}

// ── Ação: Gerar novo convite (a partir de expirado) ───────────────────
function actionCreateFresh() {
    // Mantém os dados do form, volta para confirmação
    step.value = 'confirm'
}

// ── Retry SMTP ────────────────────────────────────────────────────────
async function retrySmtp() {
    if (! createdInvite.value) { submitInvite(); return }
    step.value = 'sending'
    try {
        const { data } = await window.axios.post(route('invites.resend', createdInvite.value.id))
        createdInvite.value = data.invite
        handleEmailResult(data.email_result)
    } catch {
        step.value = 'smtp_error'
        toast.error('Erro ao tentar novamente.')
    }
}

// ── Clipboard ────────────────────────────────────────────────────────
function copyToClipboard(text, key) {
    navigator.clipboard.writeText(text).then(() => {
        copiedKey.value = key
        setTimeout(() => { copiedKey.value = null }, 2000)
    })
}

// ═══════════════════════════════════════════════════════════════════════
// GESTÃO DE MEMBROS
// ═══════════════════════════════════════════════════════════════════════
function deactivate(user) {
    if (! confirm(`Desativar ${user.name}? O acesso será suspenso imediatamente.`)) return
    router.post(route('team.deactivate', user.id), {}, {
        onSuccess: () => toast.success(`${user.name} foi desativado.`),
    })
}

function reactivate(user) {
    router.post(route('team.reactivate', user.id), {}, {
        onSuccess: () => toast.success(`${user.name} foi reativado.`),
    })
}

// ── Edição de cargo (somente owner/admin) ──────────────────────────────
const cargoTarget  = ref(null)
const cargoForm    = ref({ job_title: '' })
const cargoSaving  = ref(false)

function openCargoEdit(member) {
    cargoTarget.value = member
    cargoForm.value.job_title = member.job_title || ''
}

function closeCargoEdit() {
    cargoTarget.value = null
}

function saveCargo() {
    if (! cargoForm.value.job_title) return
    cargoSaving.value = true
    router.patch(route('team.update-role', cargoTarget.value.id), {
        role: cargoTarget.value.role,
        job_title: cargoForm.value.job_title,
    }, {
        preserveScroll: true,
        onSuccess: () => { toast.success('Cargo atualizado com sucesso.'); closeCargoEdit() },
        onError: () => toast.error('Erro ao atualizar cargo.'),
        onFinish: () => { cargoSaving.value = false },
    })
}

// ── Helpers ────────────────────────────────────────────────────────────
const ROLE_LABELS = { owner: 'Proprietário', admin: 'Administrador', professional: 'Profissional', staff: 'Equipe' }

function initials(name) {
    if (! name) return '?'
    return name.trim().split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

function formatDate(date) {
    if (! date) return '—'
    return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(date) {
    if (! date) return '—'
    return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function daysUntil(date) {
    if (! date) return 0
    return Math.max(0, Math.ceil((new Date(date) - new Date()) / 86400000))
}

const currentLink = computed(() => {
    const inv = createdInvite.value ?? actionInvite.value
    if (! inv?.token) return ''
    return window.location.origin + '/convites/' + inv.token
})

const currentCode = computed(() => (createdInvite.value ?? actionInvite.value)?.short_token ?? '')
</script>

<template>
    <AppLayout title="Gestão de Equipe">
        <template #pageHeader>
            <PageHeader
                title="Gestão de Equipe"
                :description="`${members.length} membro${members.length !== 1 ? 's' : ''} na clínica`"
            >
                <button
                    v-if="isAdmin"
                    @click="openModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3M13.5 19.5l-.397-1.191A4.5 4.5 0 0 0 8.893 15.75H8.25A4.5 4.5 0 0 0 3.75 20.25v.75a.75.75 0 0 0 .75.75h.75" />
                    </svg>
                    Convidar membro
                </button>
            </PageHeader>
        </template>

        <div class="max-w-6xl mx-auto px-4 py-8 space-y-8">

            <!-- Grid de membros -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="member in members"
                    :id="`member-${member.id}`"
                    :key="member.id"
                    class="bg-white rounded-xl border shadow-sm hover:shadow-md transition-all duration-150 p-5"
                    :class="[
                        member.status === 'inativo' ? 'opacity-60' : '',
                        member.id === highlightedId ? 'border-teal-400 ring-2 ring-teal-200' : 'border-slate-200',
                    ]"
                >
                    <div class="flex items-start gap-3 mb-4">
                        <div class="relative flex-shrink-0">
                            <img v-if="member.profile_photo_path" :src="`/storage/${member.profile_photo_path}`" :alt="member.name" class="w-12 h-12 rounded-full object-cover ring-2 ring-white shadow" />
                            <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center ring-2 ring-white shadow">
                                <span class="text-white font-bold text-sm">{{ initials(member.name) }}</span>
                            </div>
                            <span v-if="member.is_current_user" class="absolute -top-1 -right-1 bg-blue-600 text-white text-[9px] font-bold px-1 py-0.5 rounded-full leading-none">Eu</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900 truncate">{{ member.name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ member.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                            {{ member.job_title || ROLE_LABELS[member.role] || 'Equipe' }}
                        </span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="member.status === 'inativo' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-700'">
                            {{ member.status === 'inativo' ? 'Inativo' : 'Ativo' }}
                        </span>
                    </div>

                    <div class="space-y-1 text-xs text-slate-500 mb-4">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                            <span>{{ ROLE_LABELS[member.role] || member.role }}</span>
                        </div>
                        <div v-if="member.last_login_at" class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>Último acesso {{ formatDate(member.last_login_at) }}</span>
                        </div>
                    </div>

                    <div v-if="isAdmin && !member.is_current_user" class="flex gap-2 pt-3 border-t border-slate-100">
                        <button v-if="member.role !== 'owner'" @click="openCargoEdit(member)" class="flex-1 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg py-1.5 transition-colors font-medium">Editar cargo</button>
                        <button v-if="member.status !== 'inativo'" @click="deactivate(member)" class="flex-1 text-xs text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg py-1.5 transition-colors font-medium">Desativar</button>
                        <button v-else @click="reactivate(member)" class="flex-1 text-xs text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg py-1.5 transition-colors font-medium">Reativar</button>
                    </div>
                </div>
            </div>

            <!-- Convites pendentes -->
            <div v-if="pendingInvites.length > 0">
                <h2 class="text-base font-semibold text-slate-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                    Convites pendentes ({{ pendingInvites.length }})
                </h2>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <ScrollFadeX>
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50">
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Nome / E-mail</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Cargo</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Código</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Expira</th>
                                    <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="invite in pendingInvites" :key="invite.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800">{{ invite.name || invite.email }}</p>
                                        <p class="text-xs text-slate-500">{{ invite.email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs bg-blue-100 text-blue-700 font-medium px-2 py-0.5 rounded-full">{{ invite.job_title || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <code class="font-mono text-sm font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">{{ invite.short_token }}</code>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ daysUntil(invite.expires_at) }}d</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button @click="resendPendingInvite(invite)" class="text-xs text-blue-600 hover:text-blue-700 font-medium hover:underline">Reenviar</button>
                                            <button @click="cancelPendingInvite(invite)" class="text-xs text-red-500 hover:text-red-700 font-medium hover:underline">Cancelar</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </ScrollFadeX>
                </div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             MODAL DE CONVITE — MÁQUINA DE ESTADOS
             ═══════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-from-class="opacity-0" enter-to-class="opacity-100" leave-from-class="opacity-100" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150">
                <div v-if="step !== null" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeModal">
                    <Transition enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95" enter-active-class="transition-all duration-150" leave-active-class="transition-all duration-150">
                        <div v-if="step !== null" class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                            <!-- ── FORMULÁRIO ─────────────────────────────────── -->
                            <template v-if="step === 'form'">
                                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Convidar membro</h2>
                                        <p class="text-blue-200 text-xs mt-0.5">O convidado receberá um e-mail com o link de acesso</p>
                                    </div>
                                    <button @click="closeModal" class="text-white/70 hover:text-white">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome completo</label>
                                        <input v-model="form.name" type="text" placeholder="Ex: Dra. Ana Paula Silva" class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors" :class="formErrors.name ? 'border-red-400' : 'border-slate-300'" />
                                        <InputError :message="formErrors.name" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">E-mail</label>
                                        <input v-model="form.email" type="email" placeholder="colaborador@email.com" class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors" :class="formErrors.email ? 'border-red-400' : 'border-slate-300'" />
                                        <InputError :message="formErrors.email" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Cargo</label>
                                        <select v-model="form.job_title" class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors bg-white" :class="formErrors.job_title ? 'border-red-400' : 'border-slate-300'">
                                            <option value="">Selecione um cargo...</option>
                                            <option v-for="title in jobTitles" :key="title" :value="title">{{ title }}</option>
                                        </select>
                                        <InputError :message="formErrors.job_title" />
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button @click="closeModal" class="flex-1 border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition-colors">Cancelar</button>
                                        <button @click="checkAndProceed" class="flex-1 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors">Verificar e convidar</button>
                                    </div>
                                </div>
                            </template>

                            <!-- ── VERIFICANDO ─────────────────────────────────── -->
                            <template v-else-if="step === 'checking' || step === 'sending'">
                                <div class="p-10 text-center">
                                    <svg class="w-10 h-10 mx-auto text-blue-500 animate-spin mb-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                    </svg>
                                    <p class="text-slate-600 font-medium">{{ step === 'checking' ? 'Verificando disponibilidade...' : 'Enviando convite...' }}</p>
                                    <p class="text-slate-400 text-sm mt-1">{{ step === 'checking' ? 'Consultando a base de dados' : 'Preparando e-mail de convite' }}</p>
                                </div>
                            </template>

                            <!-- ── CENÁRIO A: JÁ É MEMBRO ─────────────────────── -->
                            <template v-else-if="step === 'blocked'">
                                <div class="p-6 text-center">
                                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-7 h-7 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    </div>
                                    <h3 class="text-lg font-bold text-slate-900 mb-2">Este usuário já faz parte da equipe.</h3>
                                    <p class="text-sm text-slate-500 mb-2">O endereço <strong class="text-slate-700">{{ form.email }}</strong> já está vinculado a esta clínica.</p>
                                    <p v-if="scenarioData?.system_user?.name" class="text-sm text-slate-500 mb-6">Membro: <strong>{{ scenarioData.system_user.name }}</strong> — {{ scenarioData.system_user.job_title || 'sem cargo definido' }}</p>
                                    <p v-else class="mb-6"></p>
                                    <p class="text-xs text-slate-400 mb-6">Nenhum novo convite será criado.</p>
                                    <div class="flex gap-3">
                                        <button @click="closeModal" class="flex-1 border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition-colors">Fechar</button>
                                        <a :href="route('team.index')" class="flex-1 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors text-center">Ir para Equipe</a>
                                    </div>
                                </div>
                            </template>

                            <!-- ── CENÁRIO C: CONVITE PENDENTE ─────────────────── -->
                            <template v-else-if="step === 'pending'">
                                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5 flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Já existe um convite pendente</h2>
                                        <p class="text-amber-100 text-xs mt-0.5">Escolha o que fazer com o convite existente</p>
                                    </div>
                                    <button @click="closeModal" class="text-white/70 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                                </div>
                                <div class="p-6 space-y-4">
                                    <!-- Info do convite existente -->
                                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-slate-500">Destinatário</span><span class="font-medium text-slate-800">{{ actionInvite?.name || actionInvite?.email }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Código</span><code class="font-mono font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">{{ actionInvite?.short_token }}</code></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Enviado em</span><span class="text-slate-700">{{ formatDateTime(actionInvite?.created_at) }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Expira em</span><span class="font-medium text-slate-800">{{ daysUntil(actionInvite?.expires_at) }} dias</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Convidado por</span><span class="text-slate-700">{{ actionInvite?.invited_by || '—' }}</span></div>
                                    </div>

                                    <p v-if="scenarioData?.system_user" class="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                                        ℹ️ Este e-mail já tem uma conta no Wildental ({{ scenarioData.system_user.name }}). Ao aceitar, ele será associado a esta clínica sem criar nova conta.
                                    </p>

                                    <!-- Botões de ação -->
                                    <div class="space-y-2 pt-1">
                                        <button @click="actionResend" :disabled="loading" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-60">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                            Reenviar e-mail com mesmo código
                                        </button>
                                        <button @click="actionRegenerate" :disabled="loading" class="w-full flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-60">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                            Gerar novo código e reenviar
                                        </button>
                                        <button @click="actionCancelInvite" :disabled="loading" class="w-full flex items-center justify-center gap-2 border border-red-200 text-red-600 text-sm font-medium py-2.5 rounded-lg hover:bg-red-50 transition-colors disabled:opacity-60">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                            Cancelar convite
                                        </button>
                                        <button @click="closeModal" class="w-full text-slate-500 text-sm py-2 hover:text-slate-700 transition-colors">Fechar sem alterar</button>
                                    </div>
                                </div>
                            </template>

                            <!-- ── CENÁRIO D: CONVITE EXPIRADO ─────────────────── -->
                            <template v-else-if="step === 'expired'">
                                <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-6 py-5 flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-bold text-white">O convite anterior expirou</h2>
                                        <p class="text-slate-300 text-xs mt-0.5">Reative ou crie um novo convite</p>
                                    </div>
                                    <button @click="closeModal" class="text-white/70 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-slate-500">Destinatário</span><span class="font-medium text-slate-700">{{ actionInvite?.name || actionInvite?.email }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Código anterior</span><code class="font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded line-through">{{ actionInvite?.short_token }}</code></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Expirou em</span><span class="text-red-600 font-medium">{{ formatDateTime(actionInvite?.expires_at) }}</span></div>
                                    </div>

                                    <p v-if="scenarioData?.system_user" class="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                                        ℹ️ Este e-mail já tem uma conta no Wildental ({{ scenarioData.system_user.name }}). Um novo convite apenas associará esse usuário a esta clínica.
                                    </p>

                                    <div class="space-y-2 pt-1">
                                        <button @click="actionReactivate" :disabled="loading" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-60">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                            Reativar com novo código e reenviar
                                        </button>
                                        <button @click="actionCreateFresh" :disabled="loading" class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-700 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-60">
                                            Criar novo convite com dados atualizados
                                        </button>
                                        <button @click="closeModal" class="w-full text-slate-500 text-sm py-2 hover:text-slate-700 transition-colors">Cancelar</button>
                                    </div>
                                </div>
                            </template>

                            <!-- ── CONFIRMAR ENVIO (NEW / CANCELLED / SYSTEM_USER) -->
                            <template v-else-if="step === 'confirm'">
                                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Confirmar convite</h2>
                                        <p class="text-blue-200 text-xs mt-0.5">Revise os dados antes de enviar</p>
                                    </div>
                                    <button @click="closeModal" class="text-white/70 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-slate-500">Nome</span><span class="font-semibold text-slate-800">{{ form.name }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">E-mail</span><span class="font-medium text-slate-700">{{ form.email }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Cargo</span><span class="font-medium text-slate-700">{{ form.job_title }}</span></div>
                                        <div class="flex justify-between"><span class="text-slate-500">Validade</span><span class="text-slate-700">7 dias após o envio</span></div>
                                    </div>

                                    <!-- Info: usuário já existe no sistema -->
                                    <div v-if="scenarioData?.system_user" class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 text-xs text-indigo-700 space-y-1">
                                        <p class="font-semibold">ℹ️ Usuário já cadastrado no Wildental</p>
                                        <p>Este e-mail pertence a <strong>{{ scenarioData.system_user.name }}</strong>, que já possui conta no sistema. Ao aceitar o convite, ele será associado a esta clínica sem criar uma nova conta. Senha e dados pessoais serão mantidos.</p>
                                    </div>

                                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-700">
                                        Um e-mail com um código único será enviado. O convidado terá <strong>7 dias</strong> para aceitar.
                                    </div>

                                    <div class="flex gap-3 pt-1">
                                        <button @click="step = 'form'" class="flex-1 border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition-colors">Editar dados</button>
                                        <button @click="submitInvite" class="flex-1 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors">Enviar convite</button>
                                    </div>
                                </div>
                            </template>

                            <!-- ── RESULTADO: AMBIENTE DE DESENVOLVIMENTO ────────── -->
                            <template v-else-if="step === 'dev_mode'">
                                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-2xl">🛠️</span>
                                        <h2 class="text-lg font-bold text-white">Convite criado!</h2>
                                    </div>
                                    <p class="text-amber-100 text-xs">Você está em ambiente de desenvolvimento</p>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 space-y-1">
                                        <p class="font-semibold">MAIL_MAILER=log está ativo</p>
                                        <p>O convite foi criado com sucesso, mas <strong>nenhum e-mail foi enviado</strong> ao destinatário. O HTML do e-mail foi gravado em <code class="bg-amber-100 px-1 rounded">storage/logs/laravel.log</code>.</p>
                                        <p class="mt-2">Para enviar e-mails reais, configure <code class="bg-amber-100 px-1 rounded">MAIL_MAILER=smtp</code> no seu <code class="bg-amber-100 px-1 rounded">.env</code>.</p>
                                    </div>

                                    <!-- Código de acesso -->
                                    <div class="bg-white border-2 border-dashed border-blue-300 rounded-xl p-4 text-center">
                                        <p class="text-xs text-blue-500 font-semibold uppercase tracking-widest mb-2">Código do convite</p>
                                        <code class="text-3xl font-extrabold text-blue-800 tracking-[0.25em] font-mono">{{ currentCode }}</code>
                                    </div>

                                    <!-- Ações -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <button @click="copyToClipboard(currentLink, 'link')" class="flex items-center justify-center gap-2 border border-slate-200 rounded-lg py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                                            {{ copiedKey === 'link' ? '✓ Copiado!' : 'Copiar link' }}
                                        </button>
                                        <button @click="copyToClipboard(currentCode, 'code')" class="flex items-center justify-center gap-2 border border-slate-200 rounded-lg py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                                            {{ copiedKey === 'code' ? '✓ Copiado!' : 'Copiar código' }}
                                        </button>
                                    </div>

                                    <p class="text-xs text-slate-400 text-center break-all">{{ currentLink }}</p>

                                    <button @click="closeAndReload" class="w-full bg-slate-800 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-slate-900 transition-colors">Fechar e atualizar</button>
                                </div>
                            </template>

                            <!-- ── RESULTADO: FALHA SMTP ──────────────────────────── -->
                            <template v-else-if="step === 'smtp_error'">
                                <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-5">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-2xl">⚠️</span>
                                        <h2 class="text-lg font-bold text-white">Convite criado, mas e-mail falhou</h2>
                                    </div>
                                    <p class="text-red-100 text-xs">O convite existe no sistema — apenas o e-mail não foi entregue</p>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
                                        <p class="font-semibold mb-1">Não foi possível enviar o e-mail</p>
                                        <p class="text-xs text-red-600 font-mono break-all">{{ emailResult?.error }}</p>
                                    </div>

                                    <div class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 text-center">
                                        <p class="text-xs text-slate-400 font-semibold uppercase tracking-widest mb-2">Código do convite</p>
                                        <code class="text-3xl font-extrabold text-slate-800 tracking-[0.25em] font-mono">{{ currentCode }}</code>
                                    </div>

                                    <div class="space-y-2">
                                        <button @click="retrySmtp" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                            Tentar novamente
                                        </button>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button @click="copyToClipboard(currentLink, 'link')" class="flex items-center justify-center gap-1.5 border border-slate-200 rounded-lg py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                                {{ copiedKey === 'link' ? '✓ Copiado!' : '🔗 Copiar link' }}
                                            </button>
                                            <button @click="copyToClipboard(currentCode, 'code')" class="flex items-center justify-center gap-1.5 border border-slate-200 rounded-lg py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                                {{ copiedKey === 'code' ? '✓ Copiado!' : '📋 Copiar código' }}
                                            </button>
                                        </div>
                                        <button @click="closeAndReload" class="w-full text-slate-500 text-sm py-2 hover:text-slate-700 transition-colors">Fechar</button>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════════
             MODAL DE EDIÇÃO DE CARGO
             ═══════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-from-class="opacity-0" enter-to-class="opacity-100" leave-from-class="opacity-100" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150">
                <div v-if="cargoTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeCargoEdit">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white">Editar cargo</h2>
                                <p class="text-blue-200 text-xs mt-0.5">{{ cargoTarget?.name }}</p>
                            </div>
                            <button @click="closeCargoEdit" class="text-white/70 hover:text-white">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Cargo</label>
                                <select v-model="cargoForm.job_title" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors bg-white">
                                    <option value="">Selecione um cargo...</option>
                                    <option v-for="title in jobTitles" :key="title" :value="title">{{ title }}</option>
                                </select>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button @click="closeCargoEdit" class="flex-1 border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition-colors">Cancelar</button>
                                <button @click="saveCargo" :disabled="cargoSaving || !cargoForm.job_title" class="flex-1 bg-blue-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-60">
                                    {{ cargoSaving ? 'Salvando...' : 'Salvar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>
