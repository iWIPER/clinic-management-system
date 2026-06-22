<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    appointment: Object,
    patients: Array,
    professionals: Array,
    treatments: Array,
});

const form = useForm({
    patient_id: props.appointment.patient_id,
    professional_id: props.appointment.professional_id,
    treatment_id: props.appointment.treatment_id,
    start: props.appointment.start ? props.appointment.start.slice(0, 16) : '',
    status: props.appointment.status,
    notes: props.appointment.notes || '',
});

const selectedTreatment = ref(props.treatments.find(t => t.id == props.appointment.treatment_id));
const endTime = ref('');

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

watch(() => form.treatment_id, (newVal) => {
    selectedTreatment.value = props.treatments.find(t => t.id == newVal);
    calculateEnd();
});
watch(() => form.start, calculateEnd);

const submit = () => {
    form.put(route('appointments.update', props.appointment.id));
};
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-semibold mb-6">Editar Agendamento</h1>

            <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Paciente</label>
                    <select v-model="form.patient_id" class="w-full border rounded-lg p-3">
                        <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.nome }} {{ p.sobrenome }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Profissional</label>
                    <select v-model="form.professional_id" class="w-full border rounded-lg p-3">
                        <option v-for="prof in professionals" :key="prof.id" :value="prof.id">{{ prof.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Tratamento</label>
                    <select v-model="form.treatment_id" class="w-full border rounded-lg p-3">
                        <option v-for="t in treatments" :key="t.id" :value="t.id">{{ t.nome }} ({{ t.duracao_padrao }} min)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Início</label>
                        <input v-model="form.start" type="datetime-local" class="w-full border rounded-lg p-3" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Término</label>
                        <div class="border bg-slate-50 p-3 rounded-lg">{{ endTime || '—' }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Status</label>
                    <select v-model="form.status" class="w-full border rounded-lg p-3">
                        <option value="scheduled">Agendado</option>
                        <option value="confirmed">Confirmado</option>
                        <option value="completed">Realizado</option>
                        <option value="no_show">Faltou</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Observações</label>
                    <textarea v-model="form.notes" rows="3" class="w-full border rounded-lg p-3"></textarea>
                </div>

                <div>
                    <button type="submit" class="bg-emerald-600 text-white px-8 py-3 rounded-lg font-medium" :disabled="form.processing">
                        Salvar Alterações
                    </button>
                    <Link :href="route('appointments.index')" class="ml-4 text-slate-600">Cancelar</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
