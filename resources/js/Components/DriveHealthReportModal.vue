<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    report: { type: Object, required: true },
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'recreate-structure', 'reconnect']);

const showMissingDetails = ref(false);
const showOrphanDetails  = ref(false);
const ignoredOrphans     = ref(new Set());

const visibleOrphans = computed(() =>
    (props.report.orphans?.items ?? []).filter(o => !ignoredOrphans.value.has(o.drive_file_id))
);

function formatBytes(bytes) {
    if (!bytes) return '—';
    if (bytes >= 1e9) return (bytes / 1e9).toFixed(1) + ' GB';
    if (bytes >= 1e6) return (bytes / 1e6).toFixed(1) + ' MB';
    return (bytes / 1e3).toFixed(0) + ' KB';
}

function fmtDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('pt-BR');
}

const healthScore = computed(() => props.report.health_score ?? 0);

const healthBarFilled = computed(() => Math.round(healthScore.value / 10));

const healthBarColor = computed(() => {
    if (healthScore.value >= 90) return 'bg-emerald-500';
    if (healthScore.value >= 70) return 'bg-amber-500';
    return 'bg-red-500';
});

const storageLevelClass = computed(() => {
    const level = props.report.storage?.level;
    if (level === 'critical') return 'text-red-700 bg-red-50 border-red-200';
    if (level === 'warning')  return 'text-amber-800 bg-amber-50 border-amber-200';
    if (level === 'ok' || level === 'unlimited') return 'text-emerald-700 bg-emerald-50 border-emerald-200';
    return 'text-slate-600 bg-slate-50 border-slate-200';
});

function folderIcon(status) {
    if (status === 'ok') return '✓';
    if (status === 'missing') return '⚠';
    return '○';
}

function folderClass(status) {
    if (status === 'ok') return 'text-emerald-700';
    if (status === 'missing') return 'text-amber-700';
    return 'text-slate-400';
}

function permIcon(status) {
    if (status === 'ok') return '✔';
    if (status === 'fail') return '❌';
    return '○';
}

function ignoreOrphan(id) {
    ignoredOrphans.value = new Set([...ignoredOrphans.value, id]);
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show"
                 class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                 @click.self="emit('close')">
                <div class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <!-- Header -->
                    <div class="sticky top-0 z-10 border-b bg-white px-6 py-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Relatório de Integridade</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ report.patient_name }} · {{ fmtDateTime(report.checked_at) }}
                            </p>
                        </div>
                        <button @click="emit('close')"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-5 space-y-6 text-sm">
                        <!-- Status geral -->
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Status geral</h3>
                            <div class="rounded-xl border p-4"
                                 :class="report.connection?.connected ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
                                <p class="font-semibold" :class="report.connection?.connected ? 'text-emerald-800' : 'text-red-800'">
                                    {{ report.connection?.connected ? '✓ Google Drive conectado' : '✗ Google Drive não conectado' }}
                                </p>
                                <p class="text-xs mt-1" :class="report.connection?.connected ? 'text-emerald-700' : 'text-red-700'">
                                    {{ report.connection?.message }}
                                </p>
                                <p v-if="report.connection?.email" class="text-xs text-slate-500 mt-1">
                                    {{ report.connection.email }}
                                </p>
                            </div>
                        </section>

                        <!-- Espaço -->
                        <section v-if="report.connection?.connected">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Espaço</h3>
                            <div class="rounded-xl border p-4" :class="storageLevelClass">
                                <template v-if="report.storage?.status === 'unlimited'">
                                    <p class="font-medium">Armazenamento ilimitado (Google Workspace)</p>
                                    <p class="text-xs mt-1">{{ report.storage.message }}</p>
                                </template>
                                <template v-else-if="report.storage?.percentage != null">
                                    <p class="font-medium">
                                        Espaço utilizado
                                        <span class="ml-1">{{ formatBytes(report.storage.usage_bytes) }} / {{ formatBytes(report.storage.limit_bytes) }}</span>
                                        <span class="ml-2 font-bold">{{ report.storage.percentage }}%</span>
                                    </p>
                                    <div class="mt-2 h-2 rounded-full bg-white/60 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             :class="report.storage.level === 'critical' ? 'bg-red-500' : report.storage.level === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'"
                                             :style="{ width: report.storage.percentage + '%' }"/>
                                    </div>
                                    <p class="text-xs mt-2 font-medium">{{ report.storage.message }}</p>
                                </template>
                                <template v-else>
                                    <p class="text-xs">{{ report.storage?.message ?? 'Informação de espaço indisponível.' }}</p>
                                </template>
                            </div>
                        </section>

                        <!-- Estrutura de pastas -->
                        <section v-if="report.folders?.items?.length">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Estrutura de pastas</h3>
                            <div class="rounded-xl border border-slate-200 p-4 space-y-1.5">
                                <div v-for="item in report.folders.items" :key="item.key"
                                     class="flex items-start gap-2 text-xs" :class="folderClass(item.status)">
                                    <span class="shrink-0 font-bold w-4">{{ folderIcon(item.status) }}</span>
                                    <span>{{ item.label }}</span>
                                    <span v-if="item.message" class="text-amber-600 ml-1">— {{ item.message }}</span>
                                </div>
                            </div>

                            <div v-if="report.folders.has_issues"
                                 class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-xs font-semibold text-amber-900">Recuperação</p>
                                <p class="text-xs text-amber-800 mt-1">
                                    Foi detectada inconsistência. A estrutura pode ser recriada automaticamente.
                                </p>
                                <button @click="emit('recreate-structure')"
                                        class="mt-3 px-4 py-2 rounded-lg bg-amber-600 text-white text-xs font-medium hover:bg-amber-700 transition-colors">
                                    Recriar estrutura
                                </button>
                            </div>
                        </section>

                        <!-- Arquivos -->
                        <section v-if="report.files?.status !== 'skipped'">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Arquivos</h3>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap gap-4 text-xs text-slate-600 mb-2">
                                    <span>Banco: <strong class="text-slate-900">{{ report.files.db_count }}</strong> arquivos</span>
                                    <span>Drive: <strong class="text-slate-900">{{ report.files.drive_count }}</strong> arquivos ativos</span>
                                </div>
                                <p class="text-xs font-medium"
                                   :class="report.files.missing_count > 0 ? 'text-amber-700' : 'text-emerald-700'">
                                    {{ report.files.missing_count > 0 ? `⚠ ${report.files.message}` : `✓ ${report.files.message}` }}
                                </p>
                                <button v-if="report.files.missing_count > 0"
                                        @click="showMissingDetails = !showMissingDetails"
                                        class="mt-2 text-xs text-teal-600 hover:text-teal-800 font-medium underline">
                                    {{ showMissingDetails ? 'Ocultar detalhes' : 'Mostrar detalhes' }}
                                </button>
                                <div v-if="showMissingDetails && report.files.missing?.length"
                                     class="mt-3 space-y-2 border-t pt-3">
                                    <div v-for="file in report.files.missing" :key="file.id"
                                         class="rounded-lg bg-slate-50 border p-3 text-xs">
                                        <p class="font-semibold text-slate-800">{{ file.name }}</p>
                                        <p v-if="file.dente" class="text-slate-500">Dente {{ file.dente }}</p>
                                        <p class="text-slate-500">{{ fmtDate(file.taken_at) }}</p>
                                        <p class="text-red-600 mt-1">Status: {{ file.status }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Arquivos órfãos -->
                        <section v-if="report.orphans?.status !== 'skipped'">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Arquivos órfãos</h3>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap gap-4 text-xs text-slate-600 mb-2">
                                    <span>Drive: <strong>{{ report.orphans.drive_count }}</strong> arquivos</span>
                                    <span>Sistema: <strong>{{ report.orphans.system_count }}</strong> arquivos</span>
                                </div>
                                <p class="text-xs font-medium"
                                   :class="report.orphans.orphan_count > 0 ? 'text-amber-700' : 'text-emerald-700'">
                                    {{ report.orphans.orphan_count > 0
                                        ? `${report.orphans.orphan_count} arquivo(s) não cadastrados.`
                                        : report.orphans.message }}
                                </p>
                                <button v-if="report.orphans.orphan_count > 0"
                                        @click="showOrphanDetails = !showOrphanDetails"
                                        class="mt-2 text-xs text-teal-600 hover:text-teal-800 font-medium underline">
                                    {{ showOrphanDetails ? 'Ocultar detalhes' : 'Mostrar detalhes' }}
                                </button>
                                <div v-if="showOrphanDetails && visibleOrphans.length"
                                     class="mt-3 space-y-2 border-t pt-3">
                                    <div v-for="orphan in visibleOrphans" :key="orphan.drive_file_id"
                                         class="rounded-lg bg-slate-50 border p-3 flex items-center justify-between gap-3">
                                        <div class="text-xs min-w-0">
                                            <p class="font-semibold text-slate-800 truncate">{{ orphan.name }}</p>
                                            <p class="text-slate-500">{{ orphan.folder }}</p>
                                        </div>
                                        <button @click="ignoreOrphan(orphan.drive_file_id)"
                                                class="shrink-0 text-xs text-slate-500 hover:text-slate-700 px-2 py-1 border rounded">
                                            Ignorar
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-slate-400">Importar e Excluir estarão disponíveis em breve.</p>
                                </div>
                            </div>
                        </section>

                        <!-- Permissões -->
                        <section v-if="report.permissions?.items?.length">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Permissões</h3>
                            <div class="rounded-xl border border-slate-200 p-4 space-y-1">
                                <div v-for="perm in report.permissions.items" :key="perm.key"
                                     class="flex items-center gap-2 text-xs"
                                     :class="perm.status === 'fail' ? 'text-red-700' : 'text-slate-700'">
                                    <span>{{ permIcon(perm.status) }}</span>
                                    <span>{{ perm.label }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- API -->
                        <section v-if="report.api?.items?.length">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">API</h3>
                            <div class="rounded-xl border border-slate-200 p-4 space-y-1">
                                <div v-for="item in report.api.items" :key="item.key"
                                     class="flex items-center gap-2 text-xs"
                                     :class="item.status === 'fail' ? 'text-red-700' : 'text-slate-700'">
                                    <span>{{ permIcon(item.status) }}</span>
                                    <span>{{ item.label }}</span>
                                    <span v-if="item.message" class="text-red-500">— {{ item.message }}</span>
                                </div>
                                <Link v-if="report.api.reconnect_required"
                                      :href="route('google.connect')"
                                      class="inline-block mt-3 px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-medium hover:bg-slate-900">
                                    Reconectar Drive
                                </Link>
                            </div>
                        </section>

                        <!-- Saúde geral -->
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Saúde geral</h3>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-slate-800">Health Score</span>
                                    <span class="text-2xl font-bold" :class="healthScore >= 90 ? 'text-emerald-600' : healthScore >= 70 ? 'text-amber-600' : 'text-red-600'">
                                        {{ healthScore }}%
                                    </span>
                                </div>
                                <div class="flex gap-0.5">
                                    <span v-for="i in 10" :key="i"
                                          class="h-2 flex-1 rounded-sm"
                                          :class="i <= healthBarFilled ? healthBarColor : 'bg-slate-200'"/>
                                </div>
                            </div>
                        </section>

                        <!-- Recomendações -->
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Recomendações</h3>
                            <ul class="rounded-xl border border-slate-200 p-4 space-y-2">
                                <li v-for="(tip, i) in report.recommendations" :key="i"
                                    class="text-xs text-slate-700 flex gap-2">
                                    <span class="text-slate-400 shrink-0">•</span>
                                    <span>{{ tip }}</span>
                                </li>
                            </ul>
                        </section>

                        <!-- Histórico -->
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Histórico da última verificação</h3>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs text-slate-600 space-y-1">
                                <p>
                                    <span class="text-slate-400">Verificação atual:</span>
                                    {{ fmtDateTime(report.checked_at) }}
                                    <span class="text-slate-400">por</span>
                                    {{ report.checked_by?.name }}
                                </p>
                                <p v-if="report.last_verification">
                                    <span class="text-slate-400">Verificação anterior:</span>
                                    {{ fmtDateTime(report.last_verification.at) }}
                                    <span v-if="report.last_verification.by">por {{ report.last_verification.by }}</span>
                                    <span v-if="report.last_verification.health_score != null" class="text-slate-400">
                                        ({{ report.last_verification.health_score }}%)
                                    </span>
                                </p>
                            </div>
                        </section>

                        <!-- Auditoria -->
                        <section v-if="report.audit_summary?.length">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Registro da verificação</h3>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-1">
                                <p v-for="(step, i) in report.audit_summary" :key="i"
                                   class="text-xs text-slate-600">
                                    {{ step.icon }} {{ step.description }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <div class="sticky bottom-0 border-t bg-slate-50 px-6 py-4 flex justify-end">
                        <button @click="emit('close')"
                                class="px-5 py-2 rounded-lg bg-slate-800 text-sm font-medium text-white hover:bg-slate-900">
                            Fechar relatório
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>