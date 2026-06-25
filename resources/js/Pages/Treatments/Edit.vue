<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    treatment: Object,
    categories: Array,
    parents: Array,
});

const form = useForm({
    nome: props.treatment.nome,
    categoria: props.treatment.categoria ?? '',
    tipo: props.treatment.tipo ?? 'procedimento',
    parent_id: props.treatment.parent_id,
    especialidade: props.treatment.especialidade ?? '',
    duracao_padrao: props.treatment.duracao_padrao,
    preco_base: props.treatment.preco_base,
    descricao: props.treatment.descricao ?? '',
    cor: props.treatment.cor ?? '#10b981',
    ordem: props.treatment.ordem ?? 0,
});

const filteredParents = computed(() => {
    if (!form.categoria) return props.parents;
    return props.parents.filter((p) => p.categoria === form.categoria);
});

const submit = () => {
    form.put(route('treatments.update', props.treatment.id));
};
</script>

<template>
    <AppLayout>
        <h1 class="text-2xl font-semibold mb-6">Editar Procedimento</h1>

        <form @submit.prevent="submit" class="max-w-lg bg-white p-8 rounded-2xl border space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome *</label>
                <input v-model="form.nome" type="text" class="w-full border rounded-lg p-2 mt-1" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Categoria</label>
                    <select v-model="form.categoria" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">Selecione...</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select v-model="form.tipo" class="w-full border rounded-lg p-2 mt-1">
                        <option value="procedimento">Procedimento</option>
                        <option value="variacao">Variação</option>
                        <option value="grupo">Grupo</option>
                    </select>
                </div>
            </div>

            <div v-if="form.tipo === 'variacao'">
                <label class="block text-sm font-medium text-gray-700">Procedimento pai</label>
                <select v-model="form.parent_id" class="w-full border rounded-lg p-2 mt-1">
                    <option :value="null">Nenhum</option>
                    <option v-for="p in filteredParents" :key="p.id" :value="p.id">{{ p.nome }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Especialidade</label>
                <input v-model="form.especialidade" type="text" class="w-full border rounded-lg p-2 mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Duração (min)</label>
                    <input v-model="form.duracao_padrao" type="number" min="0" class="w-full border rounded-lg p-2 mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Preço sugerido (R$)</label>
                    <input v-model="form.preco_base" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cor</label>
                    <input v-model="form.cor" type="color" class="w-full h-10 border rounded-lg mt-1 cursor-pointer" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ordem</label>
                    <input v-model="form.ordem" type="number" min="0" class="w-full border rounded-lg p-2 mt-1" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Descrição clínica</label>
                <textarea v-model="form.descricao" rows="4" class="w-full border rounded-lg p-2 mt-1"></textarea>
            </div>

            <p class="text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100">
                Para desativar ou reativar, use a página de detalhes do procedimento.
            </p>

            <div class="pt-4 flex gap-3">
                <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg" :disabled="form.processing">
                    Salvar
                </button>
                <Link :href="route('treatments.show', treatment.id)" class="px-4 py-2 text-slate-600">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>