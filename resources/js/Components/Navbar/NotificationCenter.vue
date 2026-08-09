<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import NotificationBadge from './NotificationBadge.vue'
import NavbarDropdown from './NavbarDropdown.vue'
import GeneralTab from './NotificationTabs/GeneralTab.vue'
import SignaturesTab from './NotificationTabs/SignaturesTab.vue'

// ── Aba "Geral" (lembretes de agenda + indicações) ────────────────────────
const counts = ref({ total: 0, aguardando_confirmacao: 0, consulta_proxima: 0, referral_notifications: [] })

const fetchGeneral = async () => {
    try {
        const res = await fetch(route('notifications.counts'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
        if (res.ok) counts.value = await res.json()
    } catch {}
}

const generalItems = computed(() => {
    const items = []
    if (counts.value.aguardando_confirmacao > 0)
        items.push({ type: 'warning', text: `${counts.value.aguardando_confirmacao} agendamento(s) aguardando confirmação hoje` })
    if (counts.value.consulta_proxima > 0)
        items.push({ type: 'success', text: `${counts.value.consulta_proxima} consulta(s) nos próximos 30 min` })
    if (counts.value.referral_notifications?.length) {
        counts.value.referral_notifications.forEach((n) => items.push(n))
    }
    return items
})

// ── Aba "Assinaturas" (anamneses/evoluções aguardando assinatura) ─────────
const signatures = ref({ count: 0, items: [] })

const fetchSignatures = async () => {
    try {
        const res = await fetch(route('anamneses.pending-signatures'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
        if (res.ok) signatures.value = await res.json()
    } catch {}
}

// ── Abas (categorias) — adicionar uma nova categoria no futuro é só
// acrescentar uma entrada aqui com seu próprio fetch, sem tocar no resto. ──
const tabs = computed(() => [
    { id: 'general',    label: 'Geral',       count: counts.value.total },
    { id: 'signatures', label: 'Assinaturas', count: signatures.value.count },
])

const activeTab = ref('general')
const totalBadge = computed(() => counts.value.total + signatures.value.count)

// ── Badge + animação do sino ───────────────────────────────────────────────
const bellAnimating = ref(false)
let prevTotal = 0
let timer = null

const fetchAll = async () => {
    await Promise.all([fetchGeneral(), fetchSignatures()])
    const next = totalBadge.value
    if (next > prevTotal && next > 0) {
        bellAnimating.value = true
        setTimeout(() => { bellAnimating.value = false }, 700)
    }
    prevTotal = next
}

onMounted(() => {
    fetchAll()
    timer = setInterval(fetchAll, 60000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
    <NavbarDropdown width="w-96">
        <template #trigger="{ open }">
            <button
                type="button"
                :aria-expanded="open"
                aria-label="Notificações"
                class="relative cursor-pointer rounded-lg p-1.5 text-slate-500 transition-all duration-[180ms] ease hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.97]"
            >
                <svg
                    class="h-5 w-5 transition-transform duration-[180ms] ease"
                    :class="{ 'navbar-bell-ring': bellAnimating }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <NotificationBadge :count="totalBadge" />
            </button>
        </template>

        <template #default="{ close }">
            <div class="flex items-center justify-between border-b px-4 py-2.5">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-700">Notificações</span>
                <span
                    v-if="totalBadge > 0"
                    class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-600"
                >
                    {{ totalBadge }} pendente{{ totalBadge > 1 ? 's' : '' }}
                </span>
            </div>

            <nav class="flex flex-wrap gap-1 border-b px-2 pt-1.5">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    @click="activeTab = t.id"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium transition-colors border-b-2 -mb-px whitespace-nowrap"
                    :class="activeTab === t.id
                        ? 'border-emerald-600 text-emerald-700'
                        : 'border-transparent text-slate-500 hover:text-slate-700'"
                >
                    {{ t.label }}
                    <span
                        v-if="t.count > 0"
                        class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600"
                        :class="activeTab === t.id ? 'bg-emerald-50 text-emerald-700' : ''"
                    >
                        {{ t.count }}
                    </span>
                </button>
            </nav>

            <GeneralTab v-if="activeTab === 'general'" :items="generalItems" :close="close" />
            <SignaturesTab v-else-if="activeTab === 'signatures'" :items="signatures.items" :close="close" />
        </template>
    </NavbarDropdown>
</template>

<style scoped>
@keyframes navbar-bell-ring {
    0%, 100% { transform: rotate(0deg); }
    15%      { transform: rotate(8deg); }
    30%      { transform: rotate(-6deg); }
    45%      { transform: rotate(4deg); }
    60%      { transform: rotate(-2deg); }
    75%      { transform: rotate(0deg); }
}

.navbar-bell-ring {
    animation: navbar-bell-ring 0.6s ease;
    transform-origin: top center;
}
</style>
