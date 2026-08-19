<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AddressFields from '@/Components/AddressFields.vue';
import PhoneNumberInput from '@/Components/PhoneNumberInput.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import {
    ORIGEM_OPTIONS,
    SEXO_OPTIONS,
    PARENTESCO_OPTIONS,
    CANAL_LEMBRETE_OPTIONS,
    TIPO_ATENDIMENTO_OPTIONS,
} from '@/lib/patientFormOptions.js';
import { useCanalLembrete, useConvenioTitular } from '@/composables/usePatientFormBehaviors.js';
import { maskCpf } from '@/composables/useInputMasks';

const props = defineProps({
    patient: Object,
    convenios: { type: Array, default: () => [] },
});

const formFields = [
    'nome', 'sobrenome', 'nascimento', 'sexo', 'status', 'status_automatico',
    'is_estrangeiro', 'cpf', 'rg', 'passaporte', 'profissao', 'canal_lembrete',
    'telefone', 'email',
    'possui_responsavel_legal', 'responsavel_legal_nome', 'responsavel_legal_cpf',
    'responsavel_legal_rg', 'responsavel_legal_estrangeiro', 'responsavel_legal_passaporte',
    'responsavel_legal_telefone', 'responsavel_legal_parentesco',
    'contato_emergencia_nome', 'contato_emergencia_telefone',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
    'origem', 'convenio_id', 'tipo_atendimento', 'convenio_numero_carteirinha',
    'convenio_titular', 'convenio_titular_cpf', 'convenio_titular_parentesco',
    'tipo_atendimento_outro_descricao',
];

const toFormValue = (value) => (value == null ? '' : String(value));

const buildFormData = (patient) => ({
    nome:                          toFormValue(patient.nome),
    sobrenome:                     toFormValue(patient.sobrenome),
    nascimento:                    patient.nascimento != null ? String(patient.nascimento).slice(0, 10) : '',
    sexo:                          toFormValue(patient.sexo),
    status:                        toFormValue(patient.status),
    status_automatico:             patient.status_automatico !== false,
    is_estrangeiro:                !!patient.is_estrangeiro,
    cpf:                           toFormValue(patient.cpf),
    rg:                            toFormValue(patient.rg),
    passaporte:                    toFormValue(patient.passaporte),
    profissao:                     toFormValue(patient.profissao),
    canal_lembrete:                patient.canal_lembrete || 'nao_enviar',
    telefone:                      toFormValue(patient.telefone),
    email:                         toFormValue(patient.email),
    possui_responsavel_legal:      !!patient.possui_responsavel_legal,
    responsavel_legal_nome:        toFormValue(patient.responsavel_legal_nome),
    responsavel_legal_cpf:         toFormValue(patient.responsavel_legal_cpf),
    responsavel_legal_rg:          toFormValue(patient.responsavel_legal_rg),
    responsavel_legal_estrangeiro: !!patient.responsavel_legal_estrangeiro,
    responsavel_legal_passaporte:  toFormValue(patient.responsavel_legal_passaporte),
    responsavel_legal_telefone:    toFormValue(patient.responsavel_legal_telefone),
    responsavel_legal_parentesco:  toFormValue(patient.responsavel_legal_parentesco),
    contato_emergencia_nome:       toFormValue(patient.contato_emergencia_nome),
    contato_emergencia_telefone:   toFormValue(patient.contato_emergencia_telefone),
    cep:                           toFormValue(patient.cep),
    logradouro:                    toFormValue(patient.logradouro),
    numero:                        toFormValue(patient.numero),
    complemento:                   toFormValue(patient.complemento),
    bairro:                        toFormValue(patient.bairro),
    cidade:                        toFormValue(patient.cidade),
    estado:                        toFormValue(patient.estado),
    origem:                        patient.origem || 'manual',
    convenio_id:                   toFormValue(patient.convenio_id),
    tipo_atendimento:              patient.tipo_atendimento || 'particular',
    convenio_numero_carteirinha:   toFormValue(patient.convenio_numero_carteirinha),
    convenio_titular:              toFormValue(patient.convenio_titular),
    convenio_titular_cpf:          toFormValue(patient.convenio_titular_cpf),
    convenio_titular_parentesco:   toFormValue(patient.convenio_titular_parentesco),
    tipo_atendimento_outro_descricao: toFormValue(patient.tipo_atendimento_outro_descricao),
});

const syncFormFromPatient = () => {
    const data = buildFormData(props.patient);
    formFields.forEach((field) => {
        form[field] = data[field];
    });
};

const form = useForm(buildFormData(props.patient));

watch(
    () => `${props.patient.id}:${props.patient.updated_at}`,
    () => syncFormFromPatient(),
);

const { naoEnviarLembretes, canalLembreteSelecionado } = useCanalLembrete(form);
const { convenioTitularEhPaciente } = useConvenioTitular(form);

const onCpfInput = (e) => { form.cpf = maskCpf(e.target.value); };
const onResponsavelLegalCpfInput = (e) => { form.responsavel_legal_cpf = maskCpf(e.target.value); };
const onConvenioTitularCpfInput = (e) => { form.convenio_titular_cpf = maskCpf(e.target.value); };

const submit = () => {
    form.put(route('patients.update', props.patient.id));
};
</script>

<template>
    <AppLayout content-width="lg">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Editar Paciente</h1>
            <Link :href="route('patients.show', patient.id)" class="text-sm text-slate-500 hover:text-slate-700">Ver ficha →</Link>
        </div>

        <form
            :key="`${patient.id}-${patient.updated_at}`"
            @submit.prevent="submit"
            class="max-w-4xl bg-white p-8 rounded-2xl border space-y-6"
        >
            <!-- Identificação -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Identificação</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input v-model="form.nome" type="text" class="w-full border rounded-lg p-2.5" required />
                        <InputError :message="form.errors.nome" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sobrenome *</label>
                        <input v-model="form.sobrenome" type="text" class="w-full border rounded-lg p-2.5" required />
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
                            <input :value="form.cpf" @input="onCpfInput" type="text" placeholder="000.000.000-00" class="w-full border rounded-lg p-2.5" />
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
                    <span class="text-sm text-slate-600">Paciente estrangeiro</span>
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="space-y-2">
                        <div>
                            <label class="block text-sm mb-1">Status</label>
                            <select v-model="form.status"
                                    class="w-full border rounded-lg p-2.5 transition-opacity"
                                    :class="form.status_automatico ? 'opacity-50 cursor-not-allowed' : ''"
                                    :disabled="form.status_automatico">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                                <option value="falecido">Falecido</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="form.status_automatico"
                                   class="w-4 h-4 rounded accent-emerald-600" />
                            <span class="text-xs text-slate-600">
                                Calcular status automaticamente com base nos procedimentos realizados
                            </span>
                        </label>
                    </div>
                    <div>
                        <div>
                            <label class="block text-sm mb-1">Canal preferencial para lembretes</label>
                            <select v-model="canalLembreteSelecionado"
                                    class="w-full border rounded-lg p-2.5 transition-opacity"
                                    :class="naoEnviarLembretes ? 'opacity-50 cursor-not-allowed' : ''"
                                    :disabled="naoEnviarLembretes">
                                <option v-for="opt in CANAL_LEMBRETE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError :message="form.errors.canal_lembrete" />
                        </div>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="naoEnviarLembretes" class="w-4 h-4 rounded accent-emerald-600" />
                            <span class="text-xs text-slate-600">Não enviar lembretes automáticos</span>
                        </label>
                    </div>
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

            <!-- Responsável legal -->
            <div>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="form.possui_responsavel_legal" class="w-4 h-4 rounded accent-emerald-600" />
                    <span class="text-xs text-slate-600">Paciente possui responsável legal</span>
                </label>

                <div v-if="form.possui_responsavel_legal" class="mt-3 rounded-xl border border-slate-200 p-4">
                    <h4 class="font-medium text-slate-700 mb-3">Responsável Legal</h4>

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
                                <input :value="form.responsavel_legal_cpf" @input="onResponsavelLegalCpfInput" type="text" placeholder="000.000.000-00" class="w-full border rounded-lg p-2.5" />
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm mb-1">Parentesco</label>
                            <select v-model="form.responsavel_legal_parentesco" class="w-full border rounded-lg p-2.5">
                                <option value="">—</option>
                                <option v-for="opt in PARENTESCO_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError :message="form.errors.responsavel_legal_parentesco" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contato de Emergência (só quando não há responsável legal) -->
            <div v-if="!form.possui_responsavel_legal" class="!mt-8">
                <h3 class="font-medium text-slate-700 mb-3">Contato de Emergência</h3>
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

            <AddressFields :model="form" />

            <!-- Informações administrativas -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Informações administrativas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Origem do paciente</label>
                        <select v-model="form.origem" class="w-full border rounded-lg p-2.5">
                            <option v-for="opt in ORIGEM_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.origem" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Forma de pagamento</label>
                        <select v-model="form.tipo_atendimento" class="w-full border rounded-lg p-2.5">
                            <option v-for="opt in TIPO_ATENDIMENTO_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.tipo_atendimento" />
                    </div>
                </div>

                <div v-if="form.tipo_atendimento === 'convenio'" class="mt-4">
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
                            <input :value="form.convenio_titular_cpf" @input="onConvenioTitularCpfInput" type="text" placeholder="000.000.000-00" class="w-full border rounded-lg p-2.5" />
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

                <div v-else-if="form.tipo_atendimento === 'outro'" class="mt-4">
                    <label class="block text-sm mb-1">Descrição</label>
                    <input v-model="form.tipo_atendimento_outro_descricao" type="text" class="w-full border rounded-lg p-2.5" />
                    <InputError :message="form.errors.tipo_atendimento_outro_descricao" />
                </div>
            </div>

            <div class="pt-4 flex items-center gap-x-4">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-70 text-white px-8 py-2.5 rounded-lg font-medium"
                        :disabled="form.processing">
                    {{ form.processing ? 'Salvando...' : 'Salvar Alterações' }}
                </button>
                <Link :href="route('patients.index')" class="text-slate-600 hover:text-slate-800">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
