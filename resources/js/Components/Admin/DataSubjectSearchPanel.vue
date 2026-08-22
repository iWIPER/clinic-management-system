<script setup>
import { ref, onUnmounted } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    subjectType: { type: String, required: true, validator: (v) => ['user', 'patient'].includes(v) },
    title: { type: String, required: true },
    description: { type: String, required: true },
    placeholder: { type: String, default: 'Buscar por nome, e-mail ou CPF...' },
})

const toast = useToast()

const term = ref('')
const results = ref([])
const searching = ref(false)
const selected = ref(null)
const generating = ref(false)

// Estado só usado no fluxo de paciente (assíncrono, com polling) — para
// usuário o download é imediato, sem nada disso.
const exportId = ref(null)
const exportStatus = ref(null) // processing | ready | failed
let pollTimer = null

let debounceTimer = null

function onInput() {
    clearTimeout(debounceTimer)
    selected.value = null
    resetExportState()
    if (term.value.trim().length < 2) {
        results.value = []
        return
    }
    debounceTimer = setTimeout(runSearch, 350)
}

async function runSearch() {
    searching.value = true
    try {
        const searchRoute = props.subjectType === 'user'
            ? 'admin.exports.subjects.users.search'
            : 'admin.exports.subjects.patients.search'
        const { data } = await window.axios.get(route(searchRoute), { params: { q: term.value.trim() } })
        results.value = data.results
    } catch (e) {
        toast.error('Não foi possível buscar.')
    } finally {
        searching.value = false
    }
}

function select(item) {
    selected.value = item
    results.value = []
    term.value = ''
    resetExportState()
}

function resetExportState() {
    clearTimeout(pollTimer)
    exportId.value = null
    exportStatus.value = null
}

async function generate() {
    if (!selected.value) return
    generating.value = true

    try {
        if (props.subjectType === 'user') {
            const response = await window.axios.post(
                route('admin.exports.subjects.users.export', selected.value.id),
                {},
                { responseType: 'blob' }
            )
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/json' }))
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', `titular-usuario-${selected.value.id}.json`)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
            toast.success('Exportação concluída.')
        } else {
            const { data } = await window.axios.post(route('admin.exports.subjects.patients.export', selected.value.id))
            exportId.value = data.export_id
            exportStatus.value = data.status
            schedulePoll()
        }
    } catch (e) {
        toast.error('Não foi possível gerar a exportação.')
    } finally {
        generating.value = false
    }
}

function schedulePoll() {
    clearTimeout(pollTimer)
    if (exportStatus.value !== 'processing') return
    pollTimer = setTimeout(checkStatus, 3000)
}

async function checkStatus() {
    if (!exportId.value) return
    try {
        const { data } = await window.axios.get(route('admin.exports.subjects.status', exportId.value))
        exportStatus.value = data.status
        if (data.status === 'failed') {
            toast.error('Falha ao gerar a exportação.')
        }
        schedulePoll()
    } catch (e) {
        // painel é informativo — falha de polling não deve travar a tela
    }
}

function downloadUrl() {
    return route('admin.exports.subjects.download', exportId.value)
}

onUnmounted(() => {
    clearTimeout(debounceTimer)
    clearTimeout(pollTimer)
})
</script>

<template>
    <div class="rounded-2xl border bg-white p-5">
        <h3 class="font-semibold text-slate-900">{{ title }}</h3>
        <p class="text-xs text-slate-500 mt-1 mb-4 max-w-xl">{{ description }}</p>

        <div class="relative max-w-md">
            <input
                v-model="term"
                @input="onInput"
                type="search"
                :placeholder="placeholder"
                class="w-full rounded-lg border px-3 py-2 text-sm"
            />
            <div v-if="results.length" class="absolute z-10 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg max-h-64 overflow-y-auto">
                <button
                    v-for="r in results"
                    :key="r.id"
                    @click="select(r)"
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-slate-50"
                >
                    <span class="font-medium text-slate-800">{{ r.name }}</span>
                    <span class="text-xs text-slate-500">{{ r.email }}</span>
                </button>
            </div>
            <p v-if="searching" class="mt-1 text-xs text-slate-400">Buscando...</p>
        </div>

        <div v-if="selected" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 max-w-md">
            <p class="text-sm font-semibold text-slate-900">{{ selected.name }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ selected.email }}</p>
            <p v-if="selected.cpf" class="text-xs text-slate-500">CPF: {{ selected.cpf }}</p>
            <p v-if="selected.clinic" class="text-xs text-slate-500">Clínica: {{ selected.clinic }}</p>
            <p v-else-if="selected.clinics?.length" class="text-xs text-slate-500">
                Clínica(s): {{ selected.clinics.join(', ') }}
            </p>

            <button
                v-if="!exportId"
                @click="generate"
                :disabled="generating"
                class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
            >
                {{ generating ? 'Gerando...' : 'Gerar exportação' + (subjectType === 'patient' ? ' completa' : '') }}
            </button>

            <div v-else class="mt-3">
                <span v-if="exportStatus === 'processing'" class="text-xs font-medium text-blue-700 bg-blue-50 rounded-full px-2.5 py-1">
                    Processando...
                </span>
                <a
                    v-else-if="exportStatus === 'ready'"
                    :href="downloadUrl()"
                    class="inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >Baixar ZIP</a>
                <span v-else-if="exportStatus === 'failed'" class="text-xs font-medium text-red-700 bg-red-50 rounded-full px-2.5 py-1">
                    Falha ao gerar
                </span>
            </div>
        </div>
    </div>
</template>
