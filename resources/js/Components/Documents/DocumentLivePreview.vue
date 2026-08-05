<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    contentHtml: { type: String, default: '' },
    samplePatientId: { type: [Number, String, null], default: null },
})

const previewHtml = ref('')
const loading = ref(false)
let debounceTimer = null

const fetchPreview = async () => {
    loading.value = true
    try {
        const { data } = await axios.post(route('document-templates.preview'), {
            content_html: props.contentHtml,
            sample_patient_id: props.samplePatientId,
        })
        previewHtml.value = data.html
    } catch (e) {
        // preview é auxiliar — falha silenciosa não deve travar o editor
    } finally {
        loading.value = false
    }
}

watch(() => [props.contentHtml, props.samplePatientId], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(fetchPreview, 500)
}, { immediate: true })
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-100/60 p-4 sticky top-4">
        <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Preview em tempo real</span>
            <span v-if="loading" class="text-[10px] text-slate-400">Atualizando…</span>
        </div>
        <div class="mx-auto bg-white shadow-md rounded-sm max-w-[560px] min-h-[720px] px-10 py-12 prose prose-sm prose-slate">
            <div v-html="previewHtml" />
        </div>
    </div>
</template>
