<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    clinics: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
let timer = null

watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('admin.clinics'), { search: val || undefined }, { preserveState: true })
    }, 350)
})

async function blockClinic(id) {
    await window.axios.post(route('admin.clinics.block', id))
    router.reload()
}

async function unblockClinic(id) {
    await window.axios.post(route('admin.clinics.unblock', id))
    router.reload()
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <input v-model="search" type="search" placeholder="Buscar clínica..."
                   class="w-full max-w-sm rounded-xl border px-4 py-2 text-sm" />
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Clínica</th>
                        <th class="px-4 py-3">Plano</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Código indicação</th>
                        <th class="px-4 py-3">Cadastro</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="c in clinics.data" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ c.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ c.plan }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full border px-2 py-0.5 text-xs"
                                  :class="c.status === 'suspended' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                {{ c.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ c.referral_code || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ c.created_at }}</td>
                        <td class="px-4 py-3">
                            <button v-if="c.status !== 'suspended'" @click="blockClinic(c.id)"
                                    class="text-xs text-red-600 hover:text-red-700 font-medium">Bloquear</button>
                            <button v-else @click="unblockClinic(c.id)"
                                    class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Desbloquear</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>