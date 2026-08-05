<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputError from '@/Components/InputError.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const friendlyError = (err, fallback) => err?.response?.data?.message || err?.response?.data?.error || fallback

const props = defineProps({
    stats:               { type: Object, required: true },
    top_referrers:       { type: Array,  default: () => [] },
    conversions_by_plan: { type: Array,  default: () => [] },
    recent_clinics:      { type: Array,  default: () => [] },
    pending_payments:    { type: Array,  default: () => [] },
    settings:            { type: Object, required: true },
})

const settingsForm = ref({ ...props.settings })
const saving = ref(false)

const affiliateForm = ref({ name: '', email: '' })
const affiliateErrors = ref({})
const invitingAffiliate = ref(false)
const affiliateInviteLink = ref(null)

async function inviteAffiliate() {
    if (!affiliateForm.value.name.trim() || !affiliateForm.value.email.trim()) return
    invitingAffiliate.value = true
    affiliateErrors.value = {}
    try {
        const { data } = await window.axios.post(route('admin.affiliates.invite'), affiliateForm.value)
        affiliateInviteLink.value = data.invite_link
        affiliateForm.value = { name: '', email: '' }
    } catch (e) {
        const serverErrors = e.response?.data?.errors
        if (serverErrors) {
            affiliateErrors.value = Object.fromEntries(
                Object.entries(serverErrors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
            )
        } else {
            toast.error(friendlyError(e, 'Não foi possível gerar o convite.'))
        }
    } finally {
        invitingAffiliate.value = false
    }
}

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

async function saveSettings() {
    saving.value = true
    try {
        await window.axios.post(route('admin.settings'), settingsForm.value)
        toast.success('Configurações salvas com sucesso.')
    } catch (e) {
        toast.error(friendlyError(e, 'Não foi possível salvar as configurações.'))
    } finally {
        saving.value = false
    }
}

async function approvePayment(id) {
    try {
        await window.axios.post(route('admin.payments.approve', id))
        window.location.reload()
    } catch (e) {
        toast.error(friendlyError(e, 'Não foi possível aprovar o pagamento.'))
    }
}

async function rejectPayment(id) {
    try {
        await window.axios.post(route('admin.payments.reject', id))
        window.location.reload()
    } catch (e) {
        toast.error(friendlyError(e, 'Não foi possível recusar o pagamento.'))
    }
}
</script>

<template>
    <AdminLayout>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div v-for="(item, key) in [
                { label: 'Clínicas cadastradas', value: stats.total_clinics, color: 'text-slate-800' },
                { label: 'Em teste', value: stats.trialing, color: 'text-blue-600' },
                { label: 'Assinaturas ativas', value: stats.active_subscriptions, color: 'text-emerald-600' },
                { label: 'Receita mensal', value: formatMoney(stats.revenue_month), color: 'text-emerald-600' },
                { label: 'MRR', value: formatMoney(stats.mrr), color: 'text-violet-600' },
                { label: 'Receita anual', value: formatMoney(stats.revenue_year), color: 'text-blue-600' },
                { label: 'Churn', value: stats.churn + '%', color: 'text-red-500' },
                { label: 'Indicações geradas', value: stats.total_conversions, color: 'text-slate-800' },
                { label: 'Indicações pagas', value: stats.paid_conversions, color: 'text-emerald-600' },
                { label: 'Pagamentos pendentes', value: stats.pending_payments, color: 'text-amber-500' },
            ]" :key="key" class="rounded-2xl border bg-white p-4">
                <p class="text-[11px] text-slate-500 leading-tight">{{ item.label }}</p>
                <p class="mt-1 text-xl font-semibold" :class="item.color">{{ item.value }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Config indicações -->
            <div class="rounded-2xl border bg-white p-5">
                <h3 class="font-semibold text-slate-900 mb-4">Campanha de indicações</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-slate-500">Valor da recompensa (R$)</label>
                        <input v-model.number="settingsForm.reward_amount" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Desconto do convidado (R$)</label>
                        <input v-model.number="settingsForm.referred_discount_amount" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Saque mínimo (R$)</label>
                        <input v-model.number="settingsForm.minimum_withdraw" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Dias de teste</label>
                        <input v-model.number="settingsForm.trial_days" type="number" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="settingsForm.enabled" type="checkbox" class="rounded" />
                        Programa ativo
                    </label>
                    <button @click="saveSettings" :disabled="saving"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        Salvar configurações
                    </button>
                </div>
            </div>

            <!-- Ranking -->
            <div class="rounded-2xl border bg-white p-5">
                <h3 class="font-semibold text-slate-900 mb-4">Ranking de indicadores</h3>
                <div class="space-y-2">
                    <div v-for="(r, i) in top_referrers" :key="r.code"
                         class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-400 w-4">{{ i + 1 }}</span>
                            <span class="font-medium text-slate-800">{{ r.clinic_name }}</span>
                        </div>
                        <span class="text-emerald-600 font-medium">{{ r.conversions_count }} conv.</span>
                    </div>
                    <p v-if="!top_referrers.length" class="text-sm text-slate-400 text-center py-4">Sem dados ainda</p>
                </div>
            </div>

            <!-- Convidar afiliado -->
            <div class="rounded-2xl border bg-white p-5">
                <h3 class="font-semibold text-slate-900 mb-1">Convidar afiliado</h3>
                <p class="text-xs text-slate-500 mb-4">Contas Affiliate só acessam o programa de indicações — sem acesso a nenhuma tela clínica.</p>
                <div class="space-y-3">
                    <div>
                        <input v-model="affiliateForm.name" type="text" placeholder="Nome" class="w-full rounded-lg border px-3 py-2 text-sm" />
                        <InputError :message="affiliateErrors.name" />
                    </div>
                    <div>
                        <input v-model="affiliateForm.email" type="email" placeholder="E-mail" class="w-full rounded-lg border px-3 py-2 text-sm" />
                        <InputError :message="affiliateErrors.email" />
                    </div>
                    <button @click="inviteAffiliate" :disabled="invitingAffiliate"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                        Gerar convite
                    </button>
                    <div v-if="affiliateInviteLink" class="rounded-lg bg-slate-50 p-3 text-xs break-all">
                        <p class="text-slate-500 mb-1">Link do convite (envie manualmente):</p>
                        <code class="text-emerald-700">{{ affiliateInviteLink }}</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagamentos pendentes -->
        <div v-if="pending_payments.length" class="rounded-2xl border bg-white mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold text-slate-800">Pagamentos pendentes de aprovação</div>
            <div class="divide-y">
                <div v-for="p in pending_payments" :key="p.id" class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ p.clinic_name }}</p>
                        <p class="text-xs text-slate-400">{{ p.pix_type }} — {{ p.pix_key }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-emerald-600">{{ formatMoney(p.amount) }}</span>
                        <button @click="approvePayment(p.id)" class="rounded-lg bg-emerald-600 px-3 py-1 text-xs text-white hover:bg-emerald-700">Aprovar</button>
                        <button @click="rejectPayment(p.id)" class="rounded-lg border px-3 py-1 text-xs text-red-600 hover:bg-red-50">Recusar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversão por plano -->
        <div class="rounded-2xl border bg-white p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Conversão por plano</h3>
            <div class="flex flex-wrap gap-3">
                <div v-for="item in conversions_by_plan" :key="item.plan"
                     class="rounded-xl bg-slate-50 px-4 py-2 text-sm">
                    <span class="font-medium">{{ item.plan }}</span>
                    <span class="ml-2 text-emerald-600 font-semibold">{{ item.total }}</span>
                </div>
                <p v-if="!conversions_by_plan.length" class="text-sm text-slate-400">Sem conversões por plano</p>
            </div>
        </div>
    </AdminLayout>
</template>