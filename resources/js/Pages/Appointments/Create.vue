<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    patients: Array,
    professionals: Array,
    treatments: Array,
    defaultDate: String,
    defaultTime: { type: String, default: '09:00' },
    prefilledPatientId: [String, Number],
});

const form = useForm({
    patient_id: props.prefilledPatientId || '',
    professional_id: '',
    treatment_id: '',
    start: props.defaultDate + 'T' + props.defaultTime,
    notes: '',
});

const selectedTreatment = ref(null);

// Auto-calculate end time
const endTime = ref('');

watch(() => form.treatment_id, (newId) => {
    selectedTreatment.value = props.treatments.find(t => t.id == newId);
    if (selectedTreatment.value && form.start) {
        calculateEnd();
    }
});

watch(() => form.start, () => {
    if (selectedTreatment.value) {
        calculateEnd();
    }
});

const calculateEnd = () => {
    if (!form.start || !selectedTreatment.value) {
        endTime.value = '';
        return;
    }
    const start = new Date(form.start);
    const duration = selectedTreatment.value.duracao_padrao || 30;
    const end = new Date(start.getTime() + duration * 60000);
    
    endTime.value = end.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

const submit = () => {
    form.post(route('appointments.store'));
};
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl">
            <div class="mb-6 flex items-center gap-x-3">
                <Link :href="route('appointments.index')" class="text-sm text-slate-500 hover:text-slate-700">← Voltar à agenda</Link>
                <h1 class="text-2xl font-semibold">Novo Agendamento</h1>
            </div>

            <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-6">
                <!-- Paciente -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Paciente *</label>
                    <select v-model="form.patient_id" class="w-full border rounded-lg p-3" required>
                        <option value="">Selecione o paciente</option>
                        <option v-for="p in patients" :key="p.id" :value="p.id">
                            {{ p.nome }} {{ p.sobrenome }} {{ p.telefone ? '• ' + p.telefone : '' }}
                        </option>
                    </select>
                </div>

                <!-- Profissional -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Profissional *</label>
                    <select v-model="form.professional_id" class="w-full border rounded-lg p-3" required>
                        <option value="">Selecione o profissional</option>
                        <option v-for="prof in professionals" :key="prof.id" :value="prof.id">{{ prof.name }}</option>
                    </select>
                </div>

                <!-- Tratamento -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Tratamento *</label>
                    <select v-model="form.treatment_id" class="w-full border rounded-lg p-3" required>
                        <option value="">Selecione o tratamento</option>
                        <option v-for="t in treatments" :key="t.id" :value="t.id">
                            {{ t.nome }} ({{ t.duracao_padrao || 30 }} min)
                        </option>
                    </select>
                </div>

                <!-- Data e Hora -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Data e Horário de Início *</label>
                        <input v-model="form.start" type="datetime-local" class="w-full border rounded-lg p-3" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Término Estimado</label>
                        <div class="border rounded-lg p-3 bg-slate-50 text-slate-700 font-medium">
                            {{ endTime || 'Selecione tratamento e horário' }}
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Duração puxada automaticamente do tratamento</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Observações / Motivo</label>
                    <textarea v-model="form.notes" rows="3" class="w-full border rounded-lg p-3" placeholder="Ex: Retorno, urgência..."></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-lg font-medium"
                            :disabled="form.processing">
                        {{ form.processing ? 'Agendando...' : 'Confirmar Agendamento' }}
                    </button>
                    <Link :href="route('appointments.index')" class="ml-4 text-slate-600">Cancelar</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
