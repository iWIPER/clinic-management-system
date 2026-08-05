<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineProps({ templates: Array })
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Modelos de Anamnese</h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie perguntas, categorias e versões</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('anamnesis-categories.index')" class="rounded-lg border px-4 py-2 text-sm">Categorias</Link>
                <Link :href="route('anamnesis-templates.create')"
                      class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">Novo modelo</Link>
            </div>
        </div>

        <div class="grid gap-4">
            <div v-for="t in templates" :key="t.id"
                 class="rounded-2xl border border-slate-200 bg-white p-5 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-medium text-slate-900">{{ t.name }}</h3>
                        <span v-if="t.is_system" class="text-[10px] rounded-full bg-slate-100 px-2 py-0.5 text-slate-500">Sistema</span>
                        <span v-if="!t.is_active" class="text-[10px] rounded-full bg-red-50 px-2 py-0.5 text-red-600">Inativo</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ t.questions_count }} perguntas · v{{ t.version }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('anamnesis-templates.edit', t.id)" class="rounded-lg border px-3 py-1.5 text-xs">Construtor</Link>
                    <button @click="router.post(route('anamnesis-templates.duplicate', t.id))"
                            class="rounded-lg border px-3 py-1.5 text-xs">Duplicar</button>
                    <button v-if="!t.is_default" @click="router.post(route('anamnesis-templates.set-default', t.id))"
                            class="rounded-lg border px-3 py-1.5 text-xs">Padrão</button>
                    <button v-if="!t.is_system" @click="router.delete(route('anamnesis-templates.destroy', t.id))"
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600">Excluir</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>