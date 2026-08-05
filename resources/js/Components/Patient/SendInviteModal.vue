<script setup>
import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/UI/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { usePhoneDuplicateCheck } from '@/composables/usePhoneDuplicateCheck';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    show: { type: Boolean, default: false },
    anamnesisTemplates: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
const toast = useToast();

const initialForm = () => ({
    nome: '',
    sobrenome: '',
    telefone: '',
    email: '',
    allow_insurance: false,
    allow_anamnesis: false,
    anamnesis_template_id: '',
    channel: 'link_only',
    expires_in_days: '7',
});

const form = reactive(initialForm());
const errors = ref({});
const processing = ref(false);
const kind = ref('cadastro');
const existingPatientId = ref(null);
const confirmingCancelPrevious = ref(false);
const result = ref(null); // { invite, share } após criar

const phoneCheck = usePhoneDuplicateCheck();

function onTelefoneBlur() {
    if (kind.value === 'atualizacao') return; // já resolvido, não refaz a busca
    phoneCheck.lookup(form.telefone);
}

function chooseUpdateExisting() {
    kind.value = 'atualizacao';
    existingPatientId.value = phoneCheck.patient.value.id;
    form.nome = phoneCheck.patient.value.nome;
    form.sobrenome = phoneCheck.patient.value.sobrenome;
}

function chooseCreateNewAnyway() {
    kind.value = 'cadastro';
    existingPatientId.value = null;
    phoneCheck.reset();
}

// Se o telefone mudar depois de já ter resolvido para "atualização", volta
// a checar do zero — o paciente escolhido pode não ser mais o correto.
watch(() => form.telefone, () => {
    if (kind.value === 'atualizacao') {
        kind.value = 'cadastro';
        existingPatientId.value = null;
        phoneCheck.reset();
    }
});

function fmtDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function willCollideWithActiveInvite() {
    return kind.value === 'atualizacao' && phoneCheck.activeInvite.value;
}

function handleSubmitClick() {
    if (willCollideWithActiveInvite() && !confirmingCancelPrevious.value) {
        confirmingCancelPrevious.value = true;
        return;
    }
    submit();
}

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        const { data } = await window.axios.post(route('patient-invites.store'), {
            nome: form.nome,
            sobrenome: form.sobrenome,
            telefone: form.telefone,
            email: form.email || null,
            existing_patient_id: existingPatientId.value,
            kind: kind.value,
            allow_insurance: form.allow_insurance,
            allow_anamnesis: form.allow_anamnesis,
            anamnesis_template_id: form.allow_anamnesis ? (form.anamnesis_template_id || null) : null,
            channel: form.channel,
            expires_in_days: form.expires_in_days === 'never' ? null : Number(form.expires_in_days),
        });

        result.value = data;
        confirmingCancelPrevious.value = false;
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
            if (e.response.data.message) toast.error(e.response.data.message);
        } else {
            toast.error('Não foi possível gerar o convite.');
        }
        confirmingCancelPrevious.value = false;
    } finally {
        processing.value = false;
    }
}

const copied = ref(false);
async function copyLink() {
    try {
        await navigator.clipboard.writeText(result.value.share.url);
        copied.value = true;
        toast.success('Link copiado!');
        setTimeout(() => { copied.value = false; }, 2000);
        await window.axios.post(route('patient-invites.log-event', result.value.invite.id), { action: 'link_copied' });
    } catch {
        toast.error('Não foi possível copiar o link.');
    }
}

async function openWhatsApp() {
    window.open(result.value.share.whatsapp_url, '_blank', 'noopener');
    await window.axios.post(route('patient-invites.log-event', result.value.invite.id), { action: 'whatsapp_link_generated' });
}

function reset() {
    Object.assign(form, initialForm());
    errors.value = {};
    kind.value = 'cadastro';
    existingPatientId.value = null;
    confirmingCancelPrevious.value = false;
    result.value = null;
    phoneCheck.reset();
}

function close() {
    const hadResult = !!result.value;
    reset();
    emit('close');
    if (hadResult) router.reload({ only: ['patients'] });
}
</script>

<template>
    <Modal :show="show" max-width="max-w-lg" title="Enviar cadastro ao paciente" @close="close">
        <div class="p-5">
            <!-- Resultado após gerar o convite -->
            <div v-if="result" class="space-y-4">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Convite gerado com sucesso.
                </div>

                <div>
                    <label class="block text-sm mb-1">Link do convite</label>
                    <div class="flex items-center gap-2">
                        <input :value="result.share.url" readonly class="flex-1 min-w-0 border rounded-lg p-2.5 text-xs text-slate-600 bg-slate-50" />
                        <button type="button" @click="copyLink" class="shrink-0 text-xs font-medium px-3 py-2.5 rounded-lg border border-slate-200 hover:bg-slate-50">
                            {{ copied ? '✓ Copiado' : 'Copiar' }}
                        </button>
                    </div>
                </div>

                <div class="flex justify-center">
                    <img :src="result.share.qrcode_url" alt="QR Code do convite" class="w-40 h-40 border border-slate-200 rounded-lg p-2" />
                </div>

                <div class="flex gap-2">
                    <button v-if="result.share.whatsapp_url" type="button" @click="openWhatsApp"
                            class="flex-1 text-sm font-medium px-4 py-2.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                        Enviar por WhatsApp
                    </button>
                    <span v-if="result.invite.status === 'enviado' && form.channel === 'email'" class="flex-1 text-sm text-center px-4 py-2.5 rounded-lg bg-slate-100 text-slate-600">
                        E-mail enviado
                    </span>
                </div>
            </div>

            <!-- Formulário -->
            <form v-else @submit.prevent="handleSubmitClick" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input v-model="form.nome" type="text" required :disabled="kind === 'atualizacao'"
                               class="w-full border rounded-lg p-2.5 disabled:bg-slate-50 disabled:text-slate-500" />
                        <InputError :message="errors.nome?.[0]" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sobrenome *</label>
                        <input v-model="form.sobrenome" type="text" required :disabled="kind === 'atualizacao'"
                               class="w-full border rounded-lg p-2.5 disabled:bg-slate-50 disabled:text-slate-500" />
                        <InputError :message="errors.sobrenome?.[0]" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm mb-1">Celular *</label>
                    <input v-model="form.telefone" type="text" required @blur="onTelefoneBlur"
                           class="w-full border rounded-lg p-2.5" />
                    <InputError :message="errors.telefone?.[0]" />

                    <div v-if="phoneCheck.checked.value && phoneCheck.patient.value && kind === 'cadastro'"
                         class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <p class="text-amber-800">
                            Encontramos um cadastro existente para
                            <strong>{{ phoneCheck.patient.value.nome }} {{ phoneCheck.patient.value.sobrenome }}</strong>
                            com este telefone.
                        </p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <button type="button" @click="chooseUpdateExisting"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                                Enviar convite de atualização cadastral
                            </button>
                            <button type="button" @click="chooseCreateNewAnyway"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100">
                                Criar outro paciente mesmo assim
                            </button>
                        </div>
                    </div>

                    <div v-if="kind === 'atualizacao'" class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 flex items-center justify-between gap-2">
                        <span>Convite de <strong>atualização cadastral</strong> para {{ phoneCheck.patient.value?.nome }}.</span>
                        <button type="button" @click="chooseCreateNewAnyway" class="text-xs underline shrink-0">desfazer</button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm mb-1">Email</label>
                    <input v-model="form.email" type="email" class="w-full border rounded-lg p-2.5" />
                    <InputError :message="errors.email?.[0]" />
                </div>

                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="form.allow_insurance" class="w-4 h-4 rounded accent-emerald-600" />
                    <span class="text-sm text-slate-700">Permitir preencher convênio</span>
                </label>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="form.allow_anamnesis" class="w-4 h-4 rounded accent-emerald-600" />
                        <span class="text-sm text-slate-700">Solicitar anamnese após cadastro</span>
                    </label>
                    <div v-if="form.allow_anamnesis" class="mt-2">
                        <label class="block text-sm mb-1">Modelo de anamnese</label>
                        <select v-model="form.anamnesis_template_id" class="w-full border rounded-lg p-2.5">
                            <option value="">Selecione</option>
                            <option v-for="t in anamnesisTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <InputError :message="errors.anamnesis_template_id?.[0]" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Canal de envio</label>
                        <select v-model="form.channel" class="w-full border rounded-lg p-2.5">
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                            <option value="link_only">Apenas gerar link</option>
                        </select>
                        <InputError :message="errors.channel?.[0]" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Expiração</label>
                        <select v-model="form.expires_in_days" class="w-full border rounded-lg p-2.5">
                            <option value="7">7 dias</option>
                            <option value="15">15 dias</option>
                            <option value="30">30 dias</option>
                            <option value="never">Nunca</option>
                        </select>
                    </div>
                </div>

                <div v-if="confirmingCancelPrevious" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <p>
                        Este paciente já tem um convite de atualização ativo, enviado em
                        <strong>{{ fmtDateTime(phoneCheck.activeInvite.value.created_at) }}</strong>.
                        Gerar um novo vai cancelar o anterior.
                    </p>
                    <div class="flex gap-2 mt-2">
                        <button type="button" @click="submit" :disabled="processing"
                                class="text-xs font-medium px-3 py-1.5 rounded-lg bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50">
                            Cancelar anterior e continuar
                        </button>
                        <button type="button" @click="confirmingCancelPrevious = false"
                                class="text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100">
                            Voltar
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" :disabled="processing"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-70 text-white px-6 py-2.5 rounded-lg font-medium">
                        {{ processing ? 'Gerando...' : 'Gerar convite' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
