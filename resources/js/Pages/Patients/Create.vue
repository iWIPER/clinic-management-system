<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    nome: '',
    sobrenome: '',
    nascimento: '',
    status: 'ativo',
    doc_tipo: 'cpf',
    doc_numero: '',
    telefone: '',
    email: '',
    contato_emergencia_nome: '',
    contato_emergencia_telefone: '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    estado: '',
    observacoes: '',
});

const submit = () => {
    form.post(route('patients.store'));
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Cadastrar Paciente</h1>
            <Link :href="route('patients.index')" class="text-sm text-slate-500 hover:text-slate-700">← Voltar</Link>
        </div>

        <form @submit.prevent="submit" class="max-w-4xl bg-white p-8 rounded-2xl border space-y-6">
            <!-- Identificação -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Identificação</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input v-model="form.nome" type="text" class="w-full border rounded-lg p-2.5" required />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sobrenome *</label>
                        <input v-model="form.sobrenome" type="text" class="w-full border rounded-lg p-2.5" required />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Data de Nascimento</label>
                        <input v-model="form.nascimento" type="date" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Status</label>
                        <select v-model="form.status" class="w-full border rounded-lg p-2.5">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="falecido">Falecido</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Documentos -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Documentos</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Tipo de documento</label>
                        <select v-model="form.doc_tipo" class="w-full border rounded-lg p-2.5">
                            <option value="cpf">CPF</option>
                            <option value="rg">RG</option>
                            <option value="passaporte">Passaporte</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Número do documento</label>
                        <input v-model="form.doc_numero" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                </div>
            </div>

            <!-- Contato -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Contato</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Telefone</label>
                        <input v-model="form.telefone" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">E-mail</label>
                        <input v-model="form.email" type="email" class="w-full border rounded-lg p-2.5" />
                    </div>
                </div>
            </div>

            <!-- Contato de Emergência -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Contato de Emergência</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome</label>
                        <input v-model="form.contato_emergencia_nome" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Telefone</label>
                        <input v-model="form.contato_emergencia_telefone" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                </div>
            </div>

            <!-- Endereço -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Endereço</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-1">
                        <label class="block text-sm mb-1">CEP</label>
                        <input v-model="form.cep" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm mb-1">Logradouro</label>
                        <input v-model="form.logradouro" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Número</label>
                        <input v-model="form.numero" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Complemento</label>
                        <input v-model="form.complemento" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Bairro</label>
                        <input v-model="form.bairro" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Cidade</label>
                        <input v-model="form.cidade" type="text" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">UF</label>
                        <input v-model="form.estado" maxlength="2" class="w-full border rounded-lg p-2.5" />
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm mb-1">Observações</label>
                <textarea v-model="form.observacoes" rows="3" class="w-full border rounded-lg p-2.5"></textarea>
            </div>

            <div class="pt-4 flex items-center gap-x-4">
                <button type="submit" 
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-70 text-white px-8 py-2.5 rounded-lg font-medium"
                        :disabled="form.processing">
                    {{ form.processing ? 'Salvando...' : 'Cadastrar Paciente' }}
                </button>
                <Link :href="route('patients.index')" class="text-slate-600 hover:text-slate-800">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
