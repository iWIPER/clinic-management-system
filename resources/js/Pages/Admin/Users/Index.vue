<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatusBadge from '@/Components/Admin/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
let timer = null

function applyFilters(extra = {}) {
    router.get(route('admin.users'), {
        search: search.value || undefined,
        status: status.value || undefined,
        ...extra,
    }, { preserveState: true, replace: true })
}

watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(() => applyFilters({ page: undefined }), 350)
})
watch(status, () => applyFilters({ page: undefined }))

function goToPage(page) {
    applyFilters({ page })
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('pt-BR') : '—'
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input v-model="search" type="search" placeholder="Buscar por nome ou e-mail..."
                   class="w-full max-w-sm rounded-xl border px-4 py-2 text-sm" />
            <select v-model="status" class="rounded-xl border px-3 py-2 text-sm text-slate-600">
                <option value="">Todos os status</option>
                <option value="ativo">Ativo</option>
                <option value="inativo">Bloqueado</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Clínicas</th>
                            <th class="px-4 py-3">System Admin</th>
                            <th class="px-4 py-3">Último acesso</th>
                            <th class="px-4 py-3">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link :href="route('admin.users.show', u.id)" class="font-medium text-slate-800 hover:text-emerald-700">{{ u.name }}</Link>
                                <p class="text-xs text-slate-500">{{ u.email }}</p>
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="u.status" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ u.clinics_count }}</td>
                            <td class="px-4 py-3">
                                <span v-if="u.is_system_admin" class="text-xs font-medium text-violet-700">Sim</span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(u.last_login_at) }}</td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(u.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-if="!users.data.length" title="Nenhum usuário encontrado" description="Ajuste a busca ou os filtros." />

            <div class="px-4 pb-4" v-if="users.data.length">
                <Pagination :pagination="users" :bordered="false" @change="goToPage" />
            </div>
        </div>
    </AdminLayout>
</template>
