<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    plans: { type: Array, default: () => [] },
})

const editing = ref(null)
const form = ref({})
const saving = ref(false)

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function startEdit(plan) {
    editing.value = plan.id
    form.value = { ...plan }
}

async function savePlan() {
    saving.value = true
    try {
        await window.axios.put(route('admin.plans.update', editing.value), form.value)
        editing.value = null
        window.location.reload()
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <AdminLayout>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div v-for="plan in plans" :key="plan.id" class="rounded-2xl border bg-white p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-slate-900">{{ plan.name }}</h3>
                    <span v-if="plan.is_featured" class="rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">DESTAQUE</span>
                </div>
                <p class="text-sm text-slate-500 mb-4">{{ plan.description }}</p>
                <p class="text-2xl font-bold text-slate-800">{{ formatMoney(plan.price_monthly) }}<span class="text-sm font-normal text-slate-400">/mês</span></p>
                <p class="text-xs text-slate-400 mt-1">{{ plan.trial_days }} dias de teste</p>

                <ul class="mt-4 space-y-1.5">
                    <li v-for="f in plan.features" :key="f.label" class="flex items-center gap-2 text-sm text-slate-600">
                        <span class="text-emerald-500">✔</span> {{ f.label }}
                    </li>
                </ul>

                <button @click="startEdit(plan)" class="mt-5 w-full rounded-lg border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Editar plano
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="editing = null">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">Editar plano</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-slate-500">Nome</label>
                            <input v-model="form.name" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-slate-500">Preço mensal</label>
                                <input v-model.number="form.price_monthly" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Preço anual</label>
                                <input v-model.number="form.price_yearly" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-slate-500">Dias de teste</label>
                                <input v-model.number="form.trial_days" type="number" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Máx. usuários</label>
                                <input v-model.number="form.max_users" type="number" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" class="rounded" /> Plano ativo
                        </label>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="editing = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                        <button @click="savePlan" :disabled="saving"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>