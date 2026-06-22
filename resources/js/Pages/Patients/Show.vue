<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ patient: Object, isDriveConnected: Boolean });
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex justify-between">
            <div>
                <h1 class="text-3xl font-semibold">{{ patient.nome }} {{ patient.sobrenome }}</h1>
                <div class="text-sm text-slate-500">{{ patient.telefone || 'Sem telefone' }}</div>
            </div>
            <div class="flex gap-3">
                <Link :href="route('patients.edit', patient.id)" class="px-4 py-2 border rounded-lg">Editar</Link>
                <Link :href="route('patients.index')" class="px-4 py-2 text-slate-500">← Voltar à lista</Link>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- Dados básicos -->
            <div class="md:col-span-2 bg-white rounded-2xl border p-6">
                <h3 class="font-medium mb-4">Dados Pessoais</h3>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div><dt class="text-slate-500">Nascimento</dt><dd>{{ patient.nascimento ? new Date(patient.nascimento).toLocaleDateString('pt-BR') : '—' }}</dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd>{{ patient.status }}</dd></div>
                    <div><dt class="text-slate-500">Profissão</dt><dd>{{ patient.profissao || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Estado Civil</dt><dd>{{ patient.estado_civil || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Documento</dt><dd>{{ patient.doc_tipo?.toUpperCase() }} {{ patient.doc_numero || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd>{{ patient.email || '—' }}</dd></div>
                </dl>

                <h3 class="font-medium mt-8 mb-3">Endereço</h3>
                <p class="text-sm text-slate-700">
                    {{ patient.logradouro }} {{ patient.numero }} {{ patient.complemento }}<br>
                    {{ patient.bairro }} — {{ patient.cidade }}/{{ patient.estado }}<br>
                    CEP: {{ patient.cep || '—' }}
                </p>
            </div>

            <!-- Contato emergência + Ações -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border p-6">
                    <h3 class="font-medium mb-3">Contato de Emergência</h3>
                    <p class="text-sm">{{ patient.contato_emergencia_nome || 'Não informado' }}</p>
                    <p class="text-sm text-slate-600">{{ patient.contato_emergencia_telefone || '' }}</p>
                </div>

                <div class="bg-white rounded-2xl border p-6">
                    <h3 class="font-medium mb-4">Próximas Ações</h3>
                    <Link :href="route('appointments.create') + '?patient_id=' + patient.id" class="block w-full text-center bg-emerald-600 text-white py-2 rounded-lg mb-2">
                        + Agendar Consulta
                    </Link>
                    <Link :href="route('patients.edit', patient.id)" class="block w-full text-center border py-2 rounded-lg">
                        Editar Ficha
                    </Link>
                </div>
            </div>
        </div>

        <!-- Galeria de Fotos Clínicas (Google Drive) -->
        <div class="mt-8 bg-white rounded-2xl border p-6">
            <h3 class="font-medium mb-4">Fotos Clínicas (Google Drive do Paciente)</h3>

            <div v-if="!isDriveConnected" class="mb-4 p-3 bg-yellow-100 text-yellow-700 rounded text-sm">
                Google Drive não conectado para esta clínica. 
                <a :href="route('google.connect')" class="underline font-medium">Conectar agora</a>
            </div>

            <form :action="route('patients.photos.upload', patient.id)" method="POST" enctype="multipart/form-data" class="mb-4 flex gap-2">
                <input type="file" name="photo" accept="image/*" class="border p-2 rounded" required />
                <input type="text" name="categoria" placeholder="Categoria (intraoral, etc)" class="border p-2 rounded text-sm" />
                <input type="text" name="dente" placeholder="Dente (ex: 11)" class="border p-2 rounded w-20 text-sm" />
                <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded text-sm">Enviar para Drive</button>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="photo in patient.photos" :key="photo.id" class="border rounded overflow-hidden">
                    <a :href="'https://drive.google.com/file/d/' + photo.drive_file_id + '/view'" target="_blank">
                        <img :src="'https://drive.google.com/thumbnail?id=' + photo.drive_file_id + '&sz=w400'" class="w-full h-32 object-cover" alt="Foto clínica" />
                    </a>
                    <div class="p-2 text-xs">
                        {{ photo.filename }} <br>
                        <span class="text-slate-500">{{ photo.categoria || '' }} {{ photo.dente ? '• ' + photo.dente : '' }}</span>
                    </div>
                </div>
                <div v-if="!patient.photos || patient.photos.length === 0" class="col-span-full text-sm text-slate-400 p-4">
                    Nenhuma foto enviada ainda. Conecte o Google Drive da clínica e envie a primeira foto para criar a pasta do paciente.
                </div>
            </div>
        </div>

        <!-- Histórico (placeholder para futuro) -->
        <div class="mt-8 bg-white rounded-2xl border p-6">
            <h3 class="font-medium mb-4">Histórico (próximo passo)</h3>
            <p class="text-sm text-slate-500">Consultas, agendamentos e prontuários aparecerão aqui.</p>
        </div>
    </AppLayout>
</template>
