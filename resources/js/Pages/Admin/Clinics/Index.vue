<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatusBadge from '@/Components/Admin/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    clinics: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
let timer = null

function applyFilters(extra = {}) {
    router.get(route('admin.clinics'), {
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

async function blockClinic(c) {
    if (!confirm(`Bloquear a clínica "${c.name}"? Os membros perdem acesso imediatamente.`)) return
    try {
        await window.axios.post(route('admin.clinics.block', c.id))
        toast.success('Clínica bloqueada.')
        router.reload({ only: ['clinics'] })
    } catch (e) {
        toast.error('Não foi possível bloquear a clínica.')
    }
}

async function unblockClinic(c) {
    try {
        await window.axios.post(route('admin.clinics.unblock', c.id))
        toast.success('Clínica desbloqueada.')
        router.reload({ only: ['clinics'] })
    } catch (e) {
        toast.error('Não foi possível desbloquear a clínica.')
    }
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input v-model="search" type="search" placeholder="Buscar por nome..."
                   class="w-full max-w-sm rounded-xl border px-4 py-2 text-sm" />
            <select v-model="status" class="rounded-xl border px-3 py-2 text-sm text-slate-600">
                <option value="">Todos os status</option>
                <option value="active">Ativa</option>
                <option value="trial">Trial</option>
                <option value="suspended">Bloqueada</option>
                <option value="cancelled">Cancelada</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Clínica</th>
                            <th class="px-4 py-3">Plano</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Assinatura</th>
                            <th class="px-4 py-3">Cadastro</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in clinics.data" :key="c.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="route('admin.clinics.show', c.id)" class="text-slate-800 hover:text-emerald-700">{{ c.name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ c.plan }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="c.status" /></td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ c.subscription_status }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ c.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="c.status !== 'suspended'" @click="blockClinic(c)"
                                        class="text-xs text-red-600 hover:text-red-700 font-medium">Bloquear</button>
                                <button v-else @click="unblockClinic(c)"
                                        class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Desbloquear</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-if="!clinics.data.length" title="Nenhuma clínica encontrada" description="Ajuste a busca ou os filtros." />

            <div class="px-4 pb-4" v-if="clinics.data.length">
                <Pagination :pagination="clinics" :bordered="false" @change="goToPage" />
            </div>
        </div>
    </AdminLayout>
</template>
