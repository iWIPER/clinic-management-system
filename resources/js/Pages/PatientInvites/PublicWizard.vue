<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AddressFields from '@/Components/AddressFields.vue';
import PhoneNumberInput from '@/Components/PhoneNumberInput.vue';
import InputError from '@/Components/InputError.vue';
import AnamnesisCategoryCard from '@/Components/Anamnesis/AnamnesisCategoryCard.vue';
import AnamnesisSignatureModal from '@/Components/Anamnesis/AnamnesisSignatureModal.vue';
import { useCanalLembrete, useConvenioTitular } from '@/composables/usePatientFormBehaviors.js';
import { SEXO_OPTIONS, PARENTESCO_OPTIONS, CANAL_LEMBRETE_OPTIONS } from '@/lib/patientFormOptions.js';

const props = defineProps({
    token: { type: String, required: true },
    invite: { type: Object, required: true }, // { current_step, allow_insurance }
    patient: { type: Object, required: true },
    convenios: { type: Array, default: () => [] },
    conclusion: { type: Object, default: null }, // presente quando o convite já foi concluído antes (2ª aba, outro aparelho, F5)
    anamnese: { type: Object, default: null }, // { categories: [...] } — presente quando aguardando_conclusao (Fase 4)
});

const BASE_STEPS = ['dados_pessoais', 'endereco', 'responsavel_legal'];
// "convenio" só existe para quem o convite permite (Fase 3) — mesma allowlist
// que o backend já aplica (PatientInvite::wizardSteps()), espelhada aqui só
// para controlar a navegação; a validação de verdade continua no servidor.
const STEPS = computed(() => (
    props.invite.allow_insurance ? [...BASE_STEPS, 'convenio'] : BASE_STEPS
));

// welcome -> wizard (dados_pessoais/endereco/responsavel_legal[/convenio]) ->
// anamnese (só quando allow_anamnesis, Fase 4) -> concluido. Pula a tela de
// boas-vindas se já houver progresso salvo (current_step), anamnese pendente
// ou o convite já concluído antes — "retomar exatamente de onde parou" (BRD
// §8.1) não deveria pedir pra clicar em "Começar" de novo depois de um F5.
const view = ref(
    props.conclusion ? 'concluido'
    : props.anamnese ? 'anamnese'
    : (props.invite.current_step ? 'wizard' : 'welcome')
);
const activeStep = ref(props.invite.current_step || 'dados_pessoais');
const conclusion = ref(props.conclusion);
const completing = ref(false);

// Campos de dado do Patient (whitelist que o backend já mandou em
// props.patient) — derivados daqui, não redigitados, para não existir uma
// segunda lista desalinhada da allowlist do controller.
const DATA_FIELDS = Object.keys(props.patient);

// Cada etapa só manda os próprios campos no autosave — não o form inteiro.
// Importante além de eficiente: se um campo de uma etapa já visitada ficar
// inválido (ex.: e-mail malformado na Etapa 1), isso não pode travar o
// autosave de uma etapa diferente que o paciente preencheu certinho depois.
const STEP_FIELDS = {
    dados_pessoais: [
        'nome', 'sobrenome', 'nascimento', 'sexo', 'is_estrangeiro', 'cpf', 'rg', 'passaporte',
        'profissao', 'canal_lembrete', 'telefone', 'email',
    ],
    endereco: ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'],
    responsavel_legal: [
        'possui_responsavel_legal', 'responsavel_legal_nome', 'responsavel_legal_cpf', 'responsavel_legal_rg',
        'responsavel_legal_estrangeiro', 'responsavel_legal_passaporte', 'responsavel_legal_telefone',
        'responsavel_legal_parentesco', 'contato_emergencia_nome', 'contato_emergencia_telefone',
    ],
    // Só relevante quando props.invite.allow_insurance — se não for, esses
    // campos nem chegam em props.patient/DATA_FIELDS (backend já filtra),
    // então mandar essa lista sempre aqui não causa nenhum efeito indevido.
    convenio: [
        'tipo_atendimento', 'convenio_id', 'convenio_numero_carteirinha',
        'convenio_titular', 'convenio_titular_cpf', 'convenio_titular_parentesco',
    ],
};

// form.errors existe de propósito, no mesmo formato de Inertia useForm()
// (field -> string, não array) — é assim que AddressFields.vue e
// InputError.vue já foram construídos para consumir (mesmo padrão de
// Create.vue/Edit.vue). Reaproveita os dois componentes exatamente como
// foram projetados, sem exigir nenhuma adaptação neles.
const form = reactive({ ...props.patient, errors: {} });
const { naoEnviarLembretes, canalLembreteSelecionado } = useCanalLembrete(form);
const { convenioTitularEhPaciente } = useConvenioTitular(form);

const stepIndex = computed(() => STEPS.value.indexOf(activeStep.value));

// Toggle "Você possui convênio?" — não é um campo do Patient em si (a
// classificação é tipo_atendimento), então é local, igual ao
// convenioTitularEhPaciente que useConvenioTitular já expõe. Decide
// tipo_atendimento automaticamente (decisão confirmada nesta fase: o
// paciente nunca vê "Particular"/"Convênio"/"Outro" como opções — só
// responde sim/não e o sistema classifica).
const possuiConvenio = ref(form.tipo_atendimento === 'convenio');
watch(possuiConvenio, (checked) => {
    if (checked) {
        form.tipo_atendimento = 'convenio';
    } else {
        form.tipo_atendimento = 'particular';
        form.convenio_id = '';
        form.convenio_numero_carteirinha = '';
        form.convenio_titular = '';
        form.convenio_titular_cpf = '';
        form.convenio_titular_parentesco = '';
        // Sem isso, marcar de novo "Você possui convênio?" mais tarde
        // reexibiria o bloco com o checkbox "Titular é o próprio paciente"
        // ainda marcado (estado local, não voltou a false sozinho), mas com
        // convenio_titular/cpf já vazios pela limpeza acima — a tela diria
        // "é o paciente" enquanto o dado salvo ficaria em branco.
        convenioTitularEhPaciente.value = false;
    }
});

// Higiene de dado: campos do lado que foi desmarcado não ficam pendurados
// escondidos no Patient (ex.: preencheu Responsável legal, desmarcou, e o
// nome antigo continuaria salvo mesmo sem aparecer em lugar nenhum da tela).
watch(() => form.possui_responsavel_legal, (hasLegalGuardian) => {
    if (hasLegalGuardian) {
        form.contato_emergencia_nome = '';
        form.contato_emergencia_telefone = '';
    } else {
        form.responsavel_legal_nome = '';
        form.responsavel_legal_cpf = '';
        form.responsavel_legal_rg = '';
        form.responsavel_legal_estrangeiro = false;
        form.responsavel_legal_passaporte = '';
        form.responsavel_legal_telefone = '';
        form.responsavel_legal_parentesco = '';
    }
});

function start() {
    view.value = 'wizard';
}

function draftPayload(step) {
    const fields = STEP_FIELDS[activeStep.value] || [];
    return {
        ...Object.fromEntries(fields.map((key) => [key, form[key]])),
        current_step: step,
    };
}

// ── Autosave (BRD PATIENT_INVITATIONS_BRD.md §8.1) ──────────────────────────
const saveState = reactive({ saving: false, savedAt: null, errorMessage: null });
let saveTimer = null;

function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 600);
}

// Observa só os campos de dado (DATA_FIELDS), nunca form.errors — de
// propósito: se o watcher enxergasse form.errors, cada save() (que limpa e
// repopula form.errors) reagendaria a si mesmo para sempre. Pega qualquer
// origem de mutação dos campos observados (v-model direto, computed setters
// de useCanalLembrete, ou AddressFields/PhoneNumberInput escrevendo em
// form.* internamente) sem precisar de @input/@change em cada campo.
watch(
    () => DATA_FIELDS.map((key) => form[key]),
    () => { if (view.value === 'wizard') scheduleSave(); },
);

async function save(stepOverride = null) {
    clearTimeout(saveTimer);
    saveState.saving = true;
    form.errors = {};
    saveState.errorMessage = null;
    try {
        await window.axios.patch(
            route('patient-invites.public.update', props.token),
            draftPayload(stepOverride ?? activeStep.value),
        );
        saveState.savedAt = new Date();
    } catch (e) {
        if (e.response?.status === 422) {
            // Laravel devolve {campo: [msg, ...]}; Inertia's useForm() (e por
            // extensão AddressFields/InputError) espera {campo: msg} — mesma
            // normalização que o Inertia já faz por baixo dos panos.
            const raw = e.response.data.errors || {};
            form.errors = Object.fromEntries(
                Object.entries(raw).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
            );
            saveState.errorMessage = 'Corrija os campos destacados.';
        } else if (e.response?.status === 410) {
            // Convite expirou, foi cancelado pela clínica, ou já foi
            // concluído em outra aba/aparelho enquanto esta ficou aberta —
            // recarrega para a mesma rota já decidir a tela certa (Invalid
            // ou a conclusão), sem duplicar essa lógica aqui no frontend.
            // reload() não é síncrono — marca o erro também, para que quem
            // chamou save() (goToStep/goBack/finish) não prossiga com outra
            // requisição na janela antes da página realmente descarregar.
            saveState.errorMessage = 'Este convite não está mais disponível.';
            window.location.reload();
        } else {
            // Falha pontual (rede, etc.) — os dados continuam no formulário,
            // não se perdem; mas o paciente precisa saber que ESSE salvamento
            // específico não foi confirmado, não só ver o "Salvo há N min"
            // antigo como se nada tivesse mudado.
            saveState.errorMessage = 'Não foi possível salvar agora. Verifique sua conexão — vamos tentar de novo na próxima alteração.';
        }
    } finally {
        saveState.saving = false;
    }
}

const saveLabel = computed(() => {
    if (saveState.saving) return { text: 'Salvando…', tone: 'saving' };
    if (saveState.errorMessage) return { text: saveState.errorMessage, tone: 'error' };
    if (saveState.savedAt) {
        const minutes = Math.floor((Date.now() - saveState.savedAt.getTime()) / 60000);
        return { text: minutes < 1 ? 'Salvo agora' : `Salvo há ${minutes} min`, tone: 'success' };
    }
    return null;
});

// Etapa 3 mostra um bloco OU outro (nunca os dois) — o título acompanha qual
// dos dois está de fato visível, em vez de dizer sempre "Responsável legal"
// mesmo quando o paciente está olhando para "Contato de Emergência".
const legalGuardianStepTitle = computed(() => (
    form.possui_responsavel_legal ? 'Responsável legal' : 'Contato de emergência'
));

// ── Etapa de Anamnese (Fase 4, condicionada a allow_anamnesis) ──────────────
// Endpoints e modelo de dados próprios (AnamnesisInstance/AnamnesisAnswer,
// não Patient) — por isso não faz parte de STEPS/STEP_FIELDS acima, mesmo
// vindo logo depois da última etapa de cadastro na jornada do paciente.
// AnamnesisCategoryCard/AnamnesisQuestionField/AnamnesisSignatureModal são
// reaproveitados sem reescrita (hub de Anamnese, mesma auditoria da Fase 4).
const anamnese = ref(props.anamnese);
const anamneseAnswers = reactive({});
const anamneseError = ref('');
const showSignatureModal = ref(false);

function hydrateAnamneseAnswers(payload) {
    Object.keys(anamneseAnswers).forEach((key) => delete anamneseAnswers[key]);
    payload?.categories?.forEach((category) => {
        category.questions.forEach((q) => {
            anamneseAnswers[q.id] = { value: q.value ?? null, supplementary_text: q.supplementary_text ?? null };
        });
    });
}
hydrateAnamneseAnswers(props.anamnese);

const allRequiredAnamneseQuestionsAnswered = computed(() => {
    if (!anamnese.value) return false;
    return anamnese.value.categories.every((category) => category.questions.every((q) => {
        if (!q.is_required || q.is_disabled) return true;
        const value = anamneseAnswers[q.id]?.value;
        return value !== null && value !== undefined && value !== '';
    }));
});

function anamneseAnswersPayload() {
    return {
        answers: Object.entries(anamneseAnswers).map(([questionId, a]) => ({
            question_id: Number(questionId),
            value: a.value,
            supplementary_text: a.supplementary_text,
        })),
    };
}

let anamneseSaveTimer = null;
function scheduleAnamneseSave() {
    clearTimeout(anamneseSaveTimer);
    anamneseSaveTimer = setTimeout(saveAnamnese, 600);
}

function onAnamneseAnswerChange() {
    if (view.value === 'anamnese') scheduleAnamneseSave();
}

// Reaproveita saveState/saveLabel do wizard base — mesmo indicador
// "Salvando…/Salvo há N min", só apontando para o endpoint de anamnese.
async function saveAnamnese() {
    clearTimeout(anamneseSaveTimer);
    saveState.saving = true;
    saveState.errorMessage = null;
    try {
        await window.axios.patch(route('patient-invites.public.anamnese.update', props.token), anamneseAnswersPayload());
        saveState.savedAt = new Date();
    } catch (e) {
        if (e.response?.status === 410) {
            saveState.errorMessage = 'Este convite não está mais disponível.';
            window.location.reload();
        } else {
            saveState.errorMessage = 'Não foi possível salvar agora. Verifique sua conexão — vamos tentar de novo na próxima alteração.';
        }
    } finally {
        saveState.saving = false;
    }
}

// server-error do AnamnesisSignatureModal já cobre a exibição do erro; o
// modal só fecha quando a assinatura (e a conclusão do convite) realmente
// tiverem sucesso — em caso de erro, o paciente pode corrigir e tentar de
// novo sem perder o que já desenhou.
async function submitAnamneseSignature(payload) {
    anamneseError.value = '';
    try {
        clearTimeout(anamneseSaveTimer);
        await saveAnamnese();
        // saveAnamnese() trata os próprios erros sem relançar (mesmo padrão
        // de save()) — sem essa checagem, uma falha de rede aqui deixaria a
        // assinatura seguir em cima de respostas que não chegaram a salvar.
        if (saveState.errorMessage) {
            anamneseError.value = saveState.errorMessage;
            return;
        }

        const { data } = await window.axios.post(route('patient-invites.public.anamnese.complete', props.token), payload);
        showSignatureModal.value = false;
        conclusion.value = data;
        view.value = 'concluido';
    } catch (e) {
        if (e.response?.status === 410) {
            window.location.reload();
            return;
        }
        anamneseError.value = e.response?.status === 422
            ? (e.response.data?.errors?.answers?.[0] || e.response.data?.message || 'Responda todas as perguntas obrigatórias antes de assinar.')
            : 'Não foi possível concluir agora. Verifique sua conexão e tente novamente.';
    }
}

async function goToStep(next) {
    await save(next);
    if (!saveState.errorMessage) activeStep.value = next;
}

async function goBack() {
    const prev = STEPS.value[stepIndex.value - 1];
    if (prev) {
        await save(prev);
        if (!saveState.errorMessage) activeStep.value = prev;
    }
}

async function nextStep() {
    const next = STEPS.value[stepIndex.value + 1];
    if (next) {
        await goToStep(next);
    } else {
        await finish();
    }
}

async function finish() {
    completing.value = true;
    try {
        await save(activeStep.value);
        if (saveState.errorMessage) return;
        const { data } = await window.axios.post(route('patient-invites.public.complete', props.token));

        if (data.status === 'aguardando_conclusao') {
            // Etapas de cadastro concluídas, mas allow_anamnesis exige a
            // etapa de Anamnese antes do convite poder ir para concluido —
            // a resposta já traz o payload da anamnese, sem round-trip extra.
            anamnese.value = data.anamnese;
            hydrateAnamneseAnswers(data.anamnese);
            saveState.savedAt = null;
            view.value = 'anamnese';
        } else {
            conclusion.value = data;
            view.value = 'concluido';
        }
    } catch (e) {
        if (e.response?.status === 410) {
            window.location.reload();
            return;
        }
        saveState.errorMessage = 'Não foi possível concluir agora. Verifique sua conexão e tente novamente.';
    } finally {
        completing.value = false;
    }
}

function xsrfHeader() {
    const xsrf = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
    return xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {};
}

// Flush best-effort ao fechar a aba — fetch com keepalive (sendBeacon não
// suporta PATCH), mesmo cuidado documentado no BRD §8.1. Cobre as duas
// etapas com autosave próprio: wizard base (campos do Patient) e Anamnese
// (Fase 4, endpoint e payload diferentes).
function flushOnUnload() {
    if (view.value === 'wizard') {
        fetch(route('patient-invites.public.update', props.token), {
            method: 'PATCH',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...xsrfHeader() },
            body: JSON.stringify(draftPayload(activeStep.value)),
        });
    } else if (view.value === 'anamnese') {
        fetch(route('patient-invites.public.anamnese.update', props.token), {
            method: 'PATCH',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...xsrfHeader() },
            body: JSON.stringify(anamneseAnswersPayload()),
        });
    }
}
onMounted(() => window.addEventListener('beforeunload', flushOnUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', flushOnUnload));

function fmtDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-emerald-950 via-slate-900 to-teal-950 flex items-center justify-center p-4">

        <!-- Boas-vindas -->
        <div v-if="view === 'welcome'" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center">
            <div class="text-5xl mb-4">👋</div>
            <h1 class="text-xl font-bold text-slate-900 mb-2">Bem-vindo(a), {{ patient.nome }}.</h1>
            <p class="text-sm text-slate-500 mb-6">Esse cadastro leva aproximadamente 5 minutos.</p>
            <button type="button" @click="start"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-6 py-2.5 rounded-lg">
                Começar
            </button>
        </div>

        <!-- Conclusão -->
        <div v-else-if="view === 'concluido'" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center">
            <div class="text-5xl mb-4">✅</div>
            <template v-if="conclusion?.next_appointment">
                <h1 class="text-xl font-bold text-slate-900 mb-2">Seu cadastro foi concluído.</h1>
                <p class="text-sm text-slate-600 mb-1">Nos vemos em:</p>
                <p class="text-base font-semibold text-emerald-700 mb-6">{{ fmtDateTime(conclusion.next_appointment.start) }}</p>
                <p class="text-sm text-slate-500">Até breve.</p>
            </template>
            <template v-else>
                <h1 class="text-xl font-bold text-slate-900 mb-2">Cadastro concluído com sucesso.</h1>
                <p class="text-sm text-slate-500">
                    Obrigado por preencher seus dados. A clínica recebeu automaticamente todas as suas informações.
                    Em breve entraremos em contato.
                </p>
            </template>
        </div>

        <!-- Anamnese (Fase 4, condicionada a allow_anamnesis) -->
        <div v-else-if="view === 'anamnese'" class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 md:p-8">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-lg font-semibold text-slate-800">Anamnese</h2>
                <span v-if="saveLabel" class="text-xs flex items-center gap-1.5 text-right"
                      :class="saveLabel.tone === 'error' ? 'text-red-600' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="{
                        'bg-amber-400 animate-pulse': saveLabel.tone === 'saving',
                        'bg-emerald-400': saveLabel.tone === 'success',
                        'bg-red-500': saveLabel.tone === 'error',
                    }"></span>
                    {{ saveLabel.text }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mb-4">
                Responda as perguntas abaixo sobre sua saúde. Ao final, confirme com sua assinatura.
            </p>

            <div class="space-y-3 max-h-[55vh] overflow-y-auto pr-1 -mr-1">
                <AnamnesisCategoryCard
                    v-for="category in anamnese.categories"
                    :key="category.name"
                    :category="category"
                    :answers="anamneseAnswers"
                    readonly
                    @change="onAnamneseAnswerChange"
                />
            </div>

            <p v-if="anamneseError" class="text-sm text-red-600 mt-4">{{ anamneseError }}</p>

            <div class="pt-6">
                <button type="button" @click="showSignatureModal = true"
                        :disabled="saveState.saving || !allRequiredAnamneseQuestionsAnswered"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-medium">
                    Assinar e concluir
                </button>
                <p v-if="!allRequiredAnamneseQuestionsAnswered" class="text-xs text-slate-400 mt-2 text-center">
                    Responda todas as perguntas obrigatórias (*) para continuar.
                </p>
            </div>

            <AnamnesisSignatureModal
                :show="showSignatureModal"
                title="Assinar Anamnese"
                :patient-name="`${patient.nome} ${patient.sobrenome}`"
                :server-error="anamneseError"
                @close="showSignatureModal = false"
                @signed="submitAnamneseSignature"
            />
        </div>

        <!-- Wizard -->
        <div v-else-if="view === 'wizard'" class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 md:p-8">
            <div class="flex items-center justify-between mb-1">
                <div class="flex gap-1.5">
                    <span v-for="(s, i) in STEPS" :key="s" class="h-1.5 w-8 rounded-full"
                          :class="i <= stepIndex ? 'bg-emerald-600' : 'bg-slate-200'"></span>
                </div>
                <span v-if="saveLabel" class="text-xs flex items-center gap-1.5 text-right"
                      :class="saveLabel.tone === 'error' ? 'text-red-600' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="{
                        'bg-amber-400 animate-pulse': saveLabel.tone === 'saving',
                        'bg-emerald-400': saveLabel.tone === 'success',
                        'bg-red-500': saveLabel.tone === 'error',
                    }"></span>
                    {{ saveLabel.text }}
                </span>
            </div>

            <!-- Etapa 1: Dados pessoais -->
            <div v-if="activeStep === 'dados_pessoais'">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Dados pessoais</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input v-model="form.nome" type="text" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.nome" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sobrenome *</label>
                        <input v-model="form.sobrenome" type="text" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.sobrenome" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Data de Nascimento</label>
                        <input v-model="form.nascimento" type="date" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.nascimento" />
                    </div>
                    <template v-if="!form.is_estrangeiro">
                        <div>
                            <label class="block text-sm mb-1">CPF</label>
                            <input v-model="form.cpf" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.cpf" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">RG</label>
                            <input v-model="form.rg" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.rg" />
                        </div>
                    </template>
                    <div v-else class="md:col-span-2">
                        <label class="block text-sm mb-1">Passaporte</label>
                        <input v-model="form.passaporte" type="text" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.passaporte" />
                    </div>
                </div>

                <label class="flex items-center gap-2 mt-3 cursor-pointer select-none w-fit">
                    <input type="checkbox" v-model="form.is_estrangeiro" class="w-4 h-4 rounded accent-emerald-600" />
                    <span class="text-sm text-slate-600">Sou estrangeiro(a)</span>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm mb-1">Sexo</label>
                        <select v-model="form.sexo" class="w-full border rounded-lg p-2.5">
                            <option value="">—</option>
                            <option v-for="opt in SEXO_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.sexo" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Profissão</label>
                        <input v-model="form.profissao" type="text" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.profissao" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm mb-1">Canal preferencial para lembretes</label>
                    <select v-model="canalLembreteSelecionado" class="w-full border rounded-lg p-2.5 transition-opacity"
                            :class="naoEnviarLembretes ? 'opacity-50 cursor-not-allowed' : ''"
                            :disabled="naoEnviarLembretes">
                        <option v-for="opt in CANAL_LEMBRETE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <InputError :message="form.errors.canal_lembrete" />
                    <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="naoEnviarLembretes" class="w-4 h-4 rounded accent-emerald-600" />
                        <span class="text-xs text-slate-600">Não enviar lembretes automáticos</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm mb-1">Telefone</label>
                        <PhoneNumberInput v-model="form.telefone" />
                        <InputError :message="form.errors.telefone" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">E-mail</label>
                        <input v-model="form.email" type="email" class="w-full border rounded-lg p-2.5" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>
            </div>

            <!-- Etapa 2: Endereço -->
            <div v-else-if="activeStep === 'endereco'">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Endereço</h2>
                <AddressFields :model="form" :show-title="false" />
            </div>

            <!-- Etapa 3: Responsável legal / Contato de emergência -->
            <div v-else-if="activeStep === 'responsavel_legal'">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">{{ legalGuardianStepTitle }}</h2>

                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="form.possui_responsavel_legal" class="w-4 h-4 rounded accent-emerald-600" />
                    <span class="text-sm text-slate-700">Tenho um responsável legal</span>
                </label>

                <div v-if="form.possui_responsavel_legal" class="mt-3 rounded-xl border border-slate-200 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Nome</label>
                            <input v-model="form.responsavel_legal_nome" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.responsavel_legal_nome" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Telefone</label>
                            <PhoneNumberInput v-model="form.responsavel_legal_telefone" />
                            <InputError :message="form.errors.responsavel_legal_telefone" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <template v-if="!form.responsavel_legal_estrangeiro">
                            <div>
                                <label class="block text-sm mb-1">CPF</label>
                                <input v-model="form.responsavel_legal_cpf" type="text" class="w-full border rounded-lg p-2.5" />
                                <InputError :message="form.errors.responsavel_legal_cpf" />
                            </div>
                            <div>
                                <label class="block text-sm mb-1">RG</label>
                                <input v-model="form.responsavel_legal_rg" type="text" class="w-full border rounded-lg p-2.5" />
                                <InputError :message="form.errors.responsavel_legal_rg" />
                            </div>
                        </template>
                        <div v-else>
                            <label class="block text-sm mb-1">Passaporte</label>
                            <input v-model="form.responsavel_legal_passaporte" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.responsavel_legal_passaporte" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 mt-3 cursor-pointer select-none w-fit">
                        <input type="checkbox" v-model="form.responsavel_legal_estrangeiro" class="w-4 h-4 rounded accent-emerald-600" />
                        <span class="text-sm text-slate-600">Responsável legal estrangeiro</span>
                    </label>

                    <div class="mt-4">
                        <label class="block text-sm mb-1">Parentesco</label>
                        <select v-model="form.responsavel_legal_parentesco" class="w-full border rounded-lg p-2.5">
                            <option value="">—</option>
                            <option v-for="opt in PARENTESCO_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.responsavel_legal_parentesco" />
                    </div>
                </div>

                <div v-else class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Nome</label>
                            <input v-model="form.contato_emergencia_nome" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.contato_emergencia_nome" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Telefone</label>
                            <PhoneNumberInput v-model="form.contato_emergencia_telefone" />
                            <InputError :message="form.errors.contato_emergencia_telefone" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 4: Convênio (só quando o convite permite) -->
            <div v-else-if="activeStep === 'convenio'">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Convênio</h2>

                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="possuiConvenio" class="w-4 h-4 rounded accent-emerald-600" />
                    <span class="text-sm text-slate-700">Você possui convênio?</span>
                </label>

                <div v-if="possuiConvenio" class="mt-3 rounded-xl border border-slate-200 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Convênio</label>
                            <select v-model="form.convenio_id" class="w-full border rounded-lg p-2.5">
                                <option value="">Selecione</option>
                                <option v-for="c in convenios" :key="c.id" :value="c.id">{{ c.nome }}</option>
                            </select>
                            <InputError :message="form.errors.convenio_id" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Número da carteirinha</label>
                            <input v-model="form.convenio_numero_carteirinha" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.convenio_numero_carteirinha" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 mt-3 cursor-pointer select-none w-fit">
                        <input type="checkbox" v-model="convenioTitularEhPaciente" class="w-4 h-4 rounded accent-emerald-600" />
                        <span class="text-sm text-slate-600">Titular é o próprio paciente</span>
                    </label>

                    <div v-if="!convenioTitularEhPaciente" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm mb-1">Titular</label>
                            <input v-model="form.convenio_titular" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.convenio_titular" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">CPF do titular</label>
                            <input v-model="form.convenio_titular_cpf" type="text" class="w-full border rounded-lg p-2.5" />
                            <InputError :message="form.errors.convenio_titular_cpf" />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Parentesco</label>
                            <select v-model="form.convenio_titular_parentesco" class="w-full border rounded-lg p-2.5">
                                <option value="">—</option>
                                <option v-for="opt in PARENTESCO_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError :message="form.errors.convenio_titular_parentesco" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 flex items-center justify-between">
                <button v-if="stepIndex > 0" type="button" @click="goBack" :disabled="saveState.saving || completing"
                        class="text-sm text-slate-500 hover:text-slate-700 disabled:opacity-50">
                    ← Voltar
                </button>
                <span v-else></span>
                <button type="button" @click="nextStep" :disabled="saveState.saving || completing"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-70 text-white px-6 py-2.5 rounded-lg font-medium">
                    {{ stepIndex === STEPS.length - 1 ? (completing ? 'Concluindo...' : 'Concluir cadastro') : 'Próxima etapa' }}
                </button>
            </div>
        </div>
    </div>
</template>
