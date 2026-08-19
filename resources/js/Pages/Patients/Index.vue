<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { digitsOnly } from '@/composables/useInputMasks.js';
import SendInviteModal from '@/Components/Patient/SendInviteModal.vue';
import PageHeader from '@/Components/Navigation/PageHeader.vue';
import { ArrowDownTrayIcon, ChevronDownIcon, TableCellsIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

// Lista de formatos do menu "Exportar" — adicionar PDF/XML/Impressão no
// futuro é só acrescentar uma entrada aqui, sem mexer no resto do template.
const EXPORT_FORMATS = [
    { format: 'excel', label: 'Excel (.xlsx)', icon: TableCellsIcon },
    { format: 'csv', label: 'CSV', icon: DocumentTextIcon },
];

const props = defineProps({
    patients: Object, // { data, pagination: { current_page, last_page, total, per_page } }
    filters: Object,
    perPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
    availableMarkers: { type: Array, default: () => [] },
    anamnesisTemplates: { type: Array, default: () => [] },
});

const showInviteModal = ref(false);

const search = ref(props.filters?.search || '');
const marker = ref(props.filters?.marker || '');
const perPage = ref(props.filters?.per_page || 10);

function applyFilters(extra = {}) {
    router.get(route('patients.index'), {
        search: search.value || undefined,
        marker: marker.value || undefined,
        per_page: perPage.value,
        ...extra,
    }, { preserveState: true, replace: true, only: ['patients', 'filters'] });
}

function goToPage(page) {
    applyFilters({ page });
}

function onPerPageChange(newPerPage) {
    perPage.value = newPerPage;
    // Muda de tamanho de página sempre volta pra página 1 — a página atual
    // pode não existir mais no novo tamanho (ex: pág. 5 de 10-em-10 não
    // existe mais ao trocar para 100-em-100).
    applyFilters({ page: 1 });
}

const exportUrl = (format) => {
    const params = new URLSearchParams({ format });
    if (search.value) params.set('search', search.value);
    if (marker.value) params.set('marker', marker.value);
    return `${route('patients.export')}?${params.toString()}`;
};

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

const statusBadgeClass = (status) => ({
    'bg-green-100 text-green-700': status === 'ativo',
    'bg-slate-100 text-slate-600': status === 'inativo',
    'bg-red-100 text-red-700': status === 'falecido',
});

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—';
</script>

<template>
    <AppLayout>
        <template #pageHeader>
            <PageHeader title="Pacientes" description="Gerencie os pacientes da clínica.">
                <button type="button" @click="showInviteModal = true"
                        class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
                    Enviar cadastro ao paciente
                </button>
                <Link :href="route('patients.create')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
                    + Novo Paciente
                </Link>
            </PageHeader>
        </template>

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
            <select v-if="availableMarkers.length" v-model="marker" @change="applyFilters()"
                    class="border rounded-lg px-3 py-2 text-sm text-slate-600">
                <option value="">Todos os marcadores</option>
                <option v-for="m in availableMarkers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
        </div>

        <!-- Desktop/tablet: tabela completa (mesmo breakpoint em que a Sidebar deixa de ser drawer) -->
        <div class="hidden lg:block bg-white rounded-2xl border overflow-hidden shadow-sm">
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
                        <td class="p-4 text-slate-600">{{ formatDate(patient.created_at) }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs rounded-full" :class="statusBadgeClass(patient.status)">
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

        <!-- Mobile/tablet estreito: cards -->
        <div class="lg:hidden space-y-3">
            <div v-for="patient in patients.data" :key="patient.id"
                 class="bg-white rounded-2xl border shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="route('patients.show', patient.id)" class="font-semibold text-emerald-700 hover:underline block truncate">
                            {{ patient.nome }} {{ patient.sobrenome }}
                        </Link>
                        <p v-if="patient.responsible_professional" class="text-xs text-slate-400 mt-0.5 truncate">
                            {{ patient.responsible_professional.name }}
                        </p>
                    </div>
                    <span class="shrink-0 px-2.5 py-0.5 text-xs rounded-full" :class="statusBadgeClass(patient.status)">
                        {{ patient.status || 'ativo' }}
                    </span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Telefone</dt>
                        <dd class="text-slate-700 mt-0.5">{{ patient.telefone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Idade</dt>
                        <dd class="text-slate-700 mt-0.5">{{ patient.idade != null ? `${patient.idade} anos` : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">CPF</dt>
                        <dd class="text-slate-700 mt-0.5">{{ patient.cpf ? digitsOnly(patient.cpf) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Paciente desde</dt>
                        <dd class="text-slate-700 mt-0.5">{{ formatDate(patient.created_at) }}</dd>
                    </div>
                </dl>
                <div class="mt-3 flex items-center gap-4 border-t border-slate-100 pt-3">
                    <Link :href="route('patients.edit', patient.id)" :cache-for="0" class="text-sm font-medium text-slate-600 hover:text-slate-900">Editar</Link>
                    <button @click="deletePatient(patient)" class="text-sm font-medium text-red-600 hover:text-red-700">Excluir</button>
                </div>
            </div>

            <div v-if="patients.data.length === 0" class="bg-white rounded-2xl border p-12 text-center text-slate-400">
                Nenhum paciente encontrado.
            </div>
        </div>

        <div v-if="patients.data.length > 0"
             class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <div class="justify-self-center sm:justify-self-start">
                <NavbarDropdown align="left" width="w-44" direction="up">
                    <template #trigger>
                        <button type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors">
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Exportar
                            <ChevronDownIcon class="w-3.5 h-3.5" />
                        </button>
                    </template>
                    <template #default="{ close }">
                        <a v-for="f in EXPORT_FORMATS" :key="f.format"
                           :href="exportUrl(f.format)" @click="close"
                           class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                            <component :is="f.icon" class="w-4 h-4 text-slate-400 shrink-0" />
                            {{ f.label }}
                        </a>
                    </template>
                </NavbarDropdown>
            </div>

            <div class="justify-self-center">
                <Pagination :pagination="patients.pagination" :bordered="false" @change="goToPage" />
            </div>

            <div class="justify-self-center sm:justify-self-end">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    Itens por página:
                    <select :value="perPage" @change="onPerPageChange(Number($event.target.value))"
                            class="border rounded-lg px-2 py-1 text-xs text-slate-600">
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </label>
            </div>
        </div>
    </AppLayout>
</template>

