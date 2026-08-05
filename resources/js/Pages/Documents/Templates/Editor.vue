<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import DocumentRichEditor from '@/Components/Documents/DocumentRichEditor.vue'
import DocumentPlaceholderMenu from '@/Components/Documents/DocumentPlaceholderMenu.vue'
import DocumentLivePreview from '@/Components/Documents/DocumentLivePreview.vue'
import DocumentSavedTemplatesDropdown from '@/Components/Documents/DocumentSavedTemplatesDropdown.vue'

const props = defineProps({
    template: { type: Object, default: null },
    categoryId: { type: [Number, String, null], default: null },
    categories: { type: Array, default: () => [] },
    placeholders: { type: Array, default: () => [] },
})

const isEditing = computed(() => !!props.template)
const editorRef = ref(null)

const form = useForm({
    category_id: props.template?.category_id || props.categoryId || '',
    name: props.template?.name || '',
    description: props.template?.description || '',
    content_html: props.template?.content_html || '',
    requires_patient_signature: props.template?.requires_patient_signature ?? true,
    requires_professional_signature: props.template?.requires_professional_signature ?? false,
    requires_responsible_signature: props.template?.requires_responsible_signature ?? false,
    requires_witness_signature: props.template?.requires_witness_signature ?? false,
    signature_expiration_hours: props.template?.signature_expiration_hours ?? 72,
    change_summary: '',
})

const submit = () => {
    if (isEditing.value) {
        form.put(route('document-templates.update', props.template.id), { preserveScroll: true })
    } else {
        form.post(route('document-templates.store'))
    }
}

const insertPlaceholder = (key) => {
    editorRef.value?.insertPlaceholder(key)
}

const onDropdownAction = (action) => {
    if (action === 'new') {
        router.visit(route('document-templates.create'))
    } else if (action === 'duplicate' && isEditing.value) {
        router.post(route('document-templates.duplicate', props.template.id))
    } else if (action === 'archive' && isEditing.value) {
        if (confirm('Arquivar este modelo? Ele deixará de ficar disponível para novas emissões.')) {
            router.post(route('document-templates.archive', props.template.id), {}, {
                onSuccess: () => router.visit(route('documents.index')),
            })
        }
    } else if (action === 'delete' && isEditing.value) {
        if (confirm('Excluir este modelo definitivamente? Esta ação não pode ser desfeita.')) {
            router.delete(route('document-templates.destroy', props.template.id))
        }
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-[1400px] mx-auto px-4 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <Link :href="route('documents.index')" class="text-[11px] text-slate-400 hover:text-teal-600 transition-colors">← Documentos</Link>
                    <h1 class="text-xl font-bold text-slate-900 mt-1">{{ isEditing ? 'Editar modelo' : 'Novo modelo' }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <DocumentSavedTemplatesDropdown :has-template="isEditing" @new="onDropdownAction('new')" @duplicate="onDropdownAction('duplicate')" @archive="onDropdownAction('archive')" @delete="onDropdownAction('delete')" />
                    <button
                        type="button"
                        @click="submit"
                        :disabled="form.processing || !form.name || !form.category_id"
                        class="rounded-xl bg-teal-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 hover:bg-teal-700 transition-colors shadow-sm"
                    >{{ isEditing ? 'Salvar alterações' : 'Criar modelo' }}</button>
                </div>
            </div>

            <!-- Meta fields -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 mb-5">
                <div class="grid md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Título</label>
                        <input v-model="form.name" type="text" placeholder="Ex: Consentimento Informado" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Categoria</label>
                        <select v-model="form.category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400 bg-white">
                            <option value="">Selecione…</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.category_id" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Versão atual</label>
                        <input :value="template?.version ? `v${template.version}` : 'v1 (nova)'" type="text" disabled class="w-full rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Descrição</label>
                        <input v-model="form.description" type="text" placeholder="Breve descrição do modelo (opcional)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Expiração do link (h)</label>
                        <input v-model.number="form.signature_expiration_hours" type="number" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                        <InputError :message="form.errors.signature_expiration_hours" />
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Assinaturas necessárias</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.requires_patient_signature" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Paciente
                        </label>
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.requires_professional_signature" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Profissional
                        </label>
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.requires_responsible_signature" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Responsável
                        </label>
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.requires_witness_signature" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Testemunhas
                        </label>
                    </div>
                </div>
            </div>

            <!-- Editor + Preview -->
            <div class="grid lg:grid-cols-2 gap-5 items-start">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Conteúdo do documento</span>
                        <DocumentPlaceholderMenu :placeholders="placeholders" @insert="insertPlaceholder" />
                    </div>
                    <DocumentRichEditor ref="editorRef" v-model="form.content_html" />
                </div>

                <DocumentLivePreview :content-html="form.content_html" />
            </div>

            <!-- Histórico de versões -->
            <div v-if="isEditing && template?.versions?.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Histórico de versões</h3>
                <div class="space-y-2">
                    <div v-for="v in template.versions" :key="v.version" class="flex items-center justify-between text-[12px] py-1.5 border-b border-slate-50 last:border-0">
                        <span class="font-medium text-slate-700">v{{ v.version }} — {{ v.title }}</span>
                        <span class="text-slate-400">{{ v.created_by || '—' }} · {{ v.created_at }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
