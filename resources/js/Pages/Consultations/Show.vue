<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ consultation: Object, treatments: Array });

const notes = ref(props.consultation.notes || '');

const updateNotes = () => {
    router.put(route('consultations.update', props.consultation.id), { notes: notes.value });
};

const doStart = () => {
    router.post(route('consultations.start', props.consultation.id));
};

const doFinish = () => {
    router.post(route('consultations.finish', props.consultation.id), { notes: notes.value });
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <Link :href="route('consultations.index')" class="text-sm text-slate-500">← Voltar</Link>
                <h1 class="text-2xl font-semibold mt-1">
                    Atendimento de {{ consultation.patient.nome }} {{ consultation.patient.sobrenome }}
                </h1>
            </div>

            <div class="flex gap-2">
                <button 
                    v-if="consultation.status === 'aguardando'" 
                    @click="doStart"
                    class="bg-blue-600 text-white px-5 py-2 rounded-lg">
                    Iniciar Atendimento
                </button>

                <button 
                    v-if="consultation.status === 'em_atendimento'" 
                    @click="doFinish"
                    class="bg-green-600 text-white px-5 py-2 rounded-lg">
                    Finalizar Consulta
                </button>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- Info -->
            <div class="md:col-span-1 bg-white p-6 rounded-2xl border">
                <div class="text-sm text-slate-500">Profissional</div>
                <div class="font-medium">{{ consultation.professional?.name }}</div>

                <div class="mt-4 text-sm text-slate-500">Check-in</div>
                <div>{{ consultation.check_in_at ? new Date(consultation.check_in_at).toLocaleString('pt-BR') : '—' }}</div>

                <div class="mt-4">
                    <span class="px-3 py-1 rounded-full text-sm" :class="{
                        'bg-yellow-100 text-yellow-700': consultation.status === 'aguardando',
                        'bg-blue-100 text-blue-700': consultation.status === 'em_atendimento',
                        'bg-green-100 text-green-700': consultation.status === 'finalizado',
                    }">
                        {{ consultation.status }}
                    </span>
                </div>
            </div>

            <!-- Fluxo e Prontuário básico -->
            <div class="md:col-span-2 bg-white p-6 rounded-2xl border">
                <h3 class="font-semibold mb-4">Fluxo de Atendimento</h3>

                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-yellow-500 text-white flex items-center justify-center text-sm">1</div>
                        <div>Check-in realizado</div>
                        <div class="text-xs text-slate-400 ml-auto">{{ consultation.check_in_at ? 'OK' : 'Pendente' }}</div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full" :class="consultation.started_at ? 'bg-blue-500 text-white' : 'bg-slate-200'">2</div>
                        <div>Em atendimento</div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full" :class="consultation.finished_at ? 'bg-green-500 text-white' : 'bg-slate-200'">3</div>
                        <div>Finalizado</div>
                    </div>
                </div>

                <div class="mt-8">
                    <label class="block text-sm font-medium mb-2">Anotações / Prontuário (SOAP básico)</label>
                    <textarea 
                        v-model="notes" 
                        rows="8" 
                        class="w-full border rounded-xl p-4 font-mono text-sm"
                        placeholder="S: Subjetivo&#10;O: Objetivo&#10;A: Avaliação&#10;P: Plano"
                    ></textarea>

                    <div class="mt-3 flex gap-3">
                        <button @click="updateNotes" class="px-4 py-2 border rounded-lg text-sm">
                            Salvar anotações
                        </button>
                        
                        <button 
                            v-if="consultation.status === 'em_atendimento'" 
                            @click="doFinish"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg text-sm">
                            Finalizar e salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 bg-white rounded-2xl border p-6">
            <h3 class="font-medium mb-4">Registrar Procedimento Executado</h3>
            
            <form @submit.prevent="addExecution" class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-sm mb-1">Tratamento</label>
                    <!-- For simplicity, we'll use a select. In real, load treatments via props. -->
                    <select v-model="executionForm.treatment_id" class="w-full border rounded p-2" required>
                        <option value="">Selecione...</option>
                        <option v-for="t in treatments" :key="t.id" :value="t.id">{{ t.nome }} ({{ t.duracao_padrao }} min)</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm mb-1">Observações</label>
                    <input v-model="executionForm.notes" class="w-full border rounded p-2" />
                </div>
                <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded">
                    Registrar Execução
                </button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Ao registrar, materiais vinculados serão baixados do estoque automaticamente.</p>
        </div>

        <div class="mt-6 text-xs text-slate-500">
            Próximo: Fotos clínicas + Procedimentos executados serão integrados aqui.
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const executionForm = ref({ treatment_id: '', notes: '' });

const addExecution = () => {
    router.post(route('consultations.add-execution', props.consultation.id), executionForm.value, {
        onSuccess: () => {
            executionForm.value = { treatment_id: '', notes: '' };
        }
    });
};
</script>
