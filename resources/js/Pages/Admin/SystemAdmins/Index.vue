<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputError from '@/Components/InputError.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    admins: { type: Array, required: true },
})

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

const newEmail = ref('')
const addError = ref('')
const adding = ref(false)

async function addAdmin() {
    adding.value = true
    addError.value = ''
    try {
        await window.axios.post(route('admin.system-admins.store'), { email: newEmail.value })
        toast.success('Privilégio de System Admin concedido.')
        newEmail.value = ''
        router.reload()
    } catch (e) {
        addError.value = e.response?.data?.errors?.email?.[0] || e.response?.data?.message || 'Não foi possível conceder o privilégio.'
    } finally {
        adding.value = false
    }
}

async function removeAdmin(admin) {
    const label = admin.is_self ? 'a si mesmo' : admin.user.name
    if (!confirm(`Remover o privilégio de System Admin de ${label}?`)) return

    try {
        await window.axios.delete(route('admin.system-admins.destroy', admin.user.id))
        toast.success('Privilégio removido.')
        router.reload()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Não foi possível remover o privilégio.')
    }
}
</script>

<template>
    <AdminLayout>
        <div class="mb-6 rounded-2xl border bg-white p-5">
            <h3 class="font-semibold text-slate-900 mb-1">Conceder System Admin</h3>
            <p class="text-xs text-slate-500 mb-4">O usuário precisa já ter uma conta no Wildental — a conta continua sendo um usuário normal, só ganha o privilégio administrativo.</p>
            <div class="flex flex-wrap gap-2">
                <div class="flex-1 min-w-[240px]">
                    <input v-model="newEmail" type="email" placeholder="E-mail do usuário" class="w-full rounded-lg border px-3 py-2 text-sm" />
                    <InputError :message="addError" />
                </div>
                <button @click="addAdmin" :disabled="adding || !newEmail.trim()"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 h-fit">
                    Conceder
                </button>
            </div>
        </div>

        <div class="rounded-2xl border bg-white overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold text-slate-800">Administradores da plataforma ({{ admins.length }})</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Administrador</th>
                            <th class="px-5 py-3">Concedido por</th>
                            <th class="px-5 py-3">Desde</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in admins" :key="a.id" class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">{{ a.user.name }} <span v-if="a.is_self" class="text-xs text-slate-400">(você)</span></p>
                                <p class="text-xs text-slate-500">{{ a.user.email }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ a.granted_by }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ formatDate(a.granted_at) }}</td>
                            <td class="px-5 py-3 text-right">
                                <button v-if="admins.length > 1" @click="removeAdmin(a)"
                                        class="text-xs text-red-600 hover:text-red-700 font-medium">Remover</button>
                                <span v-else class="text-xs text-slate-400" title="Não é possível remover o último System Admin">único admin</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
