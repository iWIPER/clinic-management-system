<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'

const props = defineProps({
    logs:    { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    action_options: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const range  = ref(props.filters.range || '7days')
const action = ref(props.filters.action || '')
let timer = null

function applyFilters(extra = {}) {
    router.get(route('admin.logs'), {
        search: search.value || undefined,
        range: range.value,
        action: action.value || undefined,
        ...extra,
    }, { preserveState: true, replace: true })
}

watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(() => applyFilters({ page: undefined }), 350)
})
watch([range, action], () => applyFilters({ page: undefined }))

function goToPage(page) {
    applyFilters({ page })
}

function formatDate(iso) {
    if (! iso) return '—'
    return new Date(iso).toLocaleString('pt-BR')
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4 flex flex-wrap gap-3">
            <input v-model="search" type="search" placeholder="Buscar nos logs..."
                   class="rounded-xl border px-4 py-2 text-sm flex-1 min-w-[200px]" />
            <select v-model="action" class="rounded-xl border px-3 py-2 text-sm">
                <option value="">Todas as ações</option>
                <option v-for="(label, key) in action_options" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="range" class="rounded-xl border px-3 py-2 text-sm">
                <option value="today">Hoje</option>
                <option value="7days">7 dias</option>
                <option value="30days">30 dias</option>
                <option value="all">Todo o período</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Data/Hora</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Descrição</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Clínica</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ log.action_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 max-w-xs truncate">{{ log.description }}</td>
                            <td class="px-4 py-3">{{ log.user?.name || '—' }}</td>
                            <td class="px-4 py-3">{{ log.clinic?.name || '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ log.ip_address || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-if="!logs.data.length" title="Nenhum log encontrado" description="Ajuste os filtros ou o período." />

            <div class="px-4 pb-4" v-if="logs.data.length">
                <Pagination :pagination="logs" :bordered="false" @change="goToPage" />
            </div>
        </div>
    </AdminLayout>
</template>
