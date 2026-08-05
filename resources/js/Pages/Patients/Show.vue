<script setup>
import InputError from '@/Components/InputError.vue'
import AppLayout from '@/Layouts/AppLayout.vue';
import DriveHealthCheckLoading from '@/Components/DriveHealthCheckLoading.vue';
import DriveHealthReportModal from '@/Components/DriveHealthReportModal.vue';
import DriveDisasterRecoveryModal from '@/Components/DriveDisasterRecoveryModal.vue';
import PatientAlertChips from '@/Components/Patient/PatientAlertChips.vue';
import PatientMarkerManager from '@/Components/Patient/PatientMarkerManager.vue';
import PatientHubTabs from '@/Components/Patient/PatientHubTabs.vue';
import PatientProfessionalsCard from '@/Components/Patient/PatientProfessionalsCard.vue';
import PatientEvolutionCard from '@/Components/Patient/PatientEvolutionCard.vue';
import InviteStatusBadge from '@/Components/Patient/InviteStatusBadge.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { maskCpf } from '@/composables/useInputMasks.js';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    patient:             Object,
    clinicId:            Number,
    clinicName:          String,
    doctorName:          String,
    isDriveConnected:    Boolean,
    storageQuota:        Object,
    disclaimerConfirmed: Boolean,
    driveActivityLogs:   Array,
    autoStatus:          Object,
    responsibleTeam:     { type: Array, default: () => [] },
    eligibleProfessionals: { type: Array, default: () => [] },
    hub:                 { type: Object, default: () => ({}) },
    latestPatientInvite: { type: Object, default: () => null },
    anamnesisHub:        { type: Object, default: () => ({ instances: [], templates: [], alerts: [] }) },
    documentHub:         { type: Object, default: () => ({ documents: [], pagination: null, templates: [] }) },
    patientNotes:              { type: Array,  default: () => [] },
    notesPagination:           { type: Object, default: () => null },
    noteAlerts:                { type: Array,  default: () => [] },
    patientMarkers:            { type: Array,  default: () => [] },
    availableMarkers:          { type: Array,  default: () => [] },
    activeTab:           { type: String, default: 'overview' },
    odontogram:          { type: Object, default: () => ({}) },
    toothStatuses:       { type: Array,  default: () => [] },
    treatmentsByTooth:   { type: Object, default: () => ({}) },
    patientTreatments:   { type: Object, default: () => ({ data: [], pagination: null }) },
    evolutionsHub:       { type: Object, default: () => ({ data: [], pagination: null }) },
    catalogTreatments:   { type: Array,  default: () => [] },
    convenios:           { type: Array,  default: () => [] },
    treatmentStatuses:   { type: Array,  default: () => [] },
});

// ─── Flash ────────────────────────────────────────────────────────────────────
const page  = usePage();
const flash = computed(() => page.props.flash ?? {});

// ─── Popover de relacionamento (cabeçalho) ────────────────────────────────────
const showRelationshipPopover = ref(false);

// ─── Sidebar recolhível (auto-retrai só na aba Tratamentos) ──────────────────
const sidebarOpen = ref(true);
function onTabChange(tabId) {
    sidebarOpen.value = tabId !== 'treatments';
}

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

const FILTER_LABELS = ['Todas', 'Fotografias', 'Radiografias', 'Documentação', 'Exames', 'Ortodontia', 'Outros', 'Removidos'];

const STRUCTURE_CATEGORIES = [
    'Fotografias Clínicas', 'Radiografias', 'Exames', 'Documentação', 'Outros',
];
const FILTER_MAP = {
    'Fotografias':  'Fotografias Clínicas',
    'Radiografias': 'Radiografias',
    'Documentação': 'Documentação',
    'Exames':       'Exames',
    'Ortodontia':   'Ortodontia',
    'Outros':       'Outros',
};

const EVENT_LABELS = {
    file_deleted:                  'Arquivo removido do Google Drive',
    file_deleted_system:           'Documento removido pelo sistema',
    file_renamed:                  'Documento editado',
    file_moved:                    'Arquivo movido de categoria',
    folder_deleted:                'Pasta removida',
    patient_folder_deleted:        'Pasta do paciente removida',
    clinic_folder_deleted:         'Pasta da clínica removida',
    root_folder_deleted:           'Pasta raiz removida',
    file_restored:                 'Arquivo restaurado',
    folder_recreated:              'Pasta recriada',
    structure_not_found:           'Estrutura anterior não encontrada',
    structure_recovery_authorized: 'Usuário autorizou a recriação',
    structure_recreated:           'Estrutura recriada com sucesso',
    upload_resumed:                'Upload retomado automaticamente',
    health_check_started:          'Verificação iniciada',
    health_check_completed:        'Verificação concluída',
    health_check_structure_ok:     'Estrutura OK',
    health_check_structure_issue:  'Estrutura inconsistente',
    health_check_files_ok:         'Arquivos consistentes',
    health_check_files_issue:      'Arquivos ausentes detectados',
    health_check_orphans:          'Arquivos órfãos detectados',
    health_check_drive_ok:         'Drive conectado',
    health_check_storage:          'Espaço verificado',
    health_check_api_ok:           'API validada',
    health_check_permissions_ok:   'Permissões validadas',
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
    if (['folder_recreated', 'file_restored', 'structure_recovery_authorized', 'structure_recreated', 'upload_resumed', 'health_check_completed', 'health_check_structure_ok', 'health_check_files_ok', 'health_check_drive_ok', 'health_check_api_ok', 'health_check_permissions_ok'].includes(type)) {
        return { dot: 'bg-emerald-400', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (['structure_not_found', 'health_check_structure_issue', 'health_check_files_issue', 'health_check_orphans'].includes(type)) {
        return { dot: 'bg-amber-400', badge: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    if (['health_check_started', 'health_check_storage'].includes(type)) {
        return { dot: 'bg-slate-400', badge: 'bg-slate-50 text-slate-700 border-slate-200' };
    }
    return { dot: 'bg-red-400', badge: 'bg-red-50 text-red-700 border-red-200' };
}

function isRemoved(photo) {
    return photo.status === 'removed' || photo.status === 'missing';
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
const activeCount      = computed(() => allPhotos.value.filter(p => !isRemoved(p)).length);
const removedCount     = computed(() => allPhotos.value.filter(p => isRemoved(p)).length);
const patientFullName  = computed(() => `${props.patient.nome} ${props.patient.sobrenome}`.trim());

// Idade só em anos (considera se o aniversário já passou este ano) — mesma
// lógica de Patients/Odontogram.vue:91-99, reaproveitada em vez de duplicar
// a versão aproximada (365.25 dias) que existe em Prontuario/Show.vue.
const patientAge = computed(() => {
    if (!props.patient.nascimento) return null;
    const birth = new Date(props.patient.nascimento);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
});

// Prioriza CPF (mascarado) — RG e Passaporte exibidos crus quando é o único
// documento disponível (doc_tipo/doc_numero são legado, não usados mais).
const formattedDoc = computed(() => {
    if (props.patient.cpf) return maskCpf(props.patient.cpf);
    if (props.patient.rg) return props.patient.rg;
    if (props.patient.passaporte) return props.patient.passaporte;
    return null;
});

// ─── Copiar telefone/CPF (cabeçalho) ───────────────────────────────────────────
// Mesmo padrão de Pages/Team/Index.vue: uma chave (não um boolean) permite
// telefone e CPF darem feedback "Copiado!" de forma independente.
const copiedKey = ref(null);
function copyToClipboard(text, key) {
    navigator.clipboard.writeText(text).then(() => {
        copiedKey.value = key;
        setTimeout(() => { copiedKey.value = null; }, 2000);
    });
}

// ─── Upload form ──────────────────────────────────────────────────────────────
const uploadForm = useForm({
    photo: null,
    categoria: '',
    subcategoria: '',
    dente: '',
    authorize_structure_recovery: false,
});

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

function doUpload(authorizeRecovery = false) {
    uploadForm.authorize_structure_recovery = authorizeRecovery;
    uploadForm.post(route('patients.photos.upload', props.patient.id), {
        forceFormData: true,
        onSuccess: (page) => {
            if (page.props.flash?.disaster_recovery_required) {
                showDisasterRecoveryModal.value = true;
                return;
            }
            showDisasterRecoveryModal.value = false;
            uploadForm.reset();
        },
    });
}

// ─── Drive Health Check ───────────────────────────────────────────────────────
const healthCheckLoading = ref(false);
const healthReport       = ref(null);
const showHealthReport   = ref(false);

const MIN_HEALTH_CHECK_MS = 2500;

function verifyDrive() {
    if (healthCheckLoading.value) return;

    healthCheckLoading.value = true;
    healthReport.value       = null;

    const startedAt = Date.now();

    window.axios.post(route('patients.drive.health-check', props.patient.id))
        .then(async (response) => {
            const elapsed = Date.now() - startedAt;
            if (elapsed < MIN_HEALTH_CHECK_MS) {
                await new Promise(r => setTimeout(r, MIN_HEALTH_CHECK_MS - elapsed));
            }
            healthReport.value     = response.data;
            showHealthReport.value = true;
            router.reload({ only: ['patient', 'driveActivityLogs'], preserveScroll: true });
        })
        .catch((error) => {
            healthReport.value = error.response?.data ?? {
                checked_at: new Date().toISOString(),
                checked_by: { name: '—' },
                patient_name: `${props.patient.nome} ${props.patient.sobrenome}`.trim(),
                health_score: 0,
                connection: {
                    connected: false,
                    status: 'error',
                    message: 'A verificação não pôde ser concluída — falha na comunicação com o servidor.',
                },
                storage: { status: 'unavailable', message: 'Não verificado nesta tentativa.' },
                folders: { status: 'unavailable', items: [], has_issues: false, can_repair: false },
                files: { status: 'unavailable', db_count: 0, drive_count: 0, missing_count: 0, missing: [], message: 'Comparação não realizada.' },
                orphans: { status: 'unavailable', drive_count: 0, system_count: 0, orphan_count: 0, items: [], message: 'Verificação não realizada.' },
                permissions: { status: 'unavailable', items: [], message: 'Permissões não testadas.' },
                api: { status: 'unavailable', items: [], reconnect_required: false, message: 'API não consultada.' },
                recommendations: [
                    'A conexão com o servidor foi interrompida antes de concluir a verificação.',
                    'Tente novamente em instantes. Se o problema persistir, reconecte o Google Drive.',
                ],
                audit_summary: [{ icon: '⚠', description: 'Verificação interrompida — falha na comunicação.' }],
                partial_failure: true,
            };
            showHealthReport.value = true;
        })
        .finally(() => {
            healthCheckLoading.value = false;
        });
}

function onHealthRecreateStructure() {
    showHealthReport.value = false;
    showDisasterRecoveryModal.value = true;
}

function closeHealthReport() {
    showHealthReport.value = false;
}

// ─── Disaster recovery modal ──────────────────────────────────────────────────
// Componente reutilizável (ver DriveDisasterRecoveryModal.vue) — também
// usado pelo card de Evoluções quando o upload de foto falha por estrutura
// do Drive ausente (ver PatientEvolutionCard.vue/EvolutionDetailModal.vue).
const showDisasterRecoveryModal = ref(false);
const disasterRecoveryModalRef  = ref(null);
const recoverForm               = useForm({});

function cancelDisasterRecovery() {
    disasterRecoveryModalRef.value?.cancel();
}

function onDisasterRecoveryClosed() {
    showDisasterRecoveryModal.value = false;
    pendingUpload.value             = false;
}

function onDisasterRecoveryConfirmed() {
    const onDone = () => disasterRecoveryModalRef.value?.finish(true);
    const onFail = () => disasterRecoveryModalRef.value?.finish(false);

    if (uploadForm.photo) {
        uploadForm.authorize_structure_recovery = true;
        uploadForm.post(route('patients.photos.upload', props.patient.id), {
            forceFormData: true,
            onSuccess: onDone,
            onError:   onFail,
        });
    } else {
        recoverForm.post(route('patients.drive.recover', props.patient.id), {
            onSuccess: onDone,
            onError:   onFail,
        });
    }
}

function onDisasterRecoveryDone() {
    uploadForm.reset();
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

// ─── Rename / Delete (2-hour window) ─────────────────────────────────────────
const renameModal    = ref({ open: false, photo: null });
const deleteModal    = ref({ open: false, photo: null });
const protectedModal = ref(false);
const renameForm     = useForm({ new_name: '', categoria: '', dente: '', description: '', observacao: '' });
const deleteForm     = useForm({});

const renameSubcategoriasPicker = ref('');
const renameSubcategorias = computed(() =>
    renameForm.categoria ? (CATEGORIES[renameForm.categoria] ?? []) : []
);
watch(() => renameForm.categoria, () => { renameSubcategoriasPicker.value = ''; });
watch(renameSubcategoriasPicker, (val) => { if (val) renameForm.new_name = val; });

const isEditable = (photo) => {
    if (!photo?.created_at) return false;
    return Date.now() - new Date(photo.created_at).getTime() < 2 * 60 * 60 * 1000;
};

const openRenameModal = (photo) => {
    if (isEditable(photo)) {
        renameForm.new_name    = photo.subcategoria || '';
        renameForm.categoria   = photo.categoria || '';
        renameForm.dente       = photo.dente || '';
        renameForm.description = photo.description || '';
        renameForm.observacao  = photo.observacao || '';
        // Pre-select the subcategoria picker when value matches a known option
        const cats = CATEGORIES[photo.categoria ?? ''] ?? [];
        renameSubcategoriasPicker.value = cats.includes(photo.subcategoria) ? (photo.subcategoria || '') : '';
        renameModal.value = { open: true, photo };
    } else {
        protectedModal.value = true;
    }
};

const openDeleteModal = (photo) => {
    if (isEditable(photo)) {
        deleteModal.value = { open: true, photo };
    } else {
        protectedModal.value = true;
    }
};

const submitRename = () => {
    renameForm.put(
        route('patients.photos.rename', [props.patient.id, renameModal.value.photo.id]),
        {
            onSuccess: () => {
                renameModal.value = { open: false, photo: null };
                renameForm.reset();
            },
        }
    );
};

const submitDelete = () => {
    deleteForm.delete(
        route('patients.photos.delete', [props.patient.id, deleteModal.value.photo.id]),
        {
            onSuccess: () => { deleteModal.value = { open: false, photo: null }; },
        }
    );
};

// ─── Gallery ─────────────────────────────────────────────────────────────────
const brokenPhotos = ref(new Set());
function onImgError(id) {
    brokenPhotos.value = new Set([...brokenPhotos.value, id]);
}

const activeFilter = ref('Todas');
const filteredPhotos = computed(() => {
    if (activeFilter.value === 'Removidos') {
        return allPhotos.value.filter(p => isRemoved(p));
    }
    const activeOnly = allPhotos.value.filter(p => !isRemoved(p));
    if (activeFilter.value === 'Todas') return activeOnly;
    const group = FILTER_MAP[activeFilter.value];
    return activeOnly.filter(p => p.categoria === group);
});

// ─── Preview modal ────────────────────────────────────────────────────────────
const selectedPhoto = ref(null);
function openModal(photo) {
    if (isRemoved(photo)) return;
    selectedPhoto.value = photo;
}
function closeModal() { selectedPhoto.value = null; }

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (selectedPhoto.value)                   closeModal();
        else if (renameModal.value.open)           renameModal.value = { open: false, photo: null };
        else if (deleteModal.value.open)           deleteModal.value = { open: false, photo: null };
        else if (protectedModal.value)             protectedModal.value = false;
        else if (showDisasterRecoveryModal.value)  cancelDisasterRecovery();
        else if (showDisclaimerModal.value)        showDisclaimerModal.value = false;
        else if (showDisconnectModal.value)        showDisconnectModal.value = false;
    }
}
onMounted(() => {
    if (flash.value.disaster_recovery_required) {
        showDisasterRecoveryModal.value = true;
    }
    window.addEventListener('keydown', onKeydown);
});
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

// ─── Timeline Google Drive ─────────────────────────────────────────────────────
const ICON_PATHS = {
    trash:   'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    check:   'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    folder:  'M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    shield:  'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    wrench:  'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    upload:  'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
    pencil:  'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    dot:     'M12 12h.01',
};

const TIMELINE_FILTERS = [
    { key: 'all',       label: 'Todos' },
    { key: 'uploads',   label: 'Uploads' },
    { key: 'edits',     label: 'Edições' },
    { key: 'structure', label: 'Estrutura' },
    { key: 'removed',   label: 'Removidos' },
    { key: 'recovered', label: 'Recuperações' },
    { key: 'errors',    label: 'Erros' },
];

const TIMELINE_FILTER_TYPES = {
    uploads:   ['upload_resumed', 'file_restored'],
    edits:     ['file_renamed', 'file_moved', 'file_deleted_system'],
    structure: ['folder_recreated', 'structure_recreated', 'structure_not_found',
                'folder_deleted', 'patient_folder_deleted', 'clinic_folder_deleted', 'root_folder_deleted'],
    removed:   ['file_deleted', 'folder_deleted', 'patient_folder_deleted',
                'clinic_folder_deleted', 'root_folder_deleted'],
    recovered: ['structure_recovery_authorized', 'structure_recreated', 'upload_resumed',
                'folder_recreated', 'file_restored'],
    errors:    ['file_deleted', 'folder_deleted', 'patient_folder_deleted',
                'clinic_folder_deleted', 'root_folder_deleted', 'structure_not_found'],
};

const CRIT_LABELS = {
    critical: 'Crítico',
    error:    'Erro',
    warning:  'Aviso',
    success:  'Sucesso',
    info:     'Informação',
};

const tlOpen        = ref(false);
const tlShowAll     = ref(false);
const tlFilter      = ref('all');
const tlSearch      = ref('');
const tlExpandedId  = ref(null);
const TIMELINE_INIT = 5;

function tlIcon(type) {
    const m = {
        file_deleted:                  { icon: 'trash',   color: 'text-red-500'     },
        folder_deleted:                { icon: 'trash',   color: 'text-red-500'     },
        patient_folder_deleted:        { icon: 'trash',   color: 'text-red-700'     },
        clinic_folder_deleted:         { icon: 'trash',   color: 'text-red-700'     },
        root_folder_deleted:           { icon: 'trash',   color: 'text-red-700'     },
        file_restored:                 { icon: 'check',   color: 'text-emerald-500' },
        folder_recreated:              { icon: 'folder',  color: 'text-blue-500'    },
        structure_not_found:           { icon: 'warning', color: 'text-amber-500'   },
        structure_recovery_authorized: { icon: 'shield',  color: 'text-orange-500'  },
        structure_recreated:           { icon: 'wrench',  color: 'text-blue-600'    },
        upload_resumed:                { icon: 'upload',  color: 'text-emerald-500' },
        file_renamed:                  { icon: 'pencil',  color: 'text-blue-500'    },
        file_moved:                    { icon: 'folder',  color: 'text-purple-500'  },
        file_deleted_system:           { icon: 'trash',   color: 'text-orange-500'  },
    };
    return m[type] ?? { icon: 'dot', color: 'text-slate-400' };
}

function tlCrit(type) {
    if (['patient_folder_deleted', 'clinic_folder_deleted', 'root_folder_deleted'].includes(type)) return 'critical';
    if (['file_deleted', 'folder_deleted'].includes(type))                                         return 'error';
    if (['structure_not_found', 'structure_recovery_authorized'].includes(type))                   return 'warning';
    if (['file_restored', 'folder_recreated', 'structure_recreated', 'upload_resumed'].includes(type)) return 'success';
    if (['file_renamed', 'file_moved'].includes(type)) return 'info';
    if (['file_deleted_system'].includes(type))        return 'warning';
    return 'info';
}

function tlCritStyle(level) {
    const s = {
        critical: { badge: 'bg-red-100 text-red-800 border-red-300',          bar: 'border-l-red-700',     text: 'text-red-800'      },
        error:    { badge: 'bg-red-50 text-red-700 border-red-200',           bar: 'border-l-red-500',     text: 'text-red-700'      },
        warning:  { badge: 'bg-amber-50 text-amber-800 border-amber-200',     bar: 'border-l-amber-500',   text: 'text-amber-800'    },
        success:  { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', bar: 'border-l-emerald-500', text: 'text-emerald-700' },
        info:     { badge: 'bg-slate-100 text-slate-600 border-slate-200',    bar: 'border-l-slate-300',   text: 'text-slate-600'    },
    };
    return s[level] ?? s.info;
}

function tlDateLabel(iso) {
    if (!iso) return 'Data desconhecida';
    const d    = new Date(iso);
    const now  = new Date();
    const yest = new Date(now); yest.setDate(now.getDate() - 1);
    if (d.toDateString() === now.toDateString())  return 'Hoje';
    if (d.toDateString() === yest.toDateString()) return 'Ontem';
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

const tlLastEvent = computed(() => {
    const logs = props.driveActivityLogs;
    if (!logs?.length) return null;
    const d    = new Date(logs[0].created_at);
    const now  = new Date();
    const yest = new Date(now); yest.setDate(now.getDate() - 1);
    const t    = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === now.toDateString())  return `Hoje às ${t}`;
    if (d.toDateString() === yest.toDateString()) return `Ontem às ${t}`;
    return fmtDateTime(logs[0].created_at);
});

const tlFiltered = computed(() => {
    const logs = props.driveActivityLogs ?? [];
    let r = logs;
    if (tlFilter.value !== 'all') {
        const ok = TIMELINE_FILTER_TYPES[tlFilter.value] ?? [];
        r = r.filter(l => ok.includes(l.event_type));
    }
    const q = tlSearch.value.toLowerCase().trim();
    if (q) r = r.filter(l =>
        eventLabel(l.event_type).toLowerCase().includes(q) ||
        (l.description ?? '').toLowerCase().includes(q) ||
        JSON.stringify(l.metadata ?? {}).toLowerCase().includes(q)
    );
    return r;
});

const tlHidden  = computed(() => Math.max(0, tlFiltered.value.length - TIMELINE_INIT));
const tlVisible = computed(() =>
    tlShowAll.value ? tlFiltered.value : tlFiltered.value.slice(0, TIMELINE_INIT)
);

const tlGrouped = computed(() => {
    const order = [], map = {};
    for (const log of tlVisible.value) {
        const g = tlDateLabel(log.created_at);
        if (!map[g]) { map[g] = []; order.push(g); }
        map[g].push(log);
    }
    return order.map(date => ({ date, logs: map[date] }));
});

function tlToggle(id) { tlExpandedId.value = tlExpandedId.value === id ? null : id; }

function tlReset() {
    tlFilter.value     = 'all';
    tlSearch.value     = '';
    tlShowAll.value    = false;
    tlExpandedId.value = null;
}

function tlRefresh() {
    router.reload({ only: ['driveActivityLogs', 'patient'] });
}

function tlExport() {
    const logs = props.driveActivityLogs ?? [];
    const rows = [
        ['Data', 'Evento', 'Criticidade', 'Descrição', 'Arquivo', 'Categoria', 'Paciente'].join(';'),
        ...logs.map(l => [
            fmtDateTime(l.created_at),
            eventLabel(l.event_type),
            CRIT_LABELS[tlCrit(l.event_type)],
            l.description ?? '',
            l.metadata?.filename ?? '',
            l.metadata?.categoria ?? '',
            l.metadata?.patient_name ?? '',
        ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(';')),
    ];
    const blob = new Blob(['﻿' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `drive-historico-${props.patient.nome}-${props.patient.sobrenome}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <AppLayout>
        <!-- Cabeçalho -->
        <div class="mb-6 flex justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-semibold">{{ patient.nome }} {{ patient.sobrenome }}</h1>
                    <InviteStatusBadge v-if="latestPatientInvite" :invite="latestPatientInvite" />
                    <div v-if="hub?.summary?.relationship"
                         class="relative inline-block"
                         @mouseenter="showRelationshipPopover = true"
                         @mouseleave="showRelationshipPopover = false">
                        <button type="button"
                                @click="showRelationshipPopover = !showRelationshipPopover"
                                class="group inline-flex items-center justify-center w-9 h-9 rounded-full text-teal-600 bg-teal-50 hover:bg-teal-100 hover:text-teal-700 hover:shadow-sm transition-all duration-150"
                                title="Histórico do paciente">
                            <svg class="w-[18px] h-[18px] transition-transform duration-150 group-hover:scale-110"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 012-2h2a2 2 0 012 2v6m-9 0h10a2 2 0 002-2V7.5M4 19a2 2 0 01-2-2v-4a2 2 0 012-2h1m14-4l-3-3m0 0l-3 3m3-3v9" />
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-1"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition-all duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95">
                            <div v-if="showRelationshipPopover"
                                 class="absolute z-20 top-full left-0 mt-2 w-72 bg-white rounded-2xl border border-slate-200 shadow-xl shadow-slate-200/60 p-4 text-sm">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Histórico do paciente</p>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-slate-500">Comparecimentos</dt>
                                        <dd class="font-semibold text-slate-900">{{ hub.summary.relationship.attendances ?? 0 }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-slate-500">Faltas</dt>
                                        <dd class="font-semibold text-slate-900">{{ hub.summary.relationship.no_shows ?? 0 }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-slate-500">Cancelamentos</dt>
                                        <dd class="font-semibold text-slate-900">{{ hub.summary.relationship.cancellations ?? 0 }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt class="text-slate-500">Remarcações</dt>
                                        <dd class="font-semibold text-slate-900">{{ hub.summary.relationship.reschedules ?? 0 }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-3.5 pt-3.5 border-t border-slate-100 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-1.5 text-slate-500">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Tempo como paciente</span>
                                    </div>
                                    <span class="font-bold text-teal-700 text-base">{{ hub.summary.relationship.time_as_patient ?? '—' }}</span>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 text-sm text-slate-500 flex-wrap">
                    <button v-if="patient.telefone" type="button"
                            @click="copyToClipboard(patient.telefone, 'phone')"
                            class="hover:text-teal-700 transition-colors"
                            title="Clique para copiar">
                        {{ copiedKey === 'phone' ? '✓ Copiado' : patient.telefone }}
                    </button>
                    <span v-else>Sem telefone</span>

                    <template v-if="formattedDoc">
                        <span class="text-slate-300">·</span>
                        <button type="button"
                                @click="copyToClipboard(formattedDoc, 'doc')"
                                class="hover:text-teal-700 transition-colors"
                                title="Clique para copiar">
                            {{ copiedKey === 'doc' ? '✓ Copiado' : formattedDoc }}
                        </button>
                    </template>

                    <template v-if="patientAge !== null">
                        <span class="text-slate-300">·</span>
                        <span>{{ patientAge }} anos</span>
                    </template>
                </div>
                <PatientAlertChips
                    :anamnesis-alerts="anamnesisHub?.alerts || []"
                    :note-alerts="noteAlerts"
                />
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <PatientMarkerManager
                        :patient="patient"
                        :markers="patientMarkers"
                        :available-markers="availableMarkers"
                    />
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('patients.odontogram', patient.id)"
                      class="inline-flex items-center gap-2 px-4 py-[7px] bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors">
                    🦷 Odontograma
                </Link>
                <Link :href="route('patients.edit', patient.id)" :cache-for="0"
                      class="px-4 py-[7px] border rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Editar
                </Link>
                <div class="h-6 w-px bg-slate-200"></div>
                <Link :href="route('patients.index')" class="text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                    ← Voltar à lista
                </Link>
            </div>
        </div>

        <!-- Abas centrais + sidebar -->
        <div class="relative grid gap-6" :class="sidebarOpen ? 'md:grid-cols-3' : 'md:grid-cols-1'">
            <div :class="sidebarOpen ? 'md:col-span-2' : 'md:col-span-1'" class="bg-white rounded-2xl border p-6">
                <PatientHubTabs
                    @tab-change="onTabChange"
                    :patient="patient"
                    :patient-full-name="patientFullName"
                    :patient-age="patientAge"
                    :hub="hub"
                    :anamnesis-hub="anamnesisHub"
                    :document-hub="documentHub"
                    :patient-notes="patientNotes"
                    :notes-pagination="notesPagination"
                    :available-markers="availableMarkers"
                    :active-tab="activeTab"
                    :fmt-date="fmtDate"
                    :has-address="hasAddress"
                    :street-line="streetLine"
                    :city-state-line="cityStateLine"
                    :odontogram="odontogram"
                    :tooth-statuses="toothStatuses"
                    :treatments-by-tooth="treatmentsByTooth"
                    :patient-treatments="patientTreatments"
                    :catalog-treatments="catalogTreatments"
                    :convenios="convenios"
                    :eligible-professionals="eligibleProfessionals"
                    :treatment-statuses="treatmentStatuses"
                />
            </div>
            <div v-show="sidebarOpen" class="space-y-6">
                <div class="bg-white rounded-2xl border p-6">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Próximas Ações</h3>
                    <Link :href="route('appointments.create') + '?patient_id=' + patient.id"
                          class="block w-full text-center bg-emerald-600 text-white py-2 rounded-lg">
                        + Agendar Consulta
                    </Link>
                    <div class="mt-3 pt-3 border-t border-slate-100 text-xs">
                        <p class="text-slate-400 mb-0.5">Próxima consulta</p>
                        <p v-if="hub?.summary?.clinical?.next_appointment_at" class="font-medium text-slate-800">
                            {{ fmtDateTime(hub.summary.clinical.next_appointment_at) }}
                            <span v-if="hub.summary.clinical.next_appointment_label" class="text-slate-500 font-normal"> · {{ hub.summary.clinical.next_appointment_label }}</span>
                        </p>
                        <p v-else class="text-slate-500">Nenhuma consulta agendada.</p>
                    </div>
                </div>

                <PatientEvolutionCard
                    :patient="patient"
                    :evolutions-hub="evolutionsHub"
                    :professionals="eligibleProfessionals"
                    :is-drive-connected="isDriveConnected"
                    :clinic-name="clinicName"
                    :doctor-name="doctorName"
                />

                <!-- Status clínico automático -->
                <div class="bg-white rounded-2xl border p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-900">Status Clínico</h3>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                              :class="patient.status_automatico
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : 'bg-slate-100 text-slate-500'">
                            {{ patient.status_automatico ? 'Automático' : 'Manual' }}
                        </span>
                    </div>

                    <template v-if="autoStatus">
                        <dl class="space-y-3 text-xs">
                            <div>
                                <dt class="text-slate-400 mb-0.5">Último procedimento</dt>
                                <dd class="font-medium text-slate-800">{{ autoStatus.procedure_nome }}</dd>
                                <dd class="text-slate-500 mt-0.5">{{ fmtDate(autoStatus.last_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400 mb-0.5">Inatividade prevista</dt>
                                <dd class="font-semibold mt-0.5" :class="autoStatus.is_inativo ? 'text-red-600' : 'text-slate-800'">
                                    {{ fmtDate(autoStatus.inativo_em) }}
                                </dd>
                                <dd class="mt-0.5" :class="autoStatus.is_inativo ? 'text-red-500' : 'text-slate-500'">
                                    <template v-if="autoStatus.is_inativo">Já inativo</template>
                                    <template v-else-if="autoStatus.dias_restantes <= 30">
                                        <span class="text-amber-600 font-medium">{{ autoStatus.dias_restantes }} dias restantes</span>
                                    </template>
                                    <template v-else>{{ autoStatus.dias_restantes }} dias restantes</template>
                                </dd>
                            </div>
                        </dl>
                        <p class="text-[10px] text-slate-400 mt-3 leading-relaxed">
                            Baseado em {{ autoStatus.inatividade_meses }}m de inatividade após o procedimento.
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-xs text-slate-400">
                            Nenhum procedimento concluído com tempo de inatividade definido.
                        </p>
                    </template>
                </div>

                <PatientProfessionalsCard
                    :patient="patient"
                    :hub="hub"
                    :responsible-team="responsibleTeam"
                    :eligible-professionals="eligibleProfessionals"
                />
            </div>

            <!-- Puxador da sidebar — acoplado à borda entre as colunas, acompanha abertura/fechamento -->
            <div class="hidden md:block absolute top-1/2 -translate-y-1/2 z-20 transition-[left] duration-200 ease-out"
                 :style="{ left: sidebarOpen ? 'calc(66.6667% - 20px)' : 'calc(100% - 20px)' }">
                <div class="relative inline-flex group/stg">
                    <button type="button"
                            @click="sidebarOpen = !sidebarOpen"
                            :aria-label="sidebarOpen ? 'Ocultar painel lateral' : 'Mostrar painel lateral'"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm cursor-pointer hover:bg-slate-50 hover:text-teal-600 hover:border-teal-200 hover:shadow-md transition-all duration-200 ease-out">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  :d="sidebarOpen ? 'M15.75 19.5L8.25 12l7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5'" />
                        </svg>
                    </button>

                    <!-- Tooltip (mesmo padrão CSS-puro do StatusIndicator.vue) -->
                    <div class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-1.5
                                opacity-0 group-hover/stg:opacity-100 transition-opacity duration-150
                                bg-slate-800 text-white text-[10px] font-medium rounded-md px-2 py-1
                                whitespace-nowrap z-[200] shadow-lg">
                        {{ sidebarOpen ? 'Ocultar painel lateral' : 'Mostrar painel lateral' }}
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-slate-800" />
                    </div>
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
                        :disabled="healthCheckLoading"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors">
                    <svg class="w-3.5 h-3.5" :class="healthCheckLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ healthCheckLoading ? 'Verificando...' : 'Verificar Drive' }}
                </button>
            </div>

            <!-- Contadores de integridade -->
            <div v-if="isDriveConnected && allPhotos.length > 0" class="mb-5 flex flex-wrap gap-2">
                <div class="flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Fotos ativas: {{ activeCount }}
                </div>
                <div v-if="removedCount > 0"
                     class="flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                    Removidos: {{ removedCount }}
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
                    <InputError :message="uploadForm.errors.categoria" />
                </div>
                <div v-if="uploadForm.categoria" class="flex flex-col gap-1">
                    <label class="text-xs text-slate-500 font-medium">Subcategoria <span class="text-red-400">*</span></label>
                    <select v-model="uploadForm.subcategoria" required
                            class="border rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 min-w-[200px]">
                        <option value="">Selecione uma subcategoria</option>
                        <option v-for="item in subcategorias" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <InputError :message="uploadForm.errors.subcategoria" />
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
                        :class="activeFilter === label
                            ? (label === 'Removidos' ? 'bg-red-600 text-white' : 'bg-slate-800 text-white')
                            : (label === 'Removidos' ? 'bg-red-50 text-red-600 hover:bg-red-100 border border-red-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')">
                    <template v-if="label === 'Removidos'">❌ {{ label }}</template>
                    <template v-else>{{ label }}</template>
                </button>
            </div>

            <!-- Info card — aba Removidos -->
            <div v-if="activeFilter === 'Removidos' && removedCount > 0"
                 class="mb-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-sm font-semibold text-slate-800 mb-2">Arquivos removidos</h4>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Esta área exibe documentos que existiam anteriormente no prontuário, mas foram removidos diretamente da conta Google Drive da clínica.
                    Esses registros permanecem apenas para fins de auditoria e rastreabilidade.
                    Os arquivos não poderão ser visualizados pelo ClinicFlow enquanto permanecerem excluídos do Google Drive.
                </p>
            </div>

            <!-- Galeria -->
            <div v-if="filteredPhotos.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div v-for="photo in filteredPhotos" :key="photo.id"
                     class="group rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden transition-all duration-200"
                     :class="!isRemoved(photo) ? 'cursor-pointer hover:shadow-md hover:border-slate-300' : 'cursor-default'"
                     @click="openModal(photo)">
                    <!-- Área da imagem -->
                    <div class="relative aspect-square overflow-hidden bg-slate-100">

                        <!-- Ícones de edição (aparecem ao passar o mouse) -->
                        <div v-if="!isRemoved(photo) && isDriveConnected"
                             class="absolute top-1.5 right-1.5 z-10 hidden group-hover:flex items-center gap-1"
                             @click.stop>
                            <!-- Renomear -->
                            <button @click.stop="openRenameModal(photo)"
                                    class="w-6 h-6 flex items-center justify-center rounded-full transition-all"
                                    :class="isEditable(photo)
                                        ? 'bg-black/50 text-white hover:bg-blue-500/80'
                                        : 'bg-black/20 text-white/40'"
                                    title="Renomear documento">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <!-- Excluir -->
                            <button @click.stop="openDeleteModal(photo)"
                                    class="w-6 h-6 flex items-center justify-center rounded-full transition-all"
                                    :class="isEditable(photo)
                                        ? 'bg-black/50 text-white hover:bg-red-500/80'
                                        : 'bg-black/20 text-white/40'"
                                    title="Excluir documento">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <!-- Ajuda -->
                            <span class="w-4 h-4 flex items-center justify-center rounded-full bg-black/30 text-white/70 text-[9px] font-bold cursor-help leading-none"
                                  title="Por segurança e preservação do prontuário odontológico, arquivos enviados podem ser removidos apenas nas primeiras 2 horas após o upload. Após esse período o documento torna-se permanente e somente poderá ser substituído através de um novo envio.">
                                ?
                            </span>
                        </div>

                        <!-- Arquivo removido do Drive -->
                        <div v-if="isRemoved(photo)"
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
                        <p v-if="photo.description" class="text-[11px] text-slate-400 italic truncate mt-0.5 leading-tight">{{ photo.description }}</p>
                        <p v-if="photo.taken_at" class="text-xs text-slate-400 mt-0.5">{{ fmtDate(photo.taken_at) }}</p>
                        <p v-if="photo.dente" class="text-xs text-slate-400">Dente {{ photo.dente }}</p>
                        <span v-if="isRemoved(photo)"
                              class="mt-1.5 inline-block rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-600">
                            removido
                        </span>
                        <!-- Abrir no Odontograma -->
                        <button v-if="!isRemoved(photo)"
                                @click.stop="router.visit(route('patients.odontogram', patient.id) + '?photo_id=' + photo.id)"
                                class="mt-2 w-full flex items-center justify-center gap-1.5 text-[10px] font-medium text-teal-600 hover:text-teal-800 hover:bg-teal-50 rounded-lg py-1.5 border border-teal-200 transition-colors">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Abrir no Odontograma
                        </button>
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
            <div v-if="driveActivityLogs?.length" class="mt-10 border-t border-slate-100 pt-8">

                <!-- Cabeçalho + botões -->
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                            Histórico Google Drive
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                {{ driveActivityLogs.length }}
                            </span>
                        </h4>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ driveActivityLogs.length }} evento{{ driveActivityLogs.length !== 1 ? 's' : '' }} registrado{{ driveActivityLogs.length !== 1 ? 's' : '' }}
                            <template v-if="tlLastEvent"> · Último: {{ tlLastEvent }}</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button @click="tlExport"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Exportar
                        </button>
                        <button @click="tlRefresh"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Atualizar
                        </button>
                        <button @click="tlOpen = !tlOpen"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                            <svg class="w-3 h-3 transition-transform duration-200" :class="tlOpen ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            {{ tlOpen ? 'Recolher histórico' : 'Expandir histórico' }}
                        </button>
                    </div>
                </div>

                <!-- Corpo (accordion) -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-1">
                    <div v-if="tlOpen" class="mt-5">

                        <!-- Filtros + busca -->
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="f in TIMELINE_FILTERS" :key="f.key"
                                        @click="tlFilter = f.key; tlShowAll = false; tlExpandedId = null"
                                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                                        :class="tlFilter === f.key
                                            ? 'bg-slate-800 text-white'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                                    {{ f.label }}
                                </button>
                            </div>
                            <div class="ml-auto flex items-center gap-2">
                                <div class="relative">
                                    <input v-model="tlSearch" type="text" placeholder="Pesquisar no histórico..."
                                           class="w-44 rounded-lg border border-slate-200 bg-white pl-7 pr-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300" />
                                    <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <button v-if="tlFilter !== 'all' || tlSearch"
                                        @click="tlReset"
                                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 transition-colors whitespace-nowrap">
                                    Limpar filtros
                                </button>
                            </div>
                        </div>

                        <!-- Conteúdo da timeline (scroll interno) -->
                        <div class="max-h-[600px] overflow-y-auto overscroll-contain space-y-6 pr-0.5">

                            <!-- Estado vazio -->
                            <div v-if="!tlFiltered.length"
                                 class="flex flex-col items-center justify-center py-14 text-center text-slate-400">
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="text-sm font-medium">Nenhum evento encontrado.</p>
                                <button @click="tlReset" class="mt-2 text-xs underline text-slate-400 hover:text-slate-600">
                                    Limpar filtros
                                </button>
                            </div>

                            <!-- Grupos por data -->
                            <div v-for="group in tlGrouped" :key="group.date">
                                <div class="mb-2 sticky top-0 z-10 bg-white/95 backdrop-blur-sm">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                        {{ group.date }}
                                    </span>
                                </div>

                                <div class="space-y-1.5">
                                    <div v-for="log in group.logs" :key="log.id"
                                         class="rounded-xl border border-slate-100 border-l-4 overflow-hidden transition-shadow duration-150"
                                         :class="[
                                             tlCritStyle(tlCrit(log.event_type)).bar,
                                             tlExpandedId === log.id ? 'shadow-sm' : '',
                                         ]">

                                        <!-- Linha clicável -->
                                        <button @click="tlToggle(log.id)"
                                                class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-slate-50/60 transition-colors">
                                            <!-- Ícone -->
                                            <span class="shrink-0 flex h-7 w-7 items-center justify-center rounded-lg bg-white shadow-sm border border-slate-100">
                                                <svg class="w-3.5 h-3.5" :class="tlIcon(log.event_type).color"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          :d="ICON_PATHS[tlIcon(log.event_type).icon]"/>
                                                </svg>
                                            </span>
                                            <!-- Texto -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="text-xs font-semibold text-slate-800">
                                                        {{ eventLabel(log.event_type) }}
                                                    </span>
                                                    <span class="text-[10px] rounded-full border px-1.5 py-px shrink-0"
                                                          :class="tlCritStyle(tlCrit(log.event_type)).badge">
                                                        {{ CRIT_LABELS[tlCrit(log.event_type)] }}
                                                    </span>
                                                </div>
                                                <p v-if="log.metadata?.filename || log.description"
                                                   class="text-[11px] text-slate-500 truncate mt-0.5">
                                                    {{ log.metadata?.filename || log.description }}
                                                </p>
                                            </div>
                                            <!-- Hora -->
                                            <span class="shrink-0 text-[11px] text-slate-400">
                                                {{ new Date(log.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                            <!-- Chevron -->
                                            <svg class="w-3 h-3 text-slate-300 shrink-0 transition-transform duration-200"
                                                 :class="tlExpandedId === log.id ? 'rotate-180' : ''"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        <!-- Detalhes expandidos -->
                                        <Transition
                                            enter-active-class="transition-all duration-200 ease-out"
                                            enter-from-class="opacity-0"
                                            enter-to-class="opacity-100"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-from-class="opacity-100"
                                            leave-to-class="opacity-0">
                                            <div v-if="tlExpandedId === log.id"
                                                 class="px-4 pb-4 pt-2 border-t border-slate-100 ml-10">
                                                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                                                    <template v-if="log.metadata?.patient_name">
                                                        <dt class="text-slate-400 font-medium">Paciente</dt>
                                                        <dd class="text-slate-700">{{ log.metadata.patient_name }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.filename">
                                                        <dt class="text-slate-400 font-medium">Arquivo</dt>
                                                        <dd class="text-slate-700 break-all">{{ log.metadata.filename }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.categoria">
                                                        <dt class="text-slate-400 font-medium">Categoria</dt>
                                                        <dd class="text-slate-700">{{ log.metadata.categoria }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.subcategoria">
                                                        <dt class="text-slate-400 font-medium">Subcategoria</dt>
                                                        <dd class="text-slate-700">{{ log.metadata.subcategoria }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.level">
                                                        <dt class="text-slate-400 font-medium">Nível</dt>
                                                        <dd class="text-slate-700 capitalize">{{ log.metadata.level }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.folder_path">
                                                        <dt class="text-slate-400 font-medium">Pasta</dt>
                                                        <dd>
                                                            <code class="rounded bg-slate-100 px-1 py-px font-mono text-[10px] text-slate-700 break-all">
                                                                {{ log.metadata.folder_path }}
                                                            </code>
                                                        </dd>
                                                    </template>
                                                    <template v-if="log.metadata?.status_note">
                                                        <dt class="text-slate-400 font-medium">Status</dt>
                                                        <dd class="text-slate-700">{{ log.metadata.status_note }}</dd>
                                                    </template>
                                                    <template v-if="log.metadata?.detected_at">
                                                        <dt class="text-slate-400 font-medium">Detectado em</dt>
                                                        <dd class="text-slate-700">{{ fmtDateTime(log.metadata.detected_at) }}</dd>
                                                    </template>
                                                    <dt class="text-slate-400 font-medium">Criticidade</dt>
                                                    <dd :class="tlCritStyle(tlCrit(log.event_type)).text" class="font-medium">
                                                        {{ CRIT_LABELS[tlCrit(log.event_type)] }}
                                                    </dd>
                                                    <dt class="text-slate-400 font-medium">Data</dt>
                                                    <dd class="text-slate-700">{{ fmtDateTime(log.created_at) }}</dd>
                                                </dl>
                                                <p v-if="log.description"
                                                   class="mt-3 text-xs text-slate-500 leading-relaxed border-t border-slate-100 pt-3">
                                                    {{ log.description }}
                                                </p>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mostrar mais / menos -->
                        <div v-if="tlHidden > 0 || tlShowAll" class="mt-4 text-center">
                            <button @click="tlShowAll = !tlShowAll; tlExpandedId = null"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                                <template v-if="tlShowAll">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                    Mostrar menos
                                </template>
                                <template v-else>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    Mostrar mais {{ tlHidden }} registro{{ tlHidden !== 1 ? 's' : '' }}
                                </template>
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
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
                            <p v-if="selectedPhoto.description" class="text-xs text-slate-500 italic mt-0.5">{{ selectedPhoto.description }}</p>
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
                    <div v-if="selectedPhoto.observacao"
                         class="shrink-0 border-t bg-amber-50 px-5 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 mb-1">Observação clínica</p>
                        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ selectedPhoto.observacao }}</p>
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
    <!--  MODAL — Disaster Recovery (estrutura removida)                         -->
    <!-- ════════════════════════════════════════════════════════════════════════ -->
    <DriveDisasterRecoveryModal
        ref="disasterRecoveryModalRef"
        :show="showDisasterRecoveryModal"
        :clinic-name="clinicName"
        :doctor-name="doctorName"
        :patient-full-name="patientFullName"
        @close="onDisasterRecoveryClosed"
        @confirm="onDisasterRecoveryConfirmed"
        @recovered="onDisasterRecoveryDone" />

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

    <!-- ─── Modal: Renomear documento ───────────────────────────────────────── -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="renameModal.open"
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                 @click.self="renameModal = { open: false, photo: null }">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                    <div class="px-6 pt-6 pb-4">
                        <h3 class="text-base font-semibold text-slate-900">Editar documento</h3>
                        <p class="text-xs text-slate-500 mt-1">A extensão do arquivo é mantida automaticamente.</p>
                    </div>
                    <form @submit.prevent="submitRename" class="px-6 pb-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- Categoria -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Categoria</label>
                            <select v-model="renameForm.categoria"
                                    class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                                <option value="">— Manter categoria atual —</option>
                                <option v-for="(_, group) in CATEGORIES" :key="group" :value="group">{{ group }}</option>
                            </select>
                            <InputError :message="renameForm.errors.categoria" />
                        </div>
                        <!-- Subcategoria -->
                        <div v-if="renameForm.categoria && renameSubcategorias.length">
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Subcategoria
                                <span class="ml-1 font-normal text-slate-400 text-xs">(preenche o nome automaticamente)</span>
                            </label>
                            <select v-model="renameSubcategoriasPicker"
                                    class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                                <option value="">— Selecione para sugerir um nome —</option>
                                <option v-for="item in renameSubcategorias" :key="item" :value="item">{{ item }}</option>
                            </select>
                        </div>
                        <!-- Nome -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Nome <span class="text-red-500">*</span>
                                <span class="ml-1 font-normal text-slate-400 text-xs">(usado no nome do arquivo no Drive)</span>
                            </label>
                            <input v-model="renameForm.new_name"
                                   type="text"
                                   placeholder="Ex: Radiografia Interproximal"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                   required
                                   autofocus />
                            <p v-if="renameModal.photo" class="text-xs text-slate-400 mt-1">
                                No Drive:
                                <strong>{{ renameForm.new_name || '…' }}{{ renameForm.dente ? ` - Dente ${renameForm.dente}` : '' }}.{{ renameModal.photo.filename?.split('.').pop() }}</strong>
                            </p>
                            <InputError :message="renameForm.errors.new_name" />
                        </div>
                        <!-- Dente -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Dente
                                <span class="ml-1 font-normal text-slate-400 text-xs">(deixe vazio para remover)</span>
                            </label>
                            <input v-model="renameForm.dente"
                                   type="text"
                                   placeholder="Ex: 12"
                                   maxlength="10"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                            <InputError :message="renameForm.errors.dente" />
                        </div>
                        <!-- Descrição -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Descrição
                                <span class="ml-1 font-normal text-slate-400 text-xs">(interna — não vai para o Drive)</span>
                            </label>
                            <input v-model="renameForm.description"
                                   type="text"
                                   placeholder="Ex: Boca aberta"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
                            <InputError :message="renameForm.errors.description" />
                        </div>
                        <!-- Observação -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Observação
                                <span class="ml-1 font-normal text-slate-400 text-xs">(visível apenas nos detalhes)</span>
                            </label>
                            <textarea v-model="renameForm.observacao"
                                      rows="3"
                                      placeholder="Anotações clínicas adicionais..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none" />
                            <InputError :message="renameForm.errors.observacao" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit"
                                    :disabled="renameForm.processing || !renameForm.new_name.trim()"
                                    class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ renameForm.processing ? 'Salvando...' : 'Salvar' }}
                            </button>
                            <button type="button"
                                    @click="renameModal = { open: false, photo: null }"
                                    class="flex-1 border border-slate-200 text-slate-600 py-2 rounded-lg text-sm hover:bg-slate-50 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Modal: Excluir documento ────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="deleteModal.open"
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                 @click.self="deleteModal = { open: false, photo: null }">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                    <div class="px-6 pt-6 pb-4">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Excluir documento?</h3>
                                <p v-if="deleteModal.photo" class="text-xs text-slate-500 mt-0.5 truncate max-w-[220px]">
                                    {{ deleteModal.photo.filename }}
                                </p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Este arquivo será removido permanentemente do Google Drive e do ClinicFlow.
                            <strong class="text-slate-800">Esta ação não poderá ser desfeita.</strong>
                        </p>
                    </div>
                    <div class="flex gap-3 px-6 pb-6 pt-2">
                        <button @click="submitDelete"
                                :disabled="deleteForm.processing"
                                class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50 transition-colors">
                            {{ deleteForm.processing ? 'Removendo...' : 'Excluir documento' }}
                        </button>
                        <button @click="deleteModal = { open: false, photo: null }"
                                class="flex-1 border border-slate-200 text-slate-600 py-2 rounded-lg text-sm hover:bg-slate-50 transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Modal: Documento protegido ──────────────────────────────────────── -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="protectedModal"
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                 @click.self="protectedModal = false">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                    <div class="px-6 py-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-900">Documento protegido</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Este documento foi enviado há mais de 2 horas e passou a integrar o histórico clínico do paciente.
                        </p>
                        <p class="text-sm text-slate-600 leading-relaxed mt-2">
                            Para preservar a integridade do prontuário, não é mais possível removê-lo pelo sistema.
                        </p>
                        <p class="text-sm text-slate-600 leading-relaxed mt-2">
                            Caso seja necessário realizar uma correção, envie uma nova versão do documento.
                        </p>
                        <button @click="protectedModal = false"
                                class="mt-5 w-full border border-slate-200 text-slate-700 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Drive Health Check ─────────────────────────────────────────────── -->
    <DriveHealthCheckLoading :show="healthCheckLoading" />
    <DriveHealthReportModal
        v-if="healthReport"
        :show="showHealthReport"
        :report="healthReport"
        @close="closeHealthReport"
        @recreate-structure="onHealthRecreateStructure"
    />
</template>
