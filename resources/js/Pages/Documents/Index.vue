<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

defineProps({ categories: { type: Array, default: () => [] } })

const showNewCategory = ref(false)
const form = useForm({ name: '', description: '' })

const createCategory = () => {
    form.post(route('document-categories.store'), {
        preserveScroll: true,
        onSuccess: () => { showNewCategory.value = false; form.reset() },
    })
}

const COLOR_DOT = {
    teal: 'bg-teal-500', blue: 'bg-blue-500', amber: 'bg-amber-500', red: 'bg-red-500',
    slate: 'bg-slate-400', purple: 'bg-purple-500', emerald: 'bg-emerald-500', pink: 'bg-pink-500',
}
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Documentos</h1>
                    <p class="text-sm text-slate-500 mt-1">Gerador inteligente de documentos clínicos — modelos, assinaturas e histórico em um só lugar.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('clinic-settings.documents.edit')" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Configurações</Link>
                    <button @click="showNewCategory = !showNewCategory" class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 shadow-sm transition-colors">+ Nova categoria</button>
                </div>
            </div>

            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                leave-active-class="transition-all duration-150 ease-in"
                enter-from-class="opacity-0 -translate-y-1"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div v-if="showNewCategory" class="mb-8 rounded-2xl border border-teal-100 bg-teal-50/40 p-5">
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <input v-model="form.name" placeholder="Nome da categoria" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <input v-model="form.description" placeholder="Descrição (opcional)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button @click="createCategory" :disabled="!form.name || form.processing" class="rounded-xl bg-teal-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 hover:bg-teal-700 transition-colors">Criar</button>
                        <button @click="showNewCategory = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors">Cancelar</button>
                    </div>
                </div>
            </Transition>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-200 hover:shadow-md transition-all duration-150"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="COLOR_DOT[cat.color] || COLOR_DOT.teal" />
                            <h3 class="font-semibold text-slate-900 text-[15px] truncate">{{ cat.name }}</h3>
                        </div>
                        <span
                            v-if="cat.pending_signatures > 0"
                            class="text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5 shrink-0"
                        >{{ cat.pending_signatures }} pendente(s)</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div>
                            <div class="text-[10px] text-slate-400">Emitidos</div>
                            <div class="font-semibold text-slate-700 text-sm">{{ cat.issued_count }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400">Última emissão</div>
                            <div class="font-semibold text-slate-700 text-sm">{{ cat.last_issued_at || '—' }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link :href="route('documents.category', cat.id)" class="flex-1 text-center rounded-lg bg-teal-50 text-teal-700 text-[12px] font-semibold py-2 hover:bg-teal-100 transition-colors">Ver modelos</Link>
                        <Link :href="route('document-templates.create', { category_id: cat.id })" class="flex-1 text-center rounded-lg border border-slate-200 text-slate-600 text-[12px] font-medium py-2 hover:bg-slate-50 transition-colors">Novo modelo</Link>
                    </div>
                    <div class="mt-2 text-[10px] text-slate-400">{{ cat.templates_count }} modelo(s) ativo(s)</div>
                </div>
            </div>

            <div v-if="!categories.length" class="rounded-2xl border border-dashed border-slate-200 py-20 text-center">
                <p class="text-sm text-slate-500">Nenhuma categoria ainda. Crie a primeira para começar.</p>
            </div>
        </div>
    </AppLayout>
</template>
