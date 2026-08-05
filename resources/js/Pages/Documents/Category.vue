<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    category: Object,
    templates: { type: Array, default: () => [] },
})

const setDefault = (template) => {
    router.post(route('document-templates.set-default', template.id), {}, { preserveScroll: true })
}

const duplicate = (template) => {
    router.post(route('document-templates.duplicate', template.id))
}

const archive = (template) => {
    if (confirm(`Arquivar o modelo "${template.name}"?`)) {
        router.post(route('document-templates.archive', template.id), {}, { preserveScroll: true })
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <Link :href="route('documents.index')" class="text-[11px] text-slate-400 hover:text-teal-600 transition-colors">← Documentos</Link>
                    <h1 class="text-xl font-bold text-slate-900 mt-1">{{ category.name }}</h1>
                    <p v-if="category.description" class="text-sm text-slate-500 mt-0.5">{{ category.description }}</p>
                </div>
                <Link :href="route('document-templates.create', { category_id: category.id })" class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 shadow-sm transition-colors">+ Novo modelo</Link>
            </div>

            <div v-if="!templates.length" class="rounded-2xl border border-dashed border-slate-200 py-20 text-center">
                <p class="text-sm text-slate-500">Nenhum modelo nesta categoria ainda.</p>
            </div>

            <div v-else class="space-y-2.5">
                <div
                    v-for="t in templates"
                    :key="t.id"
                    class="rounded-2xl border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm transition-all duration-150 overflow-hidden"
                >
                    <div class="px-5 py-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-slate-900 text-sm">{{ t.name }}</p>
                                <span v-if="t.is_default" class="text-[9px] font-semibold text-teal-700 bg-teal-50 border border-teal-100 px-1.5 py-0.5 rounded">Padrão</span>
                                <span v-if="t.is_system" class="text-[9px] font-medium text-slate-500 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded">Sistema</span>
                            </div>
                            <p v-if="t.description" class="text-[12px] text-slate-500 mt-1">{{ t.description }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">v{{ t.version || 1 }} · {{ t.issued_count }} emitido(s)</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-0 border-t border-slate-100 bg-slate-50/50">
                        <Link :href="route('document-templates.edit', t.id)" class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100">Editar modelo</Link>
                        <button @click="duplicate(t)" class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100">Duplicar</button>
                        <button v-if="!t.is_default" @click="setDefault(t)" class="flex-1 text-center py-2 text-[11px] font-medium text-slate-600 hover:text-teal-700 hover:bg-teal-50/50 transition-colors border-r border-slate-100">Definir padrão</button>
                        <button @click="archive(t)" class="flex-1 text-center py-2 text-[11px] font-medium text-red-400 hover:text-red-600 hover:bg-red-50/50 transition-colors">Arquivar</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
