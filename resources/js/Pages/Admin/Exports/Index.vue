<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataSubjectSearchPanel from '@/Components/Admin/DataSubjectSearchPanel.vue'
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
        <p class="text-sm text-slate-500 mb-8 max-w-2xl">
            Cada exportação é auditada (quem pediu, o quê, quando). Datasets administrativos são gerados em
            streaming — mesmo grandes, não sobrecarregam a memória do servidor — e nunca incluem dados de
            pacientes, por princípio de minimização. O atendimento pontual a um titular específico (paciente ou
            usuário) é feito à parte, na seção abaixo.
        </p>

        <section class="mb-10">
            <div class="flex items-baseline justify-between flex-wrap gap-2 mb-1">
                <h2 class="text-base font-semibold text-slate-900">Atendimento a titulares (LGPD)</h2>
                <span class="text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2.5 py-1">
                    Uso pontual, um titular por vez
                </span>
            </div>
            <p class="text-sm text-slate-500 mb-4 max-w-2xl">
                Para atender uma solicitação recebida por chat/suporte — nunca autoatendimento do titular nem
                exportação em massa. Busque a pessoa pelo nome, e-mail ou CPF e gere a exportação individual dela.
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <DataSubjectSearchPanel
                    subject-type="user"
                    title="Titular usuário"
                    description="Exporta os dados cadastrais e vínculos de clínica do usuário (dono, profissional ou staff)."
                />
                <DataSubjectSearchPanel
                    subject-type="patient"
                    title="Titular paciente"
                    description="Exporta cadastro, prontuário (evoluções e odontograma), anamnese, agenda, atendimentos, tratamentos, orçamentos, financeiro, documentos, assinaturas, fotos e notas do paciente, cada categoria no formato de origem dentro de um ZIP."
                />
            </div>
        </section>

        <section>
            <h2 class="text-base font-semibold text-slate-900 mb-1">Exportações administrativas em massa</h2>
            <p class="text-xs text-slate-500 mb-4">Datasets completos da plataforma, em CSV.</p>

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
        </section>
    </AdminLayout>
</template>
