<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

defineProps({
    datasets: { type: Array, required: true },
})

const statusFilter = ref('')
const downloading = ref(null)

async function download(dataset) {
    downloading.value = dataset
    try {
        const response = await window.axios.post(
            route('admin.exports.download', dataset),
            { status: statusFilter.value || undefined },
            { responseType: 'blob' }
        )
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `${dataset}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        toast.success('Exportação concluída.')
    } catch (e) {
        toast.error('Não foi possível gerar a exportação.')
    } finally {
        downloading.value = null
    }
}
</script>

<template>
    <AdminLayout>
        <p class="text-sm text-slate-500 mb-6 max-w-2xl">
            Cada exportação é auditada (quem pediu, o quê, quando). Datasets são gerados em streaming — mesmo
            grandes, não sobrecarregam a memória do servidor. Dados de pacientes não são exportados aqui por
            princípio de minimização — cada clínica exporta os próprios pacientes pela tela dela.
        </p>

        <div class="mb-6 rounded-2xl border bg-white p-5 max-w-xs">
            <label class="text-xs text-slate-500">Filtrar por status (quando aplicável)</label>
            <input v-model="statusFilter" type="text" placeholder="ex: active, pending..." class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="d in datasets" :key="d.key" class="rounded-2xl border bg-white p-5 flex flex-col justify-between">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ d.label }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Formato CSV</p>
                </div>
                <button @click="download(d.key)" :disabled="downloading === d.key"
                        class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                    {{ downloading === d.key ? 'Gerando...' : 'Baixar CSV' }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
