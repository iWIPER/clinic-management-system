<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    patient:             Object,
    clinicId:            Number,
    isDriveConnected:    Boolean,
    storageQuota:        Object,
    disclaimerConfirmed: Boolean,
    driveActivityLogs:   Array,
});

// ─── Flash ────────────────────────────────────────────────────────────────────
const page  = usePage();
const flash = computed(() => page.props.flash ?? {});

// ─── Categorias odontológicas ─────────────────────────────────────────────────
const CATEGORIES = {
    'Fotografias Clínicas': [
        'Foto Inicial', 'Foto Final', 'Foto Extraoral', 'Foto Intraoral',
        'Foto Arcada Superior', 'Foto Arcada Inferior', 'Foto Oclusão',
        'Foto Sorriso', 'Foto Face', 'Foto Perfil',
    ],
    'Radiografias': [
        'Radiografia Panorâmica', 'Radiografia Periapical', 'Radiografia Interproximal',
        'Radiografia Oclusal', 'Telerradiografia', 'Tomografia Cone Beam',
        'Tomografia Maxila', 'Tomografia Mandíbula',
    ],
    'Documentação': [
        'Termo de Consentimento', 'Contrato', 'Documento do Paciente',
        'Documento do Responsável', 'Receita', 'Solicitação de Exame', 'Encaminhamento',
    ],
    'Exames': [
        'Exame Laboratorial', 'Laudo', 'Relatório Clínico', 'Planejamento',
    ],
    'Ortodontia': [
        'Escaneamento Intraoral', 'Modelo Digital', 'Cefalometria', 'Planejamento Ortodôntico',
    ],
    'Outros': ['Outros'],
};

const FILTER_LABELS = ['Todas', 'Fotografias', 'Radiografias', 'Documentação', 'Exames', 'Ortodontia', 'Outros'];
const FILTER_MAP = {
    'Fotografias':  'Fotografias Clínicas',
    'Radiografias': 'Radiografias',
    'Documentação': 'Documentação',
    'Exames':       'Exames',
    'Ortodontia':   'Ortodontia',
    'Outros':       'Outros',
};

const EVENT_LABELS = {
    file_deleted:           'Arquivo removido',
    folder_deleted:         'Pasta removida',
    patient_folder_deleted: 'Pasta do paciente removida',
    clinic_folder_deleted:  'Pasta da clínica removida',
    root_folder_deleted:    'Pasta raiz removida',
    file_restored:          'Arquivo restaurado',
    folder_recreated:       'Estrutura recriada automaticamente',
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatBytes(bytes) {
    if (!bytes) return '0 B';
    if (bytes >= 1e12) return (bytes / 1e12).toFixed(1) + ' TB';
    if (bytes >= 1e9)  return (bytes / 1e9).toFixed(1) + ' GB';
    if (bytes >= 1e6)  return (bytes / 1e6).toFixed(1) + ' MB';
    return (bytes / 1e3).toFixed(0) + ' KB';
}

function fmtDate(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('pt-BR');
}

function fmtDateTime(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function eventLabel(type) {
    return EVENT_LABELS[type] ?? type;
}

function eventStyle(type) {
    if (type === 'folder_recreated' || type === 'file_restored') {
        return { dot: 'bg-emerald-400', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    return { dot: 'bg-red-400', badge: 'bg-red-50 text-red-700 border-red-200' };
}

// ─── Storage ──────────────────────────────────────────────────────────────────
const storageColor = computed(() => {
    const p = props.storageQuota?.percentage ?? 0;
    if (p >= 95) return { bar: 'bg-red-500',    text: 'text-red-600' };
    if (p >= 85) return { bar: 'bg-orange-400', text: 'text-orange-600' };
    if (p >= 70) return { bar: 'bg-yellow-400', text: 'text-yellow-700' };
    return       { bar: 'bg-emerald-500',       text: 'text-emerald-700' };
});

const storageAlert = computed(() => {
    const p = props.storageQuota?.percentage ?? 0;
    if (p >= 95) return { level: 'critical', title: 'Armazenamento quase esgotado',
        message: `Google Drive utilizando ${p}% da capacidade. Novos uploads podem ser bloqueados.` };
    if (p >= 85) return { level: 'warning', title: 'Importante',
        message: `Armazenamento em ${p}%. Uploads futuros podem ser afetados.` };
    if (p >= 70) return { level: 'info', title: 'Atenção',
        message: 'Google Drive utilizando mais de 70% da capacidade disponível.' };
    return null;
});

const uploadBlocked = computed(() =>
    props.storageQuota !== null && (props.storageQuota?.percentage ?? 0) >= 99
);

// ─── Counters ─────────────────────────────────────────────────────────────────
const allPhotos        = computed(() => props.patient.photos ?? []);
const activeCount      = computed(() => allPhotos.value.filter(p => p.status !== 'missing').length);
const missingCount     = computed(() => allPhotos.value.filter(p => p.status === 'missing').length);
const foldersRecreated = computed(() =>
    (props.driveActivityLogs ?? []).filter(l => l.event_type === 'folder_recreated').length
);

// ─── Upload form ──────────────────────────────────────────────────────────────
const uploadForm = useForm({ photo: null, categoria: '', subcategoria: '', dente: '' });

watch(() => uploadForm.categoria, () => { uploadForm.subcategoria = ''; });

const subcategorias = computed(() =>
    uploadForm.categoria ? (CATEGORIES[uploadForm.categoria] ?? []) : []
);

const pendingUpload = ref(false);

function submitUpload() {
    if (!props.disclaimerConfirmed) {
        pendingUpload.value = true;
        showDisclaimerModal.value = true;
        return;
    }
    doUpload();
}

function doUpload() {
    uploadForm.post(route('patients.photos.upload', props.patient.id), {
        forceFormData: true,
        onSuccess: () => uploadForm.reset(),
    });
}

// ─── Verify integrity ─────────────────────────────────────────────────────────
const verifyForm = useForm({});
function verifyDrive() {
    verifyForm.post(route('patients.drive.verify', props.patient.id));
}

// ─── Disclaimer modal ─────────────────────────────────────────────────────────
const showDisclaimerModal = ref(false);
const disclaimerChecked   = ref(false);
const disclaimerForm      = useForm({});

function confirmDisclaimer() {
    if (!disclaimerChecked.value) return;
    disclaimerForm.post(route('drive.confirm-disclaimer'), {
        preserveState:  true,
        preserveScroll: true,
        onSuccess: () => {
            showDisclaimerModal.value = false;
            if (pendingUpload.value) { pendingUpload.value = false; doUpload(); }
        },
    });
}

// ─── Disconnect Drive ─────────────────────────────────────────────────────────
const showDisconnectModal = ref(false);
const disconnectForm      = useForm({});

function confirmDisconnect() {
    disconnectForm.post(route('google.disconnect', { clinic: props.clinicId }), {
        onSuccess: () => { showDisconnectModal.value = false; },
    });
}

// ─── Legal note ───────────────────────────────────────────────────────────────
const showLegalNote = ref(false);

// ─── Gallery ─────────────────────────────────────────────────────────────────
const brokenPhotos = ref(new Set());
function onImgError(id) {
    brokenPhotos.value = new Set([...brokenPhotos.value, id]);
}

const activeFilter = ref('Todas');
const filteredPhotos = computed(() => {
    if (activeFilter.value === 'Todas') return allPhotos.value;
    const group = FILTER_MAP[activeFilter.value];
    return allPhotos.value.filter(p => p.categoria === group);
});

// ─── Preview modal ────────────────────────────────────────────────────────────
const selectedPhoto = ref(null);
function openModal(photo) {
    if (photo.status === 'missing') return;
    selectedPhoto.value = photo;
}
function closeModal() { selectedPhoto.value = null; }

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (selectedPhoto.value)            closeModal();
        else if (showDisclaimerModal.value) showDisclaimerModal.value = false;
        else if (showDisconnectModal.value) showDisconnectModal.value = false;
    }
}
onMounted(()       => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// ─── Address ──────────────────────────────────────────────────────────────────
const streetLine = computed(() => {
    const parts = [props.patient.logradouro, props.patient.numero].filter(Boolean);
    return parts.length ? parts.join(', ') : null;
});
const cityStateLine = computed(() => {
    const { cidade, estado } = props.patient;
    if (cidade && estado) return `${cidade} (${estado})`;
    return cidade || estado || null;
});
const hasAddress = computed(() =>
    streetLine.value || props.patient.complemento || props.patient.bairro
    || cityStateLine.value || props.patient.cep
);
</script>

<template>
    <AppLayout>
        <!-- Cabeçalho -->
        <div class="mb-6 flex justify-between">
            <div>
                <h1 class="text-3xl font-semibold">{{ patient.nome }} {{ patient.sobrenome }}</h1>
                <div class="text-sm text-slate-500">{{ patient.telefone || 'Sem telefone' }}</div>
            </div>
            <div class="flex gap-3">
                <Link :href="route('patients.prontuario', patient.id)"
                      class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Prontuário
                </Link>
                <Link :href="route('patients.edit', patient.id)" :cache-for="0" class="px-4 py-2 border rounded-lg">Editar</Link>
                <Link :href="route('patients.index')" class="px-4 py-2 text-slate-500">← Voltar à lista</Link>
            </div>
        </div>

        <!-- Dados pessoais + sidebar -->
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 bg-white rounded-2xl border p-6">
                <h3 class="font-medium mb-4">Dados Pessoais</h3>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div><dt class="text-slate-500">Nascimento</dt><dd>{{ fmtDate(patient.nascimento) || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd>{{ patient.status }}</dd></div>
                    <div><dt class="text-slate-500">Documento</dt><dd>{{ patient.doc_tipo?.toUpperCase() }} {{ patient.doc_numero || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd>{{ patient.email || '—' }}</dd></div>
                </dl>
                <h3 class="font-medium mt-8 mb-3">Endereço</h3>
                <div v-if="hasAddress" class="text-sm text-slate-700 space-y-1">
                    <p v-if="streetLine">{{ streetLine }}</p>
                    <p v-if="patient.complemento">{{ patient.complemento }}</p>
                    <p v-if="patient.bairro">{{ patient.bairro }}</p>
                    <p v-if="cityStateLine">{{ cityStateLine }}</p>
                    <p v-if="patient.cep" class="mt-1">{{ patient.cep }}</p>
                </div>
                <p v-else class="text-sm text-slate-500">—</p>
            </div>
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border p-6">
                    <h3 class="font-medium mb-3">Contato de Emergência</h3>
                    <p class="text-sm">{{ patient.contato_emergencia_nome || 'Não informado' }}</p>
                    <p class="text-sm text-slate-600">{{ patient.contato_emergencia_telefone || '' }}</p>
                </div>
                <div class="bg-white rounded-2xl border p-6">
                    <h3 class="font-medium mb-4">Próximas Ações</h3>
                    <Link :href="route('appointments.create') + '?patient_id=' + patient.id"
                          class="block w-full text-center bg-emerald-600 text-white py-2 rounded-lg mb-2">
                        + Agendar Consulta
                    </Link>
                    <Link :href="route('patients.edit', patient.id)" :cache-for="0"
                          class="block w-full text-center border py-2 rounded-lg">
                        Editar Ficha
                    </Link>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!--  FOTOS CLÍNICAS                                                    -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="mt-8 bg-white rounded-2xl border p-6">

            <!-- Cabeçalho + botão verificar -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Fotos Clínicas
                        <span class="ml-1.5 text-sm font-normal text-slate-400">({{ allPhotos.length }})</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Armazenadas no Google Drive da clínica</p>
                </div>
                <button v-if="isDriveConnected"
                        @click="verifyDrive"
                        :disabled="verifyForm.processing"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors">
                    <svg class="w-3.5 h-3.5" :class="verifyForm.processing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ verifyForm.processing ? 'Verificando...' : 'Verificar Drive' }}
                </button>
            </div>

            <!-- Contadores de integridade -->
            <div v-if="isDriveConnected && allPhotos.length > 0" class="mb-5 flex flex-wrap gap-2">
                <div class="flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Fotos ativas: {{ activeCount }}
                </div>
                <div v-if="missingCount > 0"
                     class="flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                    Indisponíveis: {{ missingCount }}
                </div>
                <div v-if="foldersRecreated > 0"
                     class="flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    Pastas recriadas: {{ foldersRecreated }}
                </div>
            </div>

            <!-- Card Google Drive (com quota) -->
            <div v-if="isDriveConnected && storageQuota" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4.433 22l4.327-7.479H22L17.673 22H4.433zM0 14.946L4.327 7.5l4.327 7.446H0zm9.213-7.446L13.54 0h8.654l-4.327 7.5H9.213z"/>
                        </svg>
                        <span class="text-sm font-medium text-slate-700">Google Drive da Clínica</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="https://one.google.com/storage" target="_blank" rel="noopener"
                           class="text-xs text-slate-500 hover:text-slate-700 underline underline-offset-2 flex items-center gap-1">
                            Gerenciar
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                        <button @click="showDisconnectModal = true"
                                class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 115.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            Desconectar
                        </button>
                    </div>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-200 overflow-hidden mb-2">
                    <div class="h-full rounded-full transition-all duration-500" :class="storageColor.bar"
                         :style="{ width: storageQuota.percentage + '%' }" />
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Usado: <strong :class="storageColor.text">{{ formatBytes(storageQuota.usage_bytes) }}</strong></span>
                    <span :class="storageColor.text + ' font-semibold'">{{ storageQuota.percentage }}%</span>
                    <span>Total: {{ formatBytes(storageQuota.limit_bytes) }}</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Disponível: {{ formatBytes(storageQuota.available_bytes) }}</p>
            </div>

            <!-- Card Google Drive (sem quota — Workspace ilimitado) -->
            <div v-else-if="isDriveConnected && !storageQuota" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-slate-700">Google Drive da Clínica</span>
                        <span class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">Conectado</span>
                    </div>
                    <button @click="showDisconnectModal = true"
                            class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 115.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Desconectar
                    </button>
                </div>
            </div>

            <!-- Alerta de armazenamento -->
            <div v-if="storageAlert" class="mb-4 rounded-lg border p-3 text-sm flex gap-2"
                 :class="storageAlert.level === 'critical' ? 'bg-red-50 border-red-200 text-red-700'
                       : storageAlert.level === 'warning'  ? 'bg-orange-50 border-orange-200 text-orange-700'
                                                           : 'bg-yellow-50 border-yellow-200 text-yellow-800'">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-semibold">{{ storageAlert.title }}</p>
                    <p class="mt-0.5">{{ storageAlert.message }}</p>
                    <a href="https://one.google.com/storage" target="_blank" rel="noopener"
                       class="mt-1 inline-block underline font-medium">Gerenciar armazenamento →</a>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash.success" class="mb-4 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                <span class="mt-0.5 shrink-0">✓</span><span>{{ flash.success }}</span>
            </div>
            <div v-if="flash.error" class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <span class="mt-0.5 shrink-0">✗</span><span>{{ flash.error }}</span>
            </div>

            <!-- Drive não conectado -->
            <div v-if="!isDriveConnected"
                 class="mb-6 flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                <svg class="w-5 h-5 shrink-0 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    Nenhuma conta Google conectada.
                    <a :href="route('google.connect')" class="ml-1 font-medium underline">Conectar agora</a>
                </div>
            </div>

            <!-- Upload bloqueado por espaço -->
            <div v-if="uploadBlocked" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-700">Upload indisponível</p>
                <p class="text-sm text-red-600 mt-0.5">O Google Drive não possui espaço disponível.</p>
                <a href="https://one.google.com/storage" target="_blank" rel="noopener"
                   class="mt-2 inline-block text-sm font-medium text-red-700 underline">
                    Gerenciar armazenamento →
                </a>
            </div>

            <!-- Formulário de upload -->
            <form v-else-if="isDriveConnected" @submit.prevent="submitUpload" class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-500 font-medium">Arquivo</label>
                    <input type="file" accept="image/*" required
                           class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200"
                           @change="e => uploadForm.photo = e.target.files[0]" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-500 font-medium">Categoria <span class="text-red-400">*</span></label>
                    <select v-model="uploadForm.categoria" required
                            class="border rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 min-w-[180px]">
                        <option value="">Selecione uma categoria</option>
                        <option v-for="(_, group) in CATEGORIES" :key="group" :value="group">{{ group }}</option>
                    </select>
                    <p v-if="uploadForm.errors.categoria" class="text-xs text-red-500">{{ uploadForm.errors.categoria }}</p>
                </div>
                <div v-if="uploadForm.categoria" class="flex flex-col gap-1">
                    <label class="text-xs text-slate-500 font-medium">Subcategoria <span class="text-red-400">*</span></label>
                    <select v-model="uploadForm.subcategoria" required
                            class="border rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 min-w-[200px]">
                        <option value="">Selecione uma subcategoria</option>
                        <option v-for="item in subcategorias" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <p v-if="uploadForm.errors.subcategoria" class="text-xs text-red-500">{{ uploadForm.errors.subcategoria }}</p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-500 font-medium">Dente</label>
                    <input type="text" v-model="uploadForm.dente" placeholder="Ex: 11"
                           class="border rounded-lg px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 w-24" />
                </div>
                <button type="submit" :disabled="uploadForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                    <svg v-if="uploadForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    {{ uploadForm.processing ? 'Enviando...' : 'Enviar para Drive' }}
                </button>
            </form>

            <div class="my-6 border-t border-slate-100"></div>

            <!-- Filtros -->
            <div v-if="allPhotos.length > 0" class="mb-5 flex flex-wrap gap-2">
                <button v-for="label in FILTER_LABELS" :key="label"
                        @click="activeFilter = label"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="activeFilter === label ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    {{ label }}
                </button>
            </div>

            <!-- Galeria -->
            <div v-if="filteredPhotos.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div v-for="photo in filteredPhotos" :key="photo.id"
                     class="group rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden transition-all duration-200"
                     :class="photo.status !== 'missing' ? 'cursor-pointer hover:shadow-md hover:border-slate-300' : 'cursor-default'"
                     @click="openModal(photo)">
                    <!-- Área da imagem -->
                    <div class="relative aspect-square overflow-hidden bg-slate-100">
                        <!-- Arquivo removido do Drive -->
                        <div v-if="photo.status === 'missing'"
                             class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-red-50 p-3 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-red-600 leading-tight">Arquivo removido<br>do Google Drive</p>
                        </div>
                        <!-- Erro de carregamento -->
                        <div v-else-if="brokenPhotos.has(photo.id)"
                             class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 gap-1">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs">Erro</span>
                        </div>
                        <!-- Imagem real -->
                        <img v-else
                             :src="route('patients.photos.view', [patient.id, photo.id])"
                             :alt="photo.subcategoria || photo.filename"
                             class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             @error="onImgError(photo.id)" />
                    </div>
                    <!-- Metadados -->
                    <div class="p-2.5">
                        <p class="text-xs font-semibold text-slate-800 truncate leading-tight">
                            {{ photo.subcategoria || '—' }}
                        </p>
                        <p class="text-xs text-slate-500 truncate mt-0.5">{{ photo.categoria || '' }}</p>
                        <p v-if="photo.taken_at" class="text-xs text-slate-400 mt-0.5">{{ fmtDate(photo.taken_at) }}</p>
                        <p v-if="photo.dente" class="text-xs text-slate-400">Dente {{ photo.dente }}</p>
                        <span v-if="photo.status === 'missing'"
                              class="mt-1.5 inline-block rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-600">
                            indisponível
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sem resultado no filtro -->
            <div v-else-if="allPhotos.length > 0 && filteredPhotos.length === 0"
                 class="flex flex-col items-center justify-center py-10 text-center text-slate-400">
                <p class="text-sm">Nenhuma foto na categoria <strong>{{ activeFilter }}</strong>.</p>
                <button @click="activeFilter = 'Todas'" class="mt-2 text-xs underline">Ver todas</button>
            </div>

            <!-- Estado vazio -->
            <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600">Nenhuma foto enviada ainda</p>
                <p class="text-xs text-slate-400 mt-1 max-w-xs">
                    Envie a primeira foto para criar automaticamente a pasta do paciente no Google Drive da clínica.
                </p>
            </div>

            <!-- Aviso legal recolhível -->
            <div v-if="allPhotos.length > 0" class="mt-8">
                <button @click="showLegalNote = !showLegalNote"
                        class="flex w-full items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 hover:bg-amber-100 transition-colors">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Informações Importantes sobre Armazenamento
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="showLegalNote ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-show="showLegalNote"
                     class="rounded-b-lg border border-t-0 border-amber-200 bg-white px-4 py-4 text-xs text-slate-600 space-y-2 leading-relaxed">
                    <p>Os arquivos clínicos são armazenados diretamente no <strong>Google Drive conectado pela clínica</strong>.</p>
                    <p>O CliniFlow não realiza armazenamento permanente desses arquivos e não possui controle sobre exclusões realizadas pelo proprietário da conta Google.</p>
                    <p>A clínica é responsável pela guarda, preservação, backup e integridade dos documentos clínicos armazenados.</p>
                    <p>A exclusão de arquivos ou da conta Google Drive pode resultar na <strong>perda permanente dos registros</strong>.</p>
                    <p>Recomendamos políticas internas de backup e retenção documental conforme as exigências legais e regulatórias aplicáveis.</p>
                </div>
            </div>

            <!-- ─── Histórico Google Drive ─────────────────────────────────── -->
            <div v-if="driveActivityLogs && driveActivityLogs.length > 0" class="mt-10">
                <div class="mb-4 flex items-center gap-3">
                    <h4 class="text-sm font-semibold text-slate-900">Histórico Google Drive</h4>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                        {{ driveActivityLogs.length }}
                    </span>
                </div>

                <div class="relative">
                    <div class="absolute left-[7px] top-2 bottom-2 w-px bg-slate-200"></div>

                    <div class="space-y-0">
                        <div v-for="log in driveActivityLogs" :key="log.id" class="flex gap-4 pb-5">
                            <div class="relative shrink-0 mt-1.5">
                                <span class="flex h-3.5 w-3.5 rounded-full border-2 border-white shadow-sm ring-1 ring-slate-100"
                                      :class="eventStyle(log.event_type).dot"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-[11px] font-medium rounded-full border px-2 py-0.5"
                                          :class="eventStyle(log.event_type).badge">
                                        {{ eventLabel(log.event_type) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ fmtDateTime(log.created_at) }}</span>
                                </div>
                                <p v-if="log.description" class="text-xs text-slate-600 leading-relaxed">
                                    {{ log.description }}
                                </p>
                                <div v-if="log.metadata" class="mt-1.5 space-y-0.5">
                                    <p v-if="log.metadata.filename" class="text-xs text-slate-500">
                                        <span class="text-slate-400">Arquivo:</span> {{ log.metadata.filename }}
                                    </p>
                                    <p v-if="log.metadata.subcategoria || log.metadata.categoria" class="text-xs text-slate-500">
                                        <span class="text-slate-400">Tipo:</span>
                                        {{ log.metadata.subcategoria || log.metadata.categoria }}
                                    </p>
                                    <p v-if="log.metadata.folder_path" class="text-xs text-slate-500">
                                        <span class="text-slate-400">Pasta:</span>
                                        <code class="ml-1 rounded bg-slate-100 px-1 py-0.5 font-mono text-slate-700">{{ log.metadata.folder_path }}</code>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observações -->
        <div class="mt-8 bg-white rounded-2xl border p-6">
            <h3 class="font-medium mb-3">Observações</h3>
            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ patient.observacoes || '—' }}</p>
        </div>

        <!-- Histórico de Atendimentos -->
        <div class="mt-8 bg-white rounded-2xl border p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-medium">Histórico de Atendimentos</h3>
                <div class="flex gap-3">
                    <Link :href="route('patients.prontuario', patient.id)"
                          class="text-xs text-teal-600 hover:text-teal-700 font-medium">
                        Prontuário completo →
                    </Link>
                    <Link :href="route('clinical-records.index', { patient_id: patient.id })"
                          class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                        Ver todos →
                    </Link>
                </div>
            </div>

            <div v-if="patient.clinical_records?.length" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 text-left font-medium text-slate-600">Data</th>
                            <th class="p-3 text-left font-medium text-slate-600">Procedimento</th>
                            <th class="p-3 text-left font-medium text-slate-600">Profissional</th>
                            <th class="p-3 text-left font-medium text-slate-600">Valor</th>
                            <th class="p-3 text-right font-medium text-slate-600">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="rec in patient.clinical_records" :key="rec.id" class="hover:bg-slate-50/50">
                            <td class="p-3 text-slate-700">
                                {{ rec.finished_at ? new Date(rec.finished_at).toLocaleDateString('pt-BR') : '—' }}
                            </td>
                            <td class="p-3 font-medium text-slate-800">{{ rec.procedure_name }}</td>
                            <td class="p-3 text-slate-600">{{ rec.professional?.name || '—' }}</td>
                            <td class="p-3 text-slate-700">
                                {{ Number(rec.price || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
                            </td>
                            <td class="p-3 text-right">
                                <Link :href="route('clinical-records.show', rec.id)"
                                      class="text-emerald-600 hover:text-emerald-800 font-medium text-xs">
                                    Detalhes →
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500">Nenhum atendimento concluído registrado ainda.</p>
        </div>
    </AppLayout>

    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <!--  MODAL — Preview de foto                                                -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="selectedPhoto"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
                 @click.self="closeModal">
                <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b px-5 py-3 shrink-0">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ selectedPhoto.subcategoria || selectedPhoto.filename }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ patient.nome }} {{ patient.sobrenome }}
                                <template v-if="selectedPhoto.categoria"> · {{ selectedPhoto.categoria }}</template>
                                <template v-if="selectedPhoto.dente"> · Dente {{ selectedPhoto.dente }}</template>
                                <template v-if="selectedPhoto.taken_at"> · {{ fmtDate(selectedPhoto.taken_at) }}</template>
                            </p>
                        </div>
                        <button @click="closeModal"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex flex-1 items-center justify-center overflow-auto bg-slate-50 p-6">
                        <img :src="route('patients.photos.view', [patient.id, selectedPhoto.id])"
                             :alt="selectedPhoto.subcategoria || selectedPhoto.filename"
                             class="max-h-full max-w-full rounded-lg object-contain shadow-sm" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <!--  MODAL — Confirmação de armazenamento                                   -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showDisclaimerModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                 @click.self="showDisclaimerModal = false">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4">
                        <h2 class="text-base font-semibold text-white">Confirmação de Armazenamento</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Leia antes de continuar</p>
                    </div>
                    <div class="px-6 py-5 space-y-3 text-sm text-slate-700">
                        <p class="font-medium text-slate-900">Antes de continuar, confirme que compreende:</p>
                        <ul class="space-y-2">
                            <li class="flex items-start gap-2"><span class="text-emerald-600 mt-0.5 shrink-0">✓</span>Os arquivos serão armazenados no <strong>Google Drive da sua clínica</strong>.</li>
                            <li class="flex items-start gap-2"><span class="text-emerald-600 mt-0.5 shrink-0">✓</span>Você é responsável pela guarda e preservação desses arquivos.</li>
                            <li class="flex items-start gap-2"><span class="text-emerald-600 mt-0.5 shrink-0">✓</span>O CliniFlow não controla exclusões realizadas pelo proprietário da conta Google.</li>
                            <li class="flex items-start gap-2"><span class="text-emerald-600 mt-0.5 shrink-0">✓</span>A exclusão pode resultar na <strong>perda permanente dos registros</strong>.</li>
                        </ul>
                    </div>
                    <div class="border-t px-6 py-4 bg-slate-50">
                        <label class="flex items-center gap-3 cursor-pointer select-none text-sm text-slate-700 mb-4">
                            <input type="checkbox" v-model="disclaimerChecked"
                                   class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            Li e compreendi estas informações.
                        </label>
                        <div class="flex justify-end gap-3">
                            <button @click="showDisclaimerModal = false; pendingUpload = false"
                                    class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Cancelar</button>
                            <button @click="confirmDisclaimer"
                                    :disabled="!disclaimerChecked || disclaimerForm.processing"
                                    class="px-5 py-2 rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                {{ disclaimerForm.processing ? 'Confirmando...' : 'Continuar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <!--  MODAL — Desconectar Google Drive                                       -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showDisconnectModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                 @click.self="showDisconnectModal = false">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                    <div class="bg-red-600 px-6 py-4 flex items-center gap-3">
                        <svg class="w-5 h-5 text-red-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h2 class="text-base font-semibold text-white">Desconectar Google Drive</h2>
                            <p class="text-xs text-red-200 mt-0.5">Ação irreversível até nova conexão</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-4 text-sm text-slate-700">
                        <p>Você está prestes a desconectar o Google Drive utilizado para armazenar os documentos clínicos desta clínica.</p>
                        <ul class="space-y-1.5 text-slate-600">
                            <li class="flex items-start gap-2"><span class="text-slate-400 mt-0.5 shrink-0">•</span>Novos uploads serão bloqueados.</li>
                            <li class="flex items-start gap-2"><span class="text-slate-400 mt-0.5 shrink-0">•</span>As imagens continuarão existindo na sua conta Google.</li>
                            <li class="flex items-start gap-2"><span class="text-slate-400 mt-0.5 shrink-0">•</span>O CliniFlow deixará de acessar os arquivos até uma nova conexão.</li>
                        </ul>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 space-y-1.5">
                            <p class="font-semibold">Custódia dos documentos</p>
                            <p>Os documentos e imagens pertencem à clínica e permanecem no Google Drive conectado. O CliniFlow não realiza cópia permanente desses arquivos e não se responsabiliza por exclusões realizadas pelo proprietário da conta Google.</p>
                        </div>
                    </div>
                    <div class="border-t px-6 py-4 bg-slate-50 flex justify-end gap-3">
                        <button @click="showDisconnectModal = false"
                                class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Cancelar</button>
                        <button @click="confirmDisconnect"
                                :disabled="disconnectForm.processing"
                                class="px-5 py-2 rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 transition-colors flex items-center gap-2">
                            <svg v-if="disconnectForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            {{ disconnectForm.processing ? 'Desconectando...' : 'Desconectar Google Drive' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
