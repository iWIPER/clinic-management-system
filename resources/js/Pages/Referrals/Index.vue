<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'
import QRCode from 'qrcode'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/Layouts/AppLayout.vue'
import AffiliateLayout from '@/Layouts/AffiliateLayout.vue'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const page = usePage()
// Contas Affiliate reaproveitam este mesmo dashboard — só troca o "shell" e as rotas.
const isAffiliate = computed(() => !!page.props.auth?.isAffiliate)
const layout = computed(() => isAffiliate.value ? AffiliateLayout : AppLayout)
const routes = computed(() => isAffiliate.value
    ? { index: 'affiliate.dashboard', pix: 'affiliate.pix', withdraw: 'affiliate.withdraw', export: 'affiliate.export', show: 'affiliate.show' }
    : { index: 'referrals.index', pix: 'referrals.pix', withdraw: 'referrals.withdraw', export: 'referrals.export', show: 'referrals.show' })

const props = defineProps({
    referral:      { type: Object, required: true },
    wallet:        { type: Object, required: true },
    stats:         { type: Object, required: true },
    conversions:   { type: Object, default: () => ({ data: [] }) },
    filters:       { type: Object, default: () => ({}) },
    monthly_chart: { type: Array,  default: () => [] },
    transactions:  { type: Array,  default: () => [] },
    payments:      { type: Array,  default: () => [] },
    settings:      { type: Object, required: true },
})

const toast = useToast()
const activeTab = ref('dashboard')
const copied = ref(false)
const showPixModal = ref(false)
const showWithdrawModal = ref(false)
const pixForm = ref({ pix_type: props.wallet.pix_type || 'cpf', pix_key: props.wallet.pix_key || '' })
const withdrawAmount = ref(props.wallet.balance)
const savingPix = ref(false)
const withdrawing = ref(false)
const selectedConversion = ref(null)
const qrDataUrl = ref(null)

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')

const tabs = [
    { id: 'dashboard', label: 'Dashboard' },
    { id: 'wallet',    label: 'Carteira' },
    { id: 'history',   label: 'Histórico' },
]

const TIMELINE_STEPS = [
    { key: 'invite',    label: 'Convite enviado' },
    { key: 'signup',    label: 'Cadastro criado' },
    { key: 'trial',     label: 'Teste iniciado' },
    { key: 'plan',      label: 'Plano assinado' },
    { key: 'trial_end', label: 'Período de teste encerrado' },
    { key: 'payment',   label: 'Pagamento confirmado' },
    { key: 'bonus',     label: 'Bônus liberado' },
    { key: 'paid',      label: 'Pagamento realizado' },
]

const STATUS_STYLES = {
    testing:            'bg-blue-50 text-blue-700 border-blue-200',
    awaiting_payment:   'bg-amber-50 text-amber-700 border-amber-200',
    payment_confirmed:  'bg-indigo-50 text-indigo-700 border-indigo-200',
    eligible:           'bg-emerald-50 text-emerald-700 border-emerald-200',
    paid:               'bg-slate-50 text-slate-700 border-slate-200',
    cancelled:          'bg-red-50 text-red-700 border-red-200',
    expired:            'bg-slate-50 text-slate-500 border-slate-200',
    refunded:           'bg-rose-50 text-rose-700 border-rose-200',
    under_review:       'bg-purple-50 text-purple-700 border-purple-200',
}

const STATUS_OPTIONS = [
    { value: 'testing',            label: 'Em Trial' },
    { value: 'awaiting_payment',   label: 'Em carência' },
    { value: 'payment_confirmed',  label: 'Pagamento confirmado' },
    { value: 'eligible',           label: 'Liberado' },
    { value: 'paid',               label: 'Pago' },
    { value: 'cancelled',          label: 'Anulado' },
    { value: 'expired',            label: 'Expirado' },
    { value: 'refunded',           label: 'Estornado' },
    { value: 'under_review',       label: 'Em revisão' },
]

const PIX_TYPES = [
    { value: 'cpf',    label: 'CPF' },
    { value: 'cnpj',   label: 'CNPJ' },
    { value: 'email',  label: 'E-mail' },
    { value: 'phone',  label: 'Telefone' },
    { value: 'random', label: 'Chave aleatória' },
]

function formatMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(iso) {
    if (! iso) return '—'
    return new Date(iso).toLocaleDateString('pt-BR')
}

function conversionRate() {
    const refs = props.stats.referrals || 0
    const conv = props.stats.conversions || 0
    if (! refs) return '0%'
    return Math.round((conv / refs) * 100) + '%'
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.referral.link)
        copied.value = true
        toast.success('Link copiado!')
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        toast.error('Não foi possível copiar o link.')
    }
}

const shareText = computed(() => `Ganhe dias grátis e desconto no CliniFlow usando meu link: ${props.referral.link}`)
const whatsappUrl  = computed(() => `https://wa.me/?text=${encodeURIComponent(shareText.value)}`)
const telegramUrl  = computed(() => `https://t.me/share/url?url=${encodeURIComponent(props.referral.link)}&text=${encodeURIComponent('Ganhe dias grátis e desconto no CliniFlow')}`)
const emailUrl     = computed(() => `mailto:?subject=${encodeURIComponent('Conheça o CliniFlow')}&body=${encodeURIComponent(shareText.value)}`)

onMounted(async () => {
    try {
        qrDataUrl.value = await QRCode.toDataURL(props.referral.link, { width: 180, margin: 1 })
    } catch {
        qrDataUrl.value = null
    }
})

async function savePix() {
    if (! pixForm.value.pix_key.trim()) {
        toast.error('Informe a chave PIX.')
        return
    }
    savingPix.value = true
    try {
        await window.axios.post(route(routes.value.pix), pixForm.value)
        toast.success('Chave PIX cadastrada!')
        showPixModal.value = false
        router.reload({ only: ['wallet'] })
    } catch (err) {
        toast.error(err.response?.data?.message || 'Erro ao salvar PIX.')
    } finally {
        savingPix.value = false
    }
}

async function requestWithdraw() {
    withdrawing.value = true
    try {
        await window.axios.post(route(routes.value.withdraw), { amount: withdrawAmount.value })
        toast.success('Saque solicitado! Você será avisado quando for processado.')
        showWithdrawModal.value = false
        router.reload({ only: ['wallet', 'payments', 'stats'] })
    } catch (err) {
        toast.error(err.response?.data?.message || 'Erro ao solicitar saque.')
    } finally {
        withdrawing.value = false
    }
}

function timelineProgress(status) {
    const order = ['testing', 'awaiting_payment', 'payment_confirmed', 'eligible', 'paid']
    const idx = order.indexOf(status)
    return idx >= 0 ? idx + 2 : 1
}

function openConversionDetail(conv) {
    selectedConversion.value = conv
}

function applyFilters() {
    router.get(route(routes.value.index), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true, only: ['conversions', 'filters'] })
}

let searchDebounce = null
watch(search, () => {
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(applyFilters, 350)
})

function filterByStatus(status) {
    statusFilter.value = statusFilter.value === status ? '' : status
    applyFilters()
}

const exportUrl = computed(() => {
    const params = new URLSearchParams()
    if (search.value) params.set('search', search.value)
    if (statusFilter.value) params.set('status', statusFilter.value)
    const qs = params.toString()
    return route(routes.value.export) + (qs ? `?${qs}` : '')
})

const chartData = computed(() => ({
    labels: props.monthly_chart.map(m => m.label),
    datasets: [{
        label: 'Conversões',
        backgroundColor: '#059669',
        borderRadius: 6,
        data: props.monthly_chart.map(m => m.total),
    }],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
}
</script>

<template>
    <component :is="layout">
        <div class="mb-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Programa de Indicações</h1>
                    <p class="mt-1 text-sm text-slate-500">Indique clínicas e ganhe bonificações em dinheiro</p>
                </div>
                <div class="flex items-center gap-2 rounded-xl border bg-white px-4 py-2.5">
                    <div class="text-xs text-slate-500">Seu código</div>
                    <code class="font-mono text-sm font-semibold text-emerald-700">{{ referral.code }}</code>
                </div>
            </div>
        </div>

        <!-- Saldo negativo -->
        <div v-if="stats.negative_balance > 0" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            ⚠ Sua carteira está com saldo negativo de <strong>{{ formatMoney(stats.negative_balance) }}</strong> devido a um estorno.
            Esse valor será descontado de próximas liberações.
        </div>

        <!-- Link exclusivo + compartilhamento -->
        <div class="mb-6 rounded-2xl border bg-gradient-to-r from-emerald-50 to-teal-50 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Link exclusivo permanente</p>
                    <p class="mt-1 font-mono text-sm text-slate-700 break-all">{{ referral.link }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button @click="copyLink"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
                            {{ copied ? 'Copiado!' : 'Copiar link' }}
                        </button>
                        <a :href="whatsappUrl" target="_blank" rel="noopener"
                           class="rounded-xl border bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            WhatsApp
                        </a>
                        <a :href="telegramUrl" target="_blank" rel="noopener"
                           class="rounded-xl border bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Telegram
                        </a>
                        <a :href="emailUrl"
                           class="rounded-xl border bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            E-mail
                        </a>
                    </div>
                </div>

                <div v-if="qrDataUrl" class="shrink-0 rounded-xl border bg-white p-2">
                    <img :src="qrDataUrl" alt="QR Code do link de indicação" class="h-28 w-28" />
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6 flex gap-1 rounded-xl border bg-white p-1">
            <button v-for="tab in tabs" :key="tab.id"
                    @click="activeTab = tab.id"
                    class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab.id
                        ? 'bg-emerald-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-50'">
                {{ tab.label }}
            </button>
        </div>

        <!-- ══ DASHBOARD ══ -->
        <div v-show="activeTab === 'dashboard'" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Saldo disponível</p>
                    <p class="mt-1 text-3xl font-semibold" :class="stats.balance < 0 ? 'text-red-600' : 'text-emerald-600'">{{ formatMoney(stats.balance) }}</p>
                </div>
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Saldo pendente</p>
                    <p class="mt-1 text-3xl font-semibold text-amber-500">{{ formatMoney(stats.pending_balance) }}</p>
                </div>
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Total recebido</p>
                    <p class="mt-1 text-3xl font-semibold text-blue-600">{{ formatMoney(stats.total_earned) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border bg-white p-4 text-center">
                    <p class="text-2xl font-semibold text-slate-800">{{ stats.clicks }}</p>
                    <p class="text-xs text-slate-500 mt-1">Total de convites</p>
                </div>
                <div class="rounded-2xl border bg-white p-4 text-center">
                    <p class="text-2xl font-semibold text-slate-800">{{ stats.referrals }}</p>
                    <p class="text-xs text-slate-500 mt-1">Cadastros</p>
                </div>
                <div class="rounded-2xl border bg-white p-4 text-center">
                    <p class="text-2xl font-semibold text-slate-800">{{ stats.trials_active }}</p>
                    <p class="text-xs text-slate-500 mt-1">Trials ativos</p>
                </div>
                <div class="rounded-2xl border bg-white p-4 text-center">
                    <p class="text-2xl font-semibold text-slate-800">{{ stats.subscriptions }}</p>
                    <p class="text-xs text-slate-500 mt-1">Assinaturas</p>
                </div>
            </div>

            <div class="rounded-2xl border bg-white p-4 text-center sm:w-64">
                <p class="text-2xl font-semibold text-slate-800">{{ conversionRate() }}</p>
                <p class="text-xs text-slate-500 mt-1">Taxa de conversão</p>
            </div>

            <!-- Gráfico de conversões por mês -->
            <div class="rounded-2xl border bg-white p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">Conversões por mês</h3>
                <div style="height: 220px">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Como funciona -->
            <div class="rounded-2xl border bg-white p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Como funciona?</h3>
                <div class="space-y-3 text-sm text-slate-600">
                    <p>Compartilhe seu link exclusivo.</p>
                    <p>Quando outra clínica criar uma conta usando seu link:</p>
                    <p class="flex items-center gap-2 text-emerald-700 font-medium">
                        <span class="text-emerald-500">✔</span> ganha {{ settings.trial_days }} dias de teste
                    </p>
                    <p>Caso ela assine um plano pago e permaneça ativa após o período de teste, você recebe</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ formatMoney(settings.reward_amount) }}</p>
                    <p class="text-slate-500">na sua carteira CliniFlow.</p>
                    <p class="pt-2 border-t text-slate-500">Você pode indicar quantas clínicas desejar. Não existe limite.</p>
                </div>
            </div>

            <!-- Linha do tempo -->
            <div class="rounded-2xl border bg-white p-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-5">Linha do tempo do programa</h3>
                <div class="flex flex-col items-center gap-1">
                    <template v-for="(step, i) in TIMELINE_STEPS" :key="step.key">
                        <div class="flex items-center gap-3 w-full max-w-xs">
                            <div class="h-8 w-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold shrink-0">
                                {{ i + 1 }}
                            </div>
                            <span class="text-sm text-slate-700">{{ step.label }}</span>
                        </div>
                        <div v-if="i < TIMELINE_STEPS.length - 1" class="text-slate-300 text-lg py-0.5">↓</div>
                    </template>
                </div>
            </div>

            <!-- Regras -->
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5">
                <h4 class="text-sm font-semibold text-emerald-800 mb-3">Regras de liberação do bônus</h4>
                <ul class="space-y-1.5 text-sm text-emerald-700">
                    <li class="flex items-center gap-2"><span>✔</span> Assinatura ativa</li>
                    <li class="flex items-center gap-2"><span>✔</span> Pagamento confirmado</li>
                    <li class="flex items-center gap-2"><span>✔</span> Período de teste encerrado</li>
                    <li class="flex items-center gap-2"><span>✔</span> Assinatura não cancelada</li>
                    <li class="flex items-center gap-2"><span>✔</span> Sem reembolso</li>
                </ul>
            </div>
        </div>

        <!-- ══ CARTEIRA ══ -->
        <div v-show="activeTab === 'wallet'" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Saldo disponível</p>
                    <p class="text-3xl font-semibold mt-1" :class="wallet.balance < 0 ? 'text-red-600' : 'text-emerald-600'">{{ formatMoney(wallet.balance) }}</p>
                </div>
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Saldo pendente</p>
                    <p class="text-3xl font-semibold text-amber-500 mt-1">{{ formatMoney(wallet.pending_balance) }}</p>
                </div>
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Saques realizados</p>
                    <p class="text-2xl font-semibold text-slate-800 mt-1">{{ formatMoney(wallet.total_withdrawn) }}</p>
                </div>
                <div class="rounded-2xl border bg-white p-5">
                    <p class="text-sm text-slate-500">Valor mínimo para saque</p>
                    <p class="text-2xl font-semibold text-slate-800 mt-1">{{ formatMoney(settings.minimum_withdraw) }}</p>
                </div>
            </div>

            <div class="rounded-2xl border bg-white p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">Solicitar saque</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Próximo lote de pagamentos: <strong>{{ formatDate(wallet.next_withdrawal_at) }}</strong>
                        </p>
                    </div>
                    <button @click="showWithdrawModal = true"
                            :disabled="!wallet.pix_key || wallet.balance < settings.minimum_withdraw"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        Solicitar saque
                    </button>
                </div>
                <p v-if="!wallet.pix_key" class="mt-2 text-xs text-amber-600">Cadastre uma chave PIX para poder solicitar saques.</p>
                <p v-else-if="wallet.balance < settings.minimum_withdraw" class="mt-2 text-xs text-slate-400">
                    Saldo abaixo do mínimo de {{ formatMoney(settings.minimum_withdraw) }} para saque.
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-slate-900">PIX cadastrado</h3>
                    <button @click="showPixModal = true"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        {{ wallet.pix_key ? 'Alterar PIX' : 'Cadastrar PIX' }}
                    </button>
                </div>
                <div v-if="wallet.pix_key" class="rounded-xl bg-slate-50 p-4 text-sm">
                    <p class="text-slate-500">Tipo: <span class="font-medium text-slate-800 uppercase">{{ wallet.pix_type }}</span></p>
                    <p class="text-slate-500 mt-1">Chave: <span class="font-mono font-medium text-slate-800">{{ wallet.pix_key }}</span></p>
                </div>
                <p v-else class="text-sm text-slate-400">Nenhuma chave PIX cadastrada. Cadastre para receber pagamentos futuros.</p>
            </div>

            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="px-5 py-3 border-b">
                    <h3 class="font-medium text-slate-800">Histórico de saques</h3>
                </div>
                <div v-if="payments.length" class="divide-y">
                    <div v-for="p in payments" :key="p.id" class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-800">{{ formatMoney(p.amount) }}</p>
                            <p class="text-xs text-slate-400">{{ formatDate(p.requested_at) }}</p>
                        </div>
                        <span class="rounded-full border px-2 py-0.5 text-xs font-medium"
                              :class="p.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                            {{ p.status }}
                        </span>
                    </div>
                </div>
                <p v-else class="px-5 py-8 text-center text-sm text-slate-400">Nenhum saque solicitado ainda.</p>
            </div>
        </div>

        <!-- ══ HISTÓRICO ══ -->
        <div v-show="activeTab === 'history'" class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button v-for="opt in STATUS_OPTIONS" :key="opt.value"
                            @click="filterByStatus(opt.value)"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium"
                            :class="statusFilter === opt.value ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600 hover:bg-slate-50'">
                        {{ opt.label }}
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="search" type="text" placeholder="Buscar por nome..."
                           class="rounded-lg border px-3 py-2 text-sm w-48" />
                    <a :href="exportUrl"
                       class="shrink-0 rounded-lg border bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        Exportar CSV
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">Indicado</th>
                                <th class="px-4 py-3">Plano</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!conversions.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma indicação encontrada.</td>
                            </tr>
                            <tr v-for="conv in conversions.data" :key="conv.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ conv.clinic_name }}</p>
                                    <p v-if="conv.clinic_city" class="text-xs text-slate-400">{{ conv.clinic_city }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ conv.plan_name }}</td>
                                <td class="px-4 py-3 font-medium">{{ formatMoney(conv.reward_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                          :class="STATUS_STYLES[conv.status] || 'bg-slate-50 text-slate-600'">
                                        {{ conv.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ formatDate(conv.trial_started) }}</td>
                                <td class="px-4 py-3">
                                    <button @click="openConversionDetail(conv)"
                                            class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                                        Detalhes
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="conversions.data.length" class="flex items-center justify-between border-t px-4 py-3 text-xs text-slate-500">
                    <span>{{ conversions.total }} indicação(ões)</span>
                    <div class="flex gap-2">
                        <button v-if="conversions.prev_page_url" @click="router.get(conversions.prev_page_url, {}, { preserveState: true })"
                                class="rounded-lg border px-3 py-1.5 hover:bg-slate-50">← Anterior</button>
                        <button v-if="conversions.next_page_url" @click="router.get(conversions.next_page_url, {}, { preserveState: true })"
                                class="rounded-lg border px-3 py-1.5 hover:bg-slate-50">Próxima →</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal PIX -->
        <Teleport to="body">
            <div v-if="showPixModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showPixModal = false">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">Cadastrar chave PIX</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-slate-600">Tipo de chave</label>
                            <select v-model="pixForm.pix_type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                                <option v-for="t in PIX_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Chave PIX</label>
                            <input v-model="pixForm.pix_key" type="text"
                                   class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"
                                   placeholder="Informe sua chave PIX" />
                        </div>
                    </div>
                    <div class="mt-6 flex gap-2 justify-end">
                        <button @click="showPixModal = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                        <button @click="savePix" :disabled="savingPix"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {{ savingPix ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Saque -->
        <Teleport to="body">
            <div v-if="showWithdrawModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showWithdrawModal = false">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-1">Solicitar saque</h3>
                    <p class="text-sm text-slate-500 mb-4">Saldo disponível: {{ formatMoney(wallet.balance) }}</p>
                    <label class="text-sm text-slate-600">Valor a sacar (R$)</label>
                    <input v-model.number="withdrawAmount" type="number" step="0.01"
                           :min="settings.minimum_withdraw" :max="wallet.balance"
                           class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                    <div class="mt-6 flex gap-2 justify-end">
                        <button @click="showWithdrawModal = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                        <button @click="requestWithdraw" :disabled="withdrawing"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {{ withdrawing ? 'Enviando...' : 'Confirmar saque' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal detalhe indicado -->
        <Teleport to="body">
            <div v-if="selectedConversion" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="selectedConversion = null">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-1">{{ selectedConversion.clinic_name }}</h3>
                    <p class="text-sm text-slate-500 mb-5">{{ selectedConversion.clinic_city || 'Cidade não informada' }}</p>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-slate-500">Plano</dt><dd class="font-medium">{{ selectedConversion.plan_name }}</dd></div>
                        <div><dt class="text-slate-500">Status</dt>
                            <dd><span class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                      :class="STATUS_STYLES[selectedConversion.status]">{{ selectedConversion.status_label }}</span></dd>
                        </div>
                        <div><dt class="text-slate-500">Dias restantes de teste</dt><dd class="font-medium">{{ selectedConversion.days_remaining }} dias</dd></div>
                        <div><dt class="text-slate-500">Liberação prevista</dt><dd class="font-medium">{{ formatDate(selectedConversion.eligible_at) }}</dd></div>
                        <div><dt class="text-slate-500">Valor do bônus</dt><dd class="font-medium text-emerald-600">{{ formatMoney(selectedConversion.reward_amount) }}</dd></div>
                    </dl>
                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="selectedConversion = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Fechar</button>
                        <Link :href="route(routes.show, selectedConversion.id)"
                              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Ver página completa
                        </Link>
                    </div>
                </div>
            </div>
        </Teleport>
    </component>
</template>
