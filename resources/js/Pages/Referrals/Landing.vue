<script setup>
import { ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { trackEvent } from '@/lib/analytics'

const props = defineProps({
    referrer: {
        type: Object,
        required: true,
        // { clinic_name, owner_name, avatar_url, logo_url }
    },
    benefits: {
        type: Object,
        required: true,
        // { trial_days, reward_amount }
    },
    registerUrl: { type: String, required: true },
})

function formatMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const invitedByLabel = props.referrer.owner_name
    ? `${props.referrer.owner_name} convidou você para conhecer o Wildental`
    : 'Você recebeu um convite para conhecer o Wildental'

const faqs = ref([
    {
        q: 'Quanto custa?',
        a: `Você começa com ${props.benefits.trial_days} dias totalmente grátis, sem compromisso. Depois disso, escolha o plano que melhor se encaixa na sua clínica.`,
        open: true,
    },
    {
        q: `Tenho ${props.benefits.trial_days} dias grátis?`,
        a: `Sim. Toda conta criada por indicação começa com ${props.benefits.trial_days} dias de acesso completo, sem restrições de funcionalidade.`,
        open: false,
    },
    {
        q: 'Preciso cadastrar cartão?',
        a: 'Não. Você pode criar sua conta e testar o sistema sem informar dados de pagamento.',
        open: false,
    },
    {
        q: `Quando ${props.referrer.owner_name || 'quem indicou'} recebe a recompensa?`,
        a: 'Assim que sua assinatura for confirmada e o período de carência for concluído, a recompensa é liberada automaticamente na carteira de quem te indicou.',
        open: false,
    },
    {
        q: 'Posso cancelar durante o teste?',
        a: 'Sim, o cancelamento durante o período de teste é livre e não gera nenhuma cobrança.',
        open: false,
    },
    {
        q: 'O desconto é automático?',
        a: 'Sim. Ao assinar um plano pago através deste convite, o desconto é aplicado automaticamente no checkout — nenhum cupom é necessário.',
        open: false,
    },
    {
        q: 'Como funciona o pagamento?',
        a: 'O pagamento é processado de forma segura e recorrente, mensal ou anual, conforme o plano escolhido.',
        open: false,
    },
    {
        q: 'O programa é válido para qualquer dentista?',
        a: 'Sim, qualquer profissional ou clínica odontológica pode criar uma conta a partir de um convite.',
        open: false,
    },
    {
        q: 'Posso indicar outras pessoas depois?',
        a: 'Sim! Assim que sua conta for criada, você recebe seu próprio link de indicação para convidar outros colegas.',
        open: false,
    },
    {
        q: 'Quem pode participar?',
        a: 'Qualquer clínica ou profissional com uma conta ativa no Wildental participa automaticamente do programa de indicação.',
        open: false,
    },
])

function toggleFaq(i) {
    faqs.value[i].open = !faqs.value[i].open
}

onMounted(() => trackEvent('landing_aberta'))

const steps = [
    { label: 'Receba o convite', icon: '✉️' },
    { label: 'Crie sua conta', icon: '📝' },
    { label: 'Teste gratuitamente', icon: '🚀' },
    { label: 'Assine', icon: '💳' },
    { label: 'Todos ganham', icon: '🎉' },
]
</script>

<template>
    <Head>
        <title>{{ invitedByLabel }} — Wildental</title>
        <meta name="description" :content="`Ganhe ${benefits.trial_days} dias grátis e desconto na primeira mensalidade ao criar sua conta no Wildental através deste convite.`" />
        <meta property="og:title" :content="invitedByLabel" />
        <meta property="og:description" :content="`Ganhe ${benefits.trial_days} dias grátis e desconto na primeira mensalidade.`" />
        <meta property="og:type" content="website" />
        <meta property="og:image" content="/images/og-referral.png" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
        <!-- ─── Hero ─────────────────────────────────────────────────────── -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-emerald-50 pointer-events-none"></div>

            <div class="relative max-w-4xl mx-auto px-6 pt-14 pb-10 sm:pt-20 sm:pb-16 text-center">
                <!-- Referrer badge -->
                <div class="inline-flex items-center gap-3 bg-white border border-slate-200 rounded-full pl-2 pr-5 py-2 shadow-sm mb-8">
                    <img
                        v-if="referrer.avatar_url"
                        :src="referrer.avatar_url"
                        alt=""
                        class="w-9 h-9 rounded-full object-cover border border-slate-200"
                    />
                    <div
                        v-else
                        class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold"
                    >
                        {{ (referrer.owner_name || referrer.clinic_name || 'C').charAt(0).toUpperCase() }}
                    </div>
                    <span class="text-sm font-medium text-slate-700">
                        Você foi convidado por
                        <strong class="text-slate-900">{{ referrer.owner_name || referrer.clinic_name }}</strong>
                        <template v-if="referrer.owner_name && referrer.clinic_name">
                            · {{ referrer.clinic_name }}
                        </template>
                    </span>
                </div>

                <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 leading-tight">
                    Gerencie sua clínica<br class="hidden sm:block" />
                    com muito mais praticidade.
                </h1>
                <p class="mt-5 text-lg text-slate-600 max-w-2xl mx-auto">
                    Agenda, prontuário, financeiro e Google Drive integrados numa plataforma só —
                    e você começa com benefícios exclusivos por ter vindo de um convite.
                </p>

                <div class="mt-10">
                    <Link
                        :href="registerUrl"
                        class="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-indigo-600 text-white text-base font-semibold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition"
                    >
                        Criar conta gratuita
                        <span aria-hidden="true">→</span>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500">Não é necessário cartão de crédito</p>
                </div>
            </div>
        </section>

        <!-- ─── Benefícios ───────────────────────────────────────────────── -->
        <section class="max-w-5xl mx-auto px-6 py-10 grid sm:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Para você, convidado</h2>
                <ul class="mt-4 space-y-2.5 text-slate-700">
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600">✔</span>
                        {{ benefits.trial_days }} dias grátis, sem compromisso
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600">✔</span>
                        {{ formatMoney(benefits.referred_discount_amount) }} de desconto na primeira mensalidade
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600">✔</span>
                        Ativação imediata — sem burocracia
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Para quem te indicou</h2>
                <p class="mt-4 text-slate-700">
                    <strong>{{ referrer.owner_name || referrer.clinic_name }}</strong> recebe uma recompensa em dinheiro
                    assim que sua assinatura for confirmada — por isso o convite é uma indicação de confiança,
                    não apenas um anúncio.
                </p>
            </div>
        </section>

        <!-- ─── Como funciona ────────────────────────────────────────────── -->
        <section class="max-w-5xl mx-auto px-6 py-12">
            <h2 class="text-2xl font-bold text-center text-slate-900">Como funciona</h2>
            <div class="mt-10 grid grid-cols-1 sm:grid-cols-5 gap-6 sm:gap-4">
                <div
                    v-for="(step, i) in steps"
                    :key="step.label"
                    class="flex sm:flex-col items-center sm:text-center gap-4 sm:gap-3"
                >
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-white border-2 border-indigo-200 flex items-center justify-center text-xl shadow-sm">
                        {{ step.icon }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ step.label }}</p>
                    </div>
                    <div v-if="i < steps.length - 1" class="hidden sm:block absolute translate-x-[4.5rem] translate-y-6 w-full h-px bg-indigo-100"></div>
                </div>
            </div>
        </section>

        <!-- ─── Social proof ─────────────────────────────────────────────── -->
        <section class="max-w-3xl mx-auto px-6 py-6 text-center">
            <p class="inline-block bg-slate-100 text-slate-600 text-sm font-medium px-4 py-2 rounded-full">
                ⭐ Centenas de clínicas já utilizam o Wildental
            </p>
        </section>

        <!-- ─── FAQ ───────────────────────────────────────────────────────── -->
        <section class="max-w-3xl mx-auto px-6 py-12">
            <h2 class="text-2xl font-bold text-center text-slate-900 mb-8">Perguntas frequentes</h2>
            <div class="space-y-3">
                <div
                    v-for="(item, i) in faqs"
                    :key="item.q"
                    class="border border-slate-200 rounded-xl overflow-hidden bg-white"
                >
                    <button
                        type="button"
                        class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left font-medium text-slate-800 hover:bg-slate-50"
                        @click="toggleFaq(i)"
                    >
                        {{ item.q }}
                        <span class="text-slate-400 transition-transform" :class="{ 'rotate-45': item.open }">+</span>
                    </button>
                    <div v-show="item.open" class="px-5 pb-4 text-sm text-slate-600 leading-relaxed">
                        {{ item.a }}
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── Confiança ─────────────────────────────────────────────────── -->
        <section class="max-w-4xl mx-auto px-6 py-10">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 grid sm:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Seus dados estão protegidos</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Seguimos as diretrizes da LGPD, com infraestrutura hospedada em nuvem e boas práticas
                        de segurança em todas as camadas do sistema.
                    </p>
                </div>
                <ul class="grid grid-cols-2 gap-4 text-sm text-slate-700">
                    <li class="flex items-center gap-2">🔒 LGPD</li>
                    <li class="flex items-center gap-2">☁️ Google Cloud</li>
                    <li class="flex items-center gap-2">💾 Backups automáticos</li>
                    <li class="flex items-center gap-2">✍️ Assinatura eletrônica</li>
                </ul>
            </div>
        </section>

        <!-- ─── CTA final ─────────────────────────────────────────────────── -->
        <section class="max-w-3xl mx-auto px-6 py-16 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Pronto para começar?</h2>
            <p class="mt-3 text-slate-600">Crie sua conta gratuita e comece a usar o Wildental hoje mesmo.</p>
            <Link
                :href="registerUrl"
                class="mt-8 inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-indigo-600 text-white text-base font-semibold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition"
            >
                Criar conta gratuita
                <span aria-hidden="true">→</span>
            </Link>

            <p class="mt-8 text-xs text-slate-400">
                Já tem uma conta?
                <Link :href="route('login')" class="underline hover:text-slate-600">Entrar</Link>
            </p>
        </section>
    </div>
</template>
