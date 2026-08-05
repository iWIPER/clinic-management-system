<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { digitsOnly } from '@/composables/useInputMasks.js';
import SendInviteModal from '@/Components/Patient/SendInviteModal.vue';

const props = defineProps({
    patients: Object, // paginated
    filters: Object,
    availableMarkers: { type: Array, default: () => [] },
    anamnesisTemplates: { type: Array, default: () => [] },
});

const showInviteModal = ref(false);

const search = ref(props.filters?.search || '');
const marker = ref(props.filters?.marker || '');

function applyFilters() {
    router.get(route('patients.index'), {
        search: search.value || undefined,
        marker: marker.value || undefined,
    }, { preserveState: true, replace: true, only: ['patients', 'filters'] });
}

// Debounce só reage a digitação real (evento "input" do DOM) — mutações
// programáticas de search.value (ex.: clearSearch) não disparam esse
// evento, então não há corrida a resolver nem timer fantasma a cancelar.
let searchDebounce = null;
function scheduleSearch() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 350);
}

const clearSearch = () => {
    clearTimeout(searchDebounce);
    search.value = '';
    applyFilters();
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
            <div class="flex items-center gap-2">
                <button type="button" @click="showInviteModal = true"
                        class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2">
                    Enviar cadastro ao paciente
                </button>
                <Link :href="route('patients.create')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2">
                    + Novo Paciente
                </Link>
            </div>
        </div>

        <SendInviteModal :show="showInviteModal" :anamnesis-templates="anamnesisTemplates" @close="showInviteModal = false" />

        <div class="mb-4 flex gap-2">
            <div class="relative flex-1">
                <input
                    v-model="search"
                    @input="scheduleSearch"
                    @keyup.esc="clearSearch"
                    type="text"
                    placeholder="Buscar por nome, CPF ou telefone..."
                    class="w-full border rounded-lg px-4 py-2 pr-9 text-sm"
                />
                <button
                    v-if="search"
                    @click="clearSearch"
                    type="button"
                    title="Limpar busca"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                >
                    ×
                </button>
            </div>
            <select v-if="availableMarkers.length" v-model="marker" @change="applyFilters"
                    class="border rounded-lg px-3 py-2 text-sm text-slate-600">
                <option value="">Todos os marcadores</option>
                <option v-for="m in availableMarkers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl border overflow-hidden shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b">
                        <th class="p-4 text-left font-medium text-slate-600">Nome</th>
                        <th class="p-4 text-left font-medium text-slate-600">CPF</th>
                        <th class="p-4 text-left font-medium text-slate-600">Telefone</th>
                        <th class="p-4 text-left font-medium text-slate-600">Idade</th>
                        <th class="p-4 text-left font-medium text-slate-600">Paciente desde</th>
                        <th class="p-4 text-left font-medium text-slate-600">
                            <span class="flex items-center gap-1">
                                Status
                                <span
                                    title="O status automático é calculado com base no último procedimento concluído. O tempo de inatividade é definido individualmente em cada procedimento."
                                    class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[10px] font-bold cursor-help select-none"
                                >?</span>
                            </span>
                        </th>
                        <th class="p-4 text-right font-medium text-slate-600">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="patient in patients.data" :key="patient.id">
                        <td class="p-4 font-medium">
                            <Link :href="route('patients.show', patient.id)" class="text-emerald-700 hover:underline">
                                {{ patient.nome }} {{ patient.sobrenome }}
                            </Link>
                            <p v-if="patient.responsible_professional" class="text-xs text-slate-400 mt-0.5">
                                {{ patient.responsible_professional.name }}
                            </p>
                        </td>
                        <td class="p-4 text-slate-600">{{ patient.cpf ? digitsOnly(patient.cpf) : '—' }}</td>
                        <td class="p-4 text-slate-600">{{ patient.telefone || '—' }}</td>
                        <td class="p-4 text-slate-600">{{ patient.idade != null ? `${patient.idade} anos` : '—' }}</td>
                        <td class="p-4 text-slate-600">{{ patient.created_at ? new Date(patient.created_at).toLocaleDateString('pt-BR') : '—' }}</td>
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
                            <Link :href="route('patients.edit', patient.id)" :cache-for="0" class="text-slate-600 hover:text-slate-900">Editar</Link>
                            <button @click="deletePatient(patient)" class="text-red-600 hover:text-red-700">Excluir</button>
                        </td>
                    </tr>

                    <tr v-if="patients.data.length === 0">
                        <td colspan="7" class="p-12 text-center text-slate-400">
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

