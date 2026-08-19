<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputError from '@/Components/InputError.vue'
import PageHeader from '@/Components/Navigation/PageHeader.vue'
import SettingsTabs from '@/Components/ClinicSettings/SettingsTabs.vue'

const props = defineProps({
    convenios: { type: Array, default: () => [] },
})

const newForm = useForm({ nome: '', ordem: 0 })
const editingId = ref(null)
const editForm = useForm({ nome: '', ordem: 0 })

const create = () => {
    newForm.post(route('clinic-settings.convenios.store'), {
        preserveScroll: true,
        onSuccess: () => newForm.reset(),
    })
}

const startEdit = (c) => {
    editingId.value = c.id
    editForm.nome = c.nome
    editForm.ordem = c.ordem
}

const saveEdit = (c) => {
    editForm.put(route('clinic-settings.convenios.update', c.id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

const toggle = (c) => {
    router.post(route('clinic-settings.convenios.toggle', c.id), {}, { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <template #pageHeader>
            <PageHeader title="Configurações da Clínica" description="Gerencie os dados, recursos e áreas da sua clínica." />
        </template>

        <SettingsTabs active="convenios" />

        <div class="max-w-3xl">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Convênios</h2>
                <p class="text-sm text-slate-500 mt-1">Convênios disponíveis para o cadastro de pacientes e para o módulo de Tratamentos.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 mb-5">
                <h2 class="text-sm font-bold text-slate-900 mb-3">Novo convênio</h2>
                <form @submit.prevent="create" class="flex gap-2 items-start">
                    <div class="flex-1">
                        <input v-model="newForm.nome" type="text" placeholder="Nome do convênio"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                        <InputError :message="newForm.errors.nome" />
                    </div>
                    <button type="submit" :disabled="newForm.processing"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white rounded-lg text-sm font-semibold transition-colors shrink-0">
                        Adicionar
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-for="c in convenios" :key="c.id"
                     class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-100 last:border-0">
                    <template v-if="editingId === c.id">
                        <input v-model="editForm.nome" type="text"
                               class="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm outline-none focus:border-teal-400" />
                        <div class="flex gap-2 shrink-0">
                            <button type="button" @click="saveEdit(c)" class="text-xs font-semibold text-teal-600 hover:text-teal-800">Salvar</button>
                            <button type="button" @click="editingId = null" class="text-xs text-slate-400 hover:text-slate-600">Cancelar</button>
                        </div>
                    </template>
                    <template v-else>
                        <span class="text-sm" :class="c.ativo ? 'text-slate-800' : 'text-slate-400 line-through'">{{ c.nome }}</span>
                        <div class="flex items-center gap-3 shrink-0">
                            <span v-if="!c.ativo" class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Inativo</span>
                            <button type="button" @click="startEdit(c)" class="text-xs font-medium text-slate-500 hover:text-teal-700">Editar</button>
                            <button type="button" @click="toggle(c)" class="text-xs font-medium"
                                    :class="c.ativo ? 'text-red-500 hover:text-red-700' : 'text-emerald-600 hover:text-emerald-800'">
                                {{ c.ativo ? 'Desativar' : 'Reativar' }}
                            </button>
                        </div>
                    </template>
                </div>
                <p v-if="!convenios.length" class="px-5 py-8 text-center text-sm text-slate-400">Nenhum convênio cadastrado.</p>
            </div>
        </div>
    </AppLayout>
</template>
