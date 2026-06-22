<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    consultations: Object,
    filters: Object,
});

const filters = ref({
    status: props.filters?.status || '',
    search: props.filters?.search || '',
});

const applyFilters = () => {
    router.get(route('consultations.index'), filters.value, { preserveState: true });
};

const statusLabels = {
    aguardando: 'Aguardando',
    em_atendimento: 'Em Atendimento',
    finalizado: 'Finalizado',
    cancelado: 'Cancelado',
};

const statusColors = {
    aguardando: 'bg-yellow-100 text-yellow-700',
    em_atendimento: 'bg-blue-100 text-blue-700',
    finalizado: 'bg-green-100 text-green-700',
    cancelado: 'bg-red-100 text-red-700',
};
</script>

<template>
    <AppLayout>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Consultas / Atendimentos</h1>
        </div>

        <!-- Filtros -->
        <div class="flex gap-3 mb-4">
            <input 
                v-model="filters.search" 
                @keyup.enter="applyFilters"
                placeholder="Buscar paciente..." 
                class="border rounded-lg px-4 py-2 text-sm flex-1" 
            />
            <select v-model="filters.status" @change="applyFilters" class="border rounded-lg px-4 py-2 text-sm">
                <option value="">Todas</option>
                <option value="aguardando">Aguardando</option>
                <option value="em_atendimento">Em Atendimento</option>
                <option value="finalizado">Finalizadas</option>
            </select>
            <button @click="applyFilters" class="bg-slate-800 text-white px-4 rounded-lg text-sm">Filtrar</button>
        </div>

        <div class="bg-white rounded-2xl border overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-4 text-left">Check-in</th>
                        <th class="p-4 text-left">Paciente</th>
                        <th class="p-4 text-left">Profissional</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="cons in consultations.data" :key="cons.id">
                        <td class="p-4">
                            {{ cons.check_in_at ? new Date(cons.check_in_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '—' }}
                        </td>
                        <td class="p-4 font-medium">
                            <Link :href="route('patients.show', cons.patient.id)" class="hover:underline">
                                {{ cons.patient.nome }} {{ cons.patient.sobrenome }}
                            </Link>
                        </td>
                        <td class="p-4">{{ cons.professional?.name }}</td>
                        <td class="p-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusColors[cons.status]">
                                {{ statusLabels[cons.status] }}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <Link :href="route('consultations.show', cons.id)" class="text-emerald-600 hover:underline">
                                Atender
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!consultations.data.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">Nenhuma consulta encontrada.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
