<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatusBadge from '@/Components/Admin/StatusBadge.vue'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    targetUser: { type: Object, required: true },
    clinics: { type: Array, default: () => [] },
    recent_activity: { type: Array, default: () => [] },
})

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

async function toggleBlock() {
    const action = props.targetUser.status === 'inativo' ? 'unblock' : 'block'
    try {
        await window.axios.post(route(`admin.users.${action}`, props.targetUser.id))
        toast.success(action === 'block' ? 'Usuário bloqueado.' : 'Usuário desbloqueado.')
        router.reload()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Não foi possível concluir a operação.')
    }
}

// Exclusão — confirmação forte (digitar o e-mail exato), porque o
// resultado real depende do histórico do usuário: anonimização (padrão,
// preserva registros clínicos ligados) ou exclusão física (só quando não
// há nenhum vínculo clínico) — a decisão é do backend, não do frontend.
const showDeleteModal = ref(false)
const confirmationEmail = ref('')
const deleteError = ref('')
const deleting = ref(false)

async function confirmDelete() {
    deleting.value = true
    deleteError.value = ''
    try {
        const { data } = await window.axios.delete(route('admin.users.destroy', props.targetUser.id), {
            data: { confirmation: confirmationEmail.value },
        })
        toast.success(data.result === 'deleted' ? 'Conta excluída definitivamente.' : 'Conta anonimizada — histórico clínico preservado.')
        router.visit(route('admin.users'))
    } catch (e) {
        deleteError.value = e.response?.data?.errors?.confirmation?.[0]
            || e.response?.data?.errors?.user?.[0]
            || e.response?.data?.message
            || 'Não foi possível excluir esta conta.'
    } finally {
        deleting.value = false
    }
}
</script>

<template>
    <AdminLayout>
        <Link :href="route('admin.users')" class="text-xs text-slate-500 hover:text-emerald-700">&larr; Voltar para Usuários</Link>

        <div class="mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">{{ targetUser.name }}</h2>
                    <StatusBadge :status="targetUser.status" />
                    <span v-if="targetUser.is_system_admin" class="rounded-full bg-violet-50 border border-violet-200 px-2 py-0.5 text-xs font-medium text-violet-700">System Admin</span>
                </div>
                <p class="text-sm text-slate-500 mt-1">{{ targetUser.email }} <span v-if="targetUser.phone">· {{ targetUser.phone }}</span></p>
                <p class="text-xs text-slate-400 mt-1">Cadastrado em {{ formatDate(targetUser.created_at) }} · Último acesso {{ formatDate(targetUser.last_login_at) }}</p>
            </div>
            <div class="flex gap-2">
                <button @click="toggleBlock"
                        class="rounded-xl px-4 py-2 text-sm font-medium"
                        :class="targetUser.status === 'inativo'
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                            : 'border border-red-200 text-red-600 hover:bg-red-50'">
                    {{ targetUser.status === 'inativo' ? 'Desbloquear' : 'Bloquear' }}
                </button>
                <button @click="showDeleteModal = true"
                        class="rounded-xl border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                    Excluir conta
                </button>
            </div>
        </div>

        <p v-if="targetUser.is_system_admin" class="mb-6 rounded-xl bg-violet-50 px-4 py-3 text-xs text-violet-800">
            Privilégio de System Admin gerenciado só pela área própria — ver
            <Link :href="route('admin.system-admins')" class="font-semibold underline">System Admins</Link>.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold text-slate-800">Clínicas ({{ clinics.length }})</div>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <div v-for="c in clinics" :key="c.id" class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-800">{{ c.name }}</p>
                            <p class="text-xs text-slate-500">{{ c.role }} · vinculado em {{ formatDate(c.joined_at) }}</p>
                        </div>
                        <StatusBadge :status="c.status" />
                    </div>
                    <p v-if="!clinics.length" class="px-5 py-6 text-center text-sm text-slate-400">Sem vínculo com nenhuma clínica.</p>
                </div>
            </div>

            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold text-slate-800">Atividade recente</div>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <div v-for="log in recent_activity" :key="log.id" class="px-5 py-3 text-sm">
                        <p class="font-medium text-slate-800">{{ log.action_label }}</p>
                        <p class="text-xs text-slate-500">{{ log.description }}</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ formatDate(log.created_at) }}</p>
                    </div>
                    <p v-if="!recent_activity.length" class="px-5 py-6 text-center text-sm text-slate-400">Sem atividade registrada.</p>
                </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" title="Excluir conta de usuário" @close="showDeleteModal = false">
            <div class="p-5 space-y-3 text-sm">
                <p class="text-slate-600">
                    Se {{ targetUser.name }} tiver registros clínicos vinculados (prontuário, evolução, consulta, anamnese),
                    a conta será <strong>anonimizada</strong> — o histórico clínico é preservado, sem nenhum dado pessoal
                    associado. Caso contrário, a conta é <strong>excluída definitivamente</strong>. Essa decisão é feita
                    automaticamente pelo sistema, com base no histórico real.
                </p>
                <p class="text-slate-600">Digite <strong>{{ targetUser.email }}</strong> para confirmar:</p>
                <input v-model="confirmationEmail" type="text" class="w-full rounded-lg border px-3 py-2 text-sm" :placeholder="targetUser.email" />
                <InputError :message="deleteError" />
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button @click="showDeleteModal = false" class="rounded-lg border px-4 py-2 text-sm">Cancelar</button>
                    <button @click="confirmDelete" :disabled="deleting || confirmationEmail !== targetUser.email"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-40">
                        Confirmar exclusão
                    </button>
                </div>
            </template>
        </Modal>
    </AdminLayout>
</template>
