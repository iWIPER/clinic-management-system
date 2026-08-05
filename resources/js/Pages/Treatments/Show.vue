<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    treatment: Object,
    stats: Object,
    auditLogs: Array,
    breadcrumb: Array,
    hasLinkedAttendances: Boolean,
    linkedDocuments: { type: Array, default: () => [] },
});

const STATUS_DOT = {
    slate: 'bg-slate-400', blue: 'bg-blue-500', amber: 'bg-amber-500', teal: 'bg-teal-500', red: 'bg-red-500',
};

const page = usePage();
const showDeleteModal = ref(false);
const showLinkedModal = ref(false);

const flashError = computed(() => page.props.flash?.error);
const canDelete = computed(() => !props.hasLinkedAttendances);

if (flashError.value === 'linked_attendances') {
    showLinkedModal.value = true;
}

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('pt-BR');
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDuration = (minutes) => {
    if (!minutes) return '—';
    return `${minutes} min`;
};

const tipoLabel = (tipo) => {
    const map = { procedimento: 'Procedimento', variacao: 'Variação', grupo: 'Grupo' };
    return map[tipo] || tipo;
};

const openDelete = () => {
    if (!canDelete.value) {
        showLinkedModal.value = true;
        return;
    }
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    router.delete(route('treatments.destroy', props.treatment.id), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
};

const deactivate = () => {
    if (confirm('Desativar este procedimento? Ele não aparecerá em novos agendamentos.')) {
        router.post(route('treatments.deactivate', props.treatment.id));
    }
};

const reactivate = () => {
    router.post(route('treatments.reactivate', props.treatment.id));
};
</script>

<template>
    <Head :title="treatment.nome" />

    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <nav class="flex items-center gap-2 text-sm text-gray-500 flex-wrap">
                <template v-for="(crumb, index) in breadcrumb" :key="index">
                    <span v-if="index > 0" class="text-gray-300">→</span>
                    <Link
                        v-if="crumb.href"
                        :href="crumb.href"
                        class="hover:text-indigo-600 transition"
                    >{{ crumb.label }}</Link>
                    <span v-else class="text-gray-900 font-medium">{{ crumb.label }}</span>
                </template>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span
                        v-if="treatment.cor"
                        class="w-4 h-4 rounded-full shrink-0 mt-1.5 ring-2 ring-white shadow"
                        :style="{ backgroundColor: treatment.cor }"
                    />
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ treatment.nome }}</h1>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <span class="text-sm text-gray-500">{{ treatment.categoria || treatment.especialidade }}</span>
                            <span v-if="treatment.tipo" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                {{ tipoLabel(treatment.tipo) }}
                            </span>
                            <span
                                class="text-xs px-2 py-0.5 rounded-full font-medium"
                                :class="treatment.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                            >
                                {{ treatment.ativo ? 'Ativo' : 'Desativado' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('treatments.edit', treatment.id)"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >Editar</Link>
                    <button
                        v-if="treatment.ativo"
                        type="button"
                        @click="deactivate"
                        class="px-4 py-2 bg-white border border-amber-300 rounded-lg text-sm font-medium text-amber-700 hover:bg-amber-50"
                    >Desativar</button>
                    <button
                        v-else
                        type="button"
                        @click="reactivate"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700"
                    >Reativar</button>
                    <button
                        type="button"
                        @click="openDelete"
                        class="px-4 py-2 bg-white border border-red-300 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50"
                    >Excluir</button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Utilizações</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.usage_count }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Faturamento</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(stats.total_revenue) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Última realização</p>
                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ formatDate(stats.last_used_at) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tempo médio realizado</p>
                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ formatDuration(stats.avg_duration_minutes) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Detalhes do procedimento</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Nome</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ treatment.nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Categoria</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ treatment.categoria || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Especialidade</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ treatment.especialidade }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Duração média</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatDuration(treatment.duracao_padrao) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tempo para inatividade</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">
                            {{ treatment.inatividade_meses ? `${treatment.inatividade_meses} meses` : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Preço sugerido</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatCurrency(treatment.preco_base) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Preço médio praticado</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatCurrency(stats.avg_practiced_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Atendimentos concluídos</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ stats.completed_appointments_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Última utilização</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatDate(stats.last_used_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Data de criação</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatDate(treatment.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Última alteração</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ formatDate(treatment.updated_at) }}</dd>
                    </div>
                    <div v-if="!treatment.ativo" class="sm:col-span-2">
                        <dt class="text-gray-500">Desativado em</dt>
                        <dd class="font-medium text-gray-600 mt-0.5">{{ formatDateTime(treatment.deactivated_at) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Descrição</dt>
                        <dd class="text-gray-700 mt-1 leading-relaxed whitespace-pre-line">{{ treatment.descricao || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="linkedDocuments.length" class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Documentos relacionados</h2>
                <ul class="space-y-3">
                    <li
                        v-for="doc in linkedDocuments"
                        :key="doc.id"
                        class="flex items-center justify-between gap-4 text-sm border-b border-gray-100 pb-3 last:border-0 last:pb-0"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="STATUS_DOT[doc.status_color] || STATUS_DOT.slate" />
                            <div class="min-w-0">
                                <Link :href="route('patients.documents.show', [doc.patient_id, doc.id])" class="font-medium text-gray-900 hover:text-teal-600 transition-colors truncate block">{{ doc.template_name }}</Link>
                                <p class="text-gray-500 text-xs mt-0.5">{{ doc.patient_name }} · {{ formatDate(doc.created_at) }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500 shrink-0">{{ doc.status_label }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="auditLogs.length" class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Histórico de alterações</h2>
                <ul class="space-y-4">
                    <li
                        v-for="log in auditLogs"
                        :key="log.id"
                        class="flex gap-4 text-sm border-b border-gray-100 pb-4 last:border-0 last:pb-0"
                    >
                        <div class="shrink-0 w-36 text-gray-500 text-xs">{{ formatDateTime(log.created_at) }}</div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900">{{ log.user_name }}</p>
                            <p class="text-gray-600 mt-0.5">{{ log.summary || log.action_label }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showDeleteModal = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900">Excluir procedimento</h3>
                <p class="text-sm text-gray-600 mt-2">Tem certeza que deseja excluir <strong>{{ treatment.nome }}</strong>? Esta ação não pode ser desfeita.</p>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
                    <button type="button" @click="confirmDelete" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Excluir</button>
                </div>
            </div>
        </div>

        <div v-if="showLinkedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showLinkedModal = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 border-t-4 border-red-500">
                <h3 class="text-lg font-semibold text-red-700">Não é possível excluir este procedimento.</h3>
                <p class="text-sm text-gray-600 mt-3">
                    Existem atendimentos associados a este procedimento.
                </p>
                <p class="text-sm text-gray-600 mt-2">
                    Para preservar o histórico clínico e financeiro da clínica, utilize a opção <strong>DESATIVAR</strong>.
                </p>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showLinkedModal = false" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
                    <button type="button" @click="showLinkedModal = false" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Entendi</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>