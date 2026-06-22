<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    appointments: Object,
    professionals: Array,
    filters: Object,
});

const activeTab = ref('lista'); // 'lista' | 'calendario'

const filters = ref({
    search: props.filters?.search || '',
    professional_id: props.filters?.professional_id || '',
    date: props.filters?.date || '',
    status: props.filters?.status || '',
});

const applyFilters = () => {
    router.get(route('appointments.index'), filters.value, { preserveState: true });
};

const clearFilters = () => {
    filters.value = { search: '', professional_id: '', date: '', status: '' };
    router.get(route('appointments.index'));
};

// Simple calendar: group appointments by date
const calendarDays = computed(() => {
    const groups = {};
    props.appointments.data.forEach(appt => {
        const date = appt.start.split('T')[0];
        if (!groups[date]) groups[date] = [];
        groups[date].push(appt);
    });
    return Object.keys(groups).sort().map(date => ({
        date,
        appointments: groups[date].sort((a, b) => a.start.localeCompare(b.start))
    }));
});

const formatTime = (datetime) => {
    return new Date(datetime).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'short' });
};

const statusLabels = {
    scheduled: 'Agendado',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
    no_show: 'Faltou',
    completed: 'Realizado',
};

const statusColors = {
    scheduled: 'bg-blue-100 text-blue-700',
    confirmed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
    no_show: 'bg-orange-100 text-orange-700',
    completed: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <AppLayout>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Agenda</h1>
            <Link :href="route('appointments.create')" class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium">
                + Novo Agendamento
            </Link>
        </div>

        <!-- Tabs -->
        <div class="flex border-b mb-4">
            <button @click="activeTab = 'lista'" 
                    class="px-6 py-2 font-medium" 
                    :class="activeTab === 'lista' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-500'">
                Lista
            </button>
            <button @click="activeTab = 'calendario'" 
                    class="px-6 py-2 font-medium" 
                    :class="activeTab === 'calendario' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-500'">
                Calendário
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-xl border mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
            <input v-model="filters.search" @keyup.enter="applyFilters" type="text" placeholder="Buscar paciente..." class="border rounded-lg px-3 py-2 text-sm" />
            
            <select v-model="filters.professional_id" @change="applyFilters" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos profissionais</option>
                <option v-for="prof in professionals" :key="prof.id" :value="prof.id">{{ prof.name }}</option>
            </select>

            <input v-model="filters.date" @change="applyFilters" type="date" class="border rounded-lg px-3 py-2 text-sm" />

            <select v-model="filters.status" @change="applyFilters" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos status</option>
                <option value="scheduled">Agendado</option>
                <option value="confirmed">Confirmado</option>
                <option value="completed">Realizado</option>
                <option value="cancelled">Cancelado</option>
            </select>

            <div class="flex gap-2">
                <button @click="applyFilters" class="flex-1 bg-slate-800 text-white rounded-lg text-sm">Filtrar</button>
                <button @click="clearFilters" class="px-4 text-sm text-slate-500">Limpar</button>
            </div>
        </div>

        <!-- LISTA -->
        <div v-if="activeTab === 'lista'">
            <div class="bg-white rounded-2xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-4 text-left">Data/Hora</th>
                            <th class="p-4 text-left">Paciente</th>
                            <th class="p-4 text-left">Profissional</th>
                            <th class="p-4 text-left">Tratamento</th>
                            <th class="p-4 text-left">Status</th>
                            <th class="p-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="appt in appointments.data" :key="appt.id">
                            <td class="p-4">
                                <div class="font-medium">{{ new Date(appt.start).toLocaleDateString('pt-BR') }}</div>
                                <div class="text-xs text-slate-500">{{ formatTime(appt.start) }} - {{ formatTime(appt.end) }}</div>
                            </td>
                            <td class="p-4">
                                <Link :href="route('patients.show', appt.patient.id)" class="hover:underline">
                                    {{ appt.patient.nome }} {{ appt.patient.sobrenome }}
                                </Link>
                            </td>
                            <td class="p-4 text-slate-600">{{ appt.professional?.name || '—' }}</td>
                            <td class="p-4 text-slate-600">{{ appt.treatment?.nome || '—' }}</td>
                            <td class="p-4">
                                <span class="px-3 py-1 text-xs rounded-full font-medium" :class="statusColors[appt.status]">
                                    {{ statusLabels[appt.status] }}
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-3 text-sm">
                                <Link :href="route('appointments.edit', appt.id)" class="text-emerald-600">Editar</Link>
                                
                                <Link 
                                    v-if="appt.status === 'scheduled' || appt.status === 'confirmed'" 
                                    :href="route('consultations.check-in', appt.id)" 
                                    method="post" 
                                    class="text-blue-600">
                                    Check-in
                                </Link>
                                
                                <button @click="router.delete(route('appointments.destroy', appt.id))" class="text-red-500">Cancelar</button>
                            </td>
                        </tr>
                        <tr v-if="appointments.data.length === 0">
                            <td colspan="6" class="p-8 text-center text-slate-400">Nenhum agendamento encontrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CALENDÁRIO SIMPLES (agrupado por dia) -->
        <div v-else class="space-y-6">
            <div v-for="day in calendarDays" :key="day.date" class="bg-white rounded-2xl border p-5">
                <div class="font-semibold text-lg mb-3 capitalize">{{ formatDate(day.date) }}</div>
                <div class="space-y-3">
                    <div v-for="appt in day.appointments" :key="appt.id" 
                         class="flex items-center justify-between border rounded-xl px-4 py-3 hover:bg-slate-50">
                        <div>
                            <div class="font-medium">{{ formatTime(appt.start) }} — {{ formatTime(appt.end) }}</div>
                            <div class="text-sm">{{ appt.patient.nome }} {{ appt.patient.sobrenome }} • {{ appt.treatment?.nome }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-0.5 text-xs rounded-full" :class="statusColors[appt.status]">
                                {{ statusLabels[appt.status] }}
                            </span>
                            <Link :href="route('appointments.edit', appt.id)" class="text-sm text-emerald-600">Editar</Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="calendarDays.length === 0" class="text-center text-slate-400 py-8">
                Nenhum agendamento no período.
            </div>
        </div>
    </AppLayout>
</template>
