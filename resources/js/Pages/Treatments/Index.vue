<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    treatments: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const doSearch = () => {
    router.get(route('treatments.index'), { search: search.value }, { preserveState: true });
};

const deleteTreatment = (treatment) => {
    if (confirm(`Desativar ${treatment.nome}?`)) {
        router.delete(route('treatments.destroy', treatment.id));
    }
};
</script>

<template>
    <AppLayout>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Catálogo de Tratamentos</h1>
            <Link :href="route('treatments.create')" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm">
                + Novo Tratamento
            </Link>
        </div>

        <div class="mb-4">
            <input 
                v-model="search" 
                @keyup.enter="doSearch"
                placeholder="Buscar por nome..." 
                class="border rounded-lg px-4 py-2 w-80 text-sm" 
            />
            <button @click="doSearch" class="ml-2 px-4 py-2 border rounded-lg text-sm">Buscar</button>
        </div>

        <div class="bg-white rounded-2xl border overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-4 text-left">Nome</th>
                        <th class="p-4 text-left">Especialidade</th>
                        <th class="p-4 text-left">Duração (min)</th>
                        <th class="p-4 text-left">Preço Base</th>
                        <th class="p-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="t in treatments.data" :key="t.id">
                        <td class="p-4 font-medium">{{ t.nome }}</td>
                        <td class="p-4">{{ t.especialidade || '—' }}</td>
                        <td class="p-4">{{ t.duracao_padrao }}</td>
                        <td class="p-4">R$ {{ parseFloat(t.preco_base || 0).toFixed(2) }}</td>
                        <td class="p-4 text-right space-x-3">
                            <Link :href="route('treatments.edit', t.id)" class="text-emerald-600">Editar</Link>
                            <button @click="deleteTreatment(t)" class="text-red-500">Desativar</button>
                        </td>
                    </tr>
                    <tr v-if="treatments.data.length === 0">
                        <td colspan="5" class="p-8 text-center text-slate-400">Nenhum tratamento encontrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
