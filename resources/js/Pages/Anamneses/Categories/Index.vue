<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({ categories: Array })

const editing = ref(null)

const form = useForm({
    name: '',
    icon: '📄',
    icon_color: '#64748b',
    description: '',
    sort_order: 0,
    is_active: true,
})

const startCreate = () => {
    editing.value = 'new'
    form.reset()
    form.icon = '📄'
    form.is_active = true
}

const startEdit = (cat) => {
    editing.value = cat.id
    form.name = cat.name
    form.icon = cat.icon
    form.icon_color = cat.icon_color
    form.description = cat.description || ''
    form.sort_order = cat.sort_order
    form.is_active = cat.is_active
}

const save = () => {
    if (editing.value === 'new') {
        form.post(route('anamnesis-categories.store'), { preserveScroll: true, onSuccess: () => { editing.value = null } })
    } else {
        form.put(route('anamnesis-categories.update', editing.value), { preserveScroll: true, onSuccess: () => { editing.value = null } })
    }
}

const title = (name) => name.toLowerCase().replace(/\b\w/g, c => c.toUpperCase())
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto pb-10">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <Link :href="route('anamnesis-templates.index')" class="text-xs text-slate-500">← Modelos</Link>
                    <h1 class="text-xl font-semibold text-slate-900 mt-1">Categorias</h1>
                    <p class="text-xs text-slate-500">Ícones, descrições e ordem das seções</p>
                </div>
                <button @click="startCreate" class="rounded-xl bg-teal-600 px-4 py-2 text-sm text-white">+ Categoria</button>
            </div>

            <div v-if="editing" class="rounded-2xl border border-[#E8EDF4] bg-white p-5 mb-5 space-y-3">
                <input v-model="form.name" type="text" placeholder="Nome (ex: GERAL)" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                <InputError :message="form.errors.name" />
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <input v-model="form.icon" type="text" placeholder="Ícone" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                        <InputError :message="form.errors.icon" />
                    </div>
                    <div>
                        <input v-model="form.icon_color" type="text" placeholder="#64748b" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                        <InputError :message="form.errors.icon_color" />
                    </div>
                    <div>
                        <input v-model.number="form.sort_order" type="number" placeholder="Ordem" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                </div>
                <textarea v-model="form.description" rows="2" placeholder="Descrição" class="w-full rounded-xl border border-[#E8EDF4] px-3 py-2 text-sm" />
                <InputError :message="form.errors.description" />
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Ativa</label>
                <div class="flex gap-2">
                    <button @click="save" :disabled="form.processing" class="rounded-xl bg-teal-600 px-4 py-2 text-sm text-white">Salvar</button>
                    <button @click="editing = null" class="rounded-xl border px-4 py-2 text-sm">Cancelar</button>
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="rounded-xl border border-[#E8EDF4] bg-white px-4 py-3 flex items-center justify-between hover:shadow-sm transition-all"
                    :class="!cat.is_active ? 'opacity-60' : ''"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-lg" :style="{ color: cat.icon_color }">{{ cat.icon }}</span>
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ title(cat.name) }}</p>
                            <p class="text-[10px] text-slate-400">{{ cat.questions_count }} perguntas · ordem {{ cat.sort_order }}</p>
                        </div>
                    </div>
                    <button @click="startEdit(cat)" class="text-xs text-teal-600 hover:underline">Editar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>