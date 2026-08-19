<script setup>
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatusBadge from '@/Components/Admin/StatusBadge.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const props = defineProps({
    clinic: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    recent_activity: { type: Array, default: () => [] },
})

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

async function toggleBlock() {
    const action = props.clinic.status === 'suspended' ? 'unblock' : 'block'
    if (action === 'block' && !confirm(`Bloquear "${props.clinic.name}"? Os membros perdem acesso imediatamente.`)) return

    try {
        await window.axios.post(route(`admin.clinics.${action}`, props.clinic.id))
        toast.success(action === 'block' ? 'Clínica bloqueada.' : 'Clínica desbloqueada.')
        router.reload()
    } catch (e) {
        toast.error('Não foi possível concluir a operação.')
    }
}
</script>

<template>
    <AdminLayout>
        <Link :href="route('admin.clinics')" class="text-xs text-slate-500 hover:text-emerald-700">&larr; Voltar para Clínicas</Link>

        <div class="mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">{{ clinic.trade_name || clinic.name }}</h2>
                    <StatusBadge :status="clinic.status" />
                </div>
                <p class="text-sm text-slate-500 mt-1">{{ clinic.type }} · Cadastrada em {{ formatDate(clinic.created_at) }}</p>
            </div>
            <button @click="toggleBlock"
                    class="rounded-xl px-4 py-2 text-sm font-medium"
                    :class="clinic.status === 'suspended'
                        ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                        : 'border border-red-200 text-red-600 hover:bg-red-50'">
                {{ clinic.status === 'suspended' ? 'Desbloquear clínica' : 'Bloquear clínica' }}
            </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="rounded-2xl border bg-white p-4">
                <p class="text-[11px] text-slate-500">Plano</p>
                <p class="mt-1 font-semibold text-slate-800">{{ clinic.plan }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4">
                <p class="text-[11px] text-slate-500">Assinatura</p>
                <p class="mt-1 font-semibold text-slate-800">{{ clinic.subscription_status }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4">
                <p class="text-[11px] text-slate-500">Membros</p>
                <p class="mt-1 font-semibold text-slate-800">{{ clinic.members_count }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4">
                <p class="text-[11px] text-slate-500">Pacientes</p>
                <p class="mt-1 font-semibold text-slate-800">{{ clinic.patients_count }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold text-slate-800">Dono</div>
                <div class="p-5 text-sm" v-if="clinic.owner">
                    <p class="font-medium text-slate-800">{{ clinic.owner.name }}</p>
                    <p class="text-slate-500">{{ clinic.owner.email }}</p>
                </div>
                <p v-else class="p-5 text-sm text-slate-400">Nenhum dono identificado.</p>

                <div class="px-5 py-3 border-t border-b font-semibold text-slate-800">Membros ({{ members.length }})</div>
                <div class="divide-y max-h-72 overflow-y-auto">
                    <div v-for="m in members" :key="m.id" class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-800">{{ m.name }}</p>
                            <p class="text-xs text-slate-500">{{ m.email }} · {{ m.role }}</p>
                        </div>
                        <StatusBadge :status="m.status" />
                    </div>
                    <p v-if="!members.length" class="px-5 py-6 text-center text-sm text-slate-400">Sem membros.</p>
                </div>
            </div>

            <div class="rounded-2xl border bg-white overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold text-slate-800">Atividade recente</div>
                <div class="divide-y max-h-[26rem] overflow-y-auto">
                    <div v-for="log in recent_activity" :key="log.id" class="px-5 py-3 text-sm">
                        <p class="font-medium text-slate-800">{{ log.action_label }}</p>
                        <p class="text-xs text-slate-500">{{ log.description }}</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ log.user }} · {{ formatDate(log.created_at) }}</p>
                    </div>
                    <p v-if="!recent_activity.length" class="px-5 py-6 text-center text-sm text-slate-400">Sem atividade registrada.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
