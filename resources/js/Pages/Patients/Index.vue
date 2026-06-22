<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    patients: Object, // paginated
    filters: Object,
});

const search = ref(props.filters?.search || '');

const doSearch = () => {
    router.get(route('patients.index'), { search: search.value }, { preserveState: true, replace: true });
};

const deletePatient = (patient) => {
    if (confirm(`Remover ${patient.nome} ${patient.sobrenome}?`)) {
        router.delete(route('patients.destroy', patient.id));
    }
};
</script>

<template>
    <AppLayout>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Pacientes</h1>
            <Link :href="route('patients.create')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2">
                + Novo Paciente
            </Link>
        </div>

        <div class="mb-4 flex gap-2">
            <input 
                v-model="search" 
                @keyup.enter="doSearch"
                type="text" 
                placeholder="Buscar por nome, CPF ou telefone..." 
                class="flex-1 border rounded-lg px-4 py-2 text-sm" 
            />
            <button @click="doSearch" class="px-5 py-2 border rounded-lg text-sm hover:bg-slate-50">Buscar</button>
        </div>

        <div class="bg-white rounded-2xl border overflow-hidden shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b">
                        <th class="p-4 text-left font-medium text-slate-600">Nome</th>
                        <th class="p-4 text-left font-medium text-slate-600">Telefone</th>
                        <th class="p-4 text-left font-medium text-slate-600">Nascimento</th>
                        <th class="p-4 text-left font-medium text-slate-600">Status</th>
                        <th class="p-4 text-right font-medium text-slate-600">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="patient in patients.data" :key="patient.id">
                        <td class="p-4 font-medium">
                            <Link :href="route('patients.show', patient.id)" class="text-emerald-700 hover:underline">
                                {{ patient.nome }} {{ patient.sobrenome }}
                            </Link>
                        </td>
                        <td class="p-4 text-slate-600">{{ patient.telefone || '—' }}</td>
                        <td class="p-4 text-slate-500">{{ patient.nascimento ? new Date(patient.nascimento).toLocaleDateString('pt-BR') : '—' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs rounded-full" :class="{
                                'bg-green-100 text-green-700': patient.status === 'ativo',
                                'bg-slate-100 text-slate-600': patient.status === 'inativo',
                                'bg-red-100 text-red-700': patient.status === 'falecido',
                            }">
                                {{ patient.status || 'ativo' }}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-3">
                            <Link :href="route('patients.edit', patient.id)" class="text-slate-600 hover:text-slate-900">Editar</Link>
                            <button @click="deletePatient(patient)" class="text-red-600 hover:text-red-700">Excluir</button>
                        </td>
                    </tr>

                    <tr v-if="patients.data.length === 0">
                        <td colspan="5" class="p-12 text-center text-slate-400">
                            Nenhum paciente encontrado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginação simples -->
        <div class="mt-4 flex justify-between text-sm text-slate-500" v-if="patients.data.length > 0">
            <div>Mostrando {{ patients.from }} a {{ patients.to }} de {{ patients.total }}</div>
            <div class="space-x-2">
                <button v-if="patients.prev_page_url" @click="router.get(patients.prev_page_url)" class="hover:text-slate-700">← Anterior</button>
                <button v-if="patients.next_page_url" @click="router.get(patients.next_page_url)" class="hover:text-slate-700">Próxima →</button>
            </div>
        </div>
    </AppLayout>
</template>

