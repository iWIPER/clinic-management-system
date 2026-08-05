<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    logs:   { type: Array,  default: () => [] },
    range:  { type: String, default: 'today' },
    search: { type: String, default: '' },
})

const selectedRange = ref(props.range)
const searchQuery   = ref(props.search)
let _searchTimer    = null

const RANGE_OPTIONS = [
    { value: 'today',   label: 'Hoje' },
    { value: '7days',   label: 'Últimos 7 dias' },
    { value: '30days',  label: 'Últimos 30 dias' },
]

function applyFilter() {
    router.get(route('access-logs.index'), {
        range: selectedRange.value,
        search: searchQuery.value || undefined,
    }, { preserveState: true, only: ['logs', 'range', 'search'] })
}

watch(selectedRange, applyFilter)

watch(searchQuery, (val) => {
    clearTimeout(_searchTimer)
    _searchTimer = setTimeout(applyFilter, 350)
})

function exportCsv() {
    window.location.href = route('access-logs.export', { range: selectedRange.value })
}

// ── Helpers ────────────────────────────────────────────────────────────────
const ACTION_STYLES = {
    login:                { bg: 'bg-emerald-100', text: 'text-emerald-700', icon: '🔓' },
    logout:               { bg: 'bg-slate-100',   text: 'text-slate-600',   icon: '🔒' },
    invite_sent:          { bg: 'bg-blue-100',    text: 'text-blue-700',    icon: '✉️' },
    invite_accepted:      { bg: 'bg-indigo-100',  text: 'text-indigo-700',  icon: '✅' },
    invite_cancelled:     { bg: 'bg-red-100',     text: 'text-red-600',     icon: '❌' },
    invite_resent:        { bg: 'bg-cyan-100',    text: 'text-cyan-700',    icon: '↩️' },
    member_deactivated:   { bg: 'bg-red-100',     text: 'text-red-600',     icon: '⛔' },
    member_reactivated:   { bg: 'bg-emerald-100', text: 'text-emerald-700', icon: '✔️' },
    password_changed:     { bg: 'bg-amber-100',   text: 'text-amber-700',   icon: '🔑' },
    profile_updated:      { bg: 'bg-sky-100',     text: 'text-sky-700',     icon: '✏️' },
    google_drive_connected:    { bg: 'bg-green-100', text: 'text-green-700', icon: '☁️' },
    google_drive_disconnected: { bg: 'bg-orange-100', text: 'text-orange-600', icon: '☁️' },
}

function actionStyle(action) {
    return ACTION_STYLES[action] ?? { bg: 'bg-slate-100', text: 'text-slate-600', icon: '📋' }
}

const DEVICE_ICONS = { desktop: '🖥️', notebook: '💻', tablet: '📱', mobile: '📱' }

function formatDateTime(date) {
    if (! date) return '-'
    const d = new Date(date)
    return d.toLocaleDateString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function initials(name) {
    if (! name) return '?'
    return name.trim().split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
    <AppLayout title="Logs de Acesso">
        <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">

            <!-- Cabeçalho -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Logs de Acesso</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Monitoramento de segurança em tempo real</p>
                </div>
                <button
                    @click="exportCsv"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Exportar CSV
                </button>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Range buttons -->
                <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-white shadow-sm">
                    <button
                        v-for="opt in RANGE_OPTIONS"
                        :key="opt.value"
                        @click="selectedRange = opt.value"
                        class="px-4 py-2 text-sm font-medium transition-colors"
                        :class="selectedRange === opt.value
                            ? 'bg-blue-600 text-white'
                            : 'text-slate-600 hover:bg-slate-50'"
                    >{{ opt.label }}</button>
                </div>

                <!-- Pesquisa -->
                <div class="relative flex-1 min-w-48">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por usuário, ação, IP..."
                        class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                </div>

                <!-- Contagem -->
                <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">
                    {{ logs.length }} registro{{ logs.length !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Tabela de logs -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div v-if="logs.length === 0" class="text-center py-16 text-slate-400">
                    <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                    </svg>
                    <p class="text-sm font-medium">Nenhum log encontrado</p>
                    <p class="text-xs mt-1">Tente ajustar os filtros acima</p>
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Data/Hora</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Usuário</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Ação</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Dispositivo</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">IP</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Localização</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="log in logs"
                            :key="log.id"
                            class="hover:bg-slate-50 transition-colors group"
                        >
                            <!-- Data -->
                            <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap font-mono">
                                {{ formatDateTime(log.created_at) }}
                            </td>

                            <!-- Usuário -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-[10px] font-bold">{{ initials(log.user?.name) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800 text-xs truncate">{{ log.user?.name || '—' }}</p>
                                        <p class="text-slate-400 text-[11px] truncate">{{ log.user?.email || '—' }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Ação -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-start gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap flex-shrink-0"
                                        :class="[actionStyle(log.action).bg, actionStyle(log.action).text]"
                                    >
                                        {{ actionStyle(log.action).icon }} {{ log.action_label }}
                                    </span>
                                    <span class="text-xs text-slate-500 hidden xl:inline truncate">{{ log.description }}</span>
                                </div>
                            </td>

                            <!-- Dispositivo -->
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <div class="text-xs text-slate-600 space-y-0.5">
                                    <div>{{ DEVICE_ICONS[log.device_type] }} {{ log.browser || '—' }}</div>
                                    <div class="text-slate-400">{{ log.os || '—' }}</div>
                                </div>
                            </td>

                            <!-- IP -->
                            <td class="px-4 py-3.5 hidden lg:table-cell">
                                <code class="text-xs text-slate-500 font-mono">{{ log.ip_address || '—' }}</code>
                            </td>

                            <!-- Localização -->
                            <td class="px-4 py-3.5 hidden lg:table-cell text-xs text-slate-500">
                                {{ [log.city, log.country].filter(Boolean).join(', ') || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Rodapé informativo -->
                <div v-if="logs.length > 0" class="border-t border-slate-100 px-4 py-3 bg-slate-50 flex items-center justify-between text-xs text-slate-400">
                    <span>Exibindo os últimos {{ logs.length }} registros</span>
                    <span>Atualizado agora</span>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
