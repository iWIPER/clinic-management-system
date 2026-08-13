<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import InputError from '@/Components/InputError.vue';
import { useAppointmentFormRules } from '@/composables/useAppointmentFormRules';

const props = defineProps({
    patients: Array,
    professionals: Array,
    chairs: Array,
    defaultDate: String,
    defaultTime: { type: String, default: '09:00' },
    prefilledPatientId: [String, Number],
    prefilledChairId: [String, Number],
    considerNationalHolidays: { type: Boolean, default: false },
    holidays: { type: Object, default: () => ({}) },
    businessHours: { type: Object, default: () => ({}) },
    businessHoursEnforced: { type: Boolean, default: false },
});

const NOTES_MAX = 200;

// Agendamento != Procedimento — sem tratamento, duração livre em minutos
// (30 é o padrão). O tratamento/procedimento é definido depois, durante o
// atendimento, não na hora de reservar o horário.
const form = useForm({
    patient_id: props.prefilledPatientId || '',
    professional_id: '',
    chair_id: props.prefilledChairId || '',
    date: props.defaultDate,
    time: props.defaultTime,
    duration_minutes: 30,
    notes: '',
});

const { selectedProfessional, endTime, isDurationInvalid, isOffDay, offDayMessage, isOutsideWorkingHours, workingHoursMessage, isHoliday, holidayName, dayWindow } =
    useAppointmentFormRules({
        professionals: toRef(props, 'professionals'),
        professionalId: computed(() => form.professional_id),
        date: computed(() => form.date),
        time: computed(() => form.time),
        durationMinutes: computed(() => form.duration_minutes),
        holidays: toRef(props, 'holidays'),
        considerNationalHolidays: toRef(props, 'considerNationalHolidays'),
        businessHours: toRef(props, 'businessHours'),
        businessHoursEnforced: toRef(props, 'businessHoursEnforced'),
    });

const submit = () => {
    form.transform((data) => {
        const { date, time, ...rest } = data;
        return { ...rest, start: `${date}T${time}` };
    }).post(route('appointments.store'));
};
</script>

<template>
    <AppLayout content-width="md">
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
                    <InputError :message="form.errors.patient_id" />
                </div>

                <!-- Profissional -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Profissional *</label>
                    <select v-model="form.professional_id" class="w-full border rounded-lg p-3" required>
                        <option value="">Selecione o profissional</option>
                        <option v-for="prof in professionals" :key="prof.id" :value="prof.id">{{ prof.name }}</option>
                    </select>
                    <InputError :message="form.errors.professional_id" />
                </div>

                <!-- Cadeira -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Cadeira</label>
                    <select v-model="form.chair_id" class="w-full border rounded-lg p-3">
                        <option value="">Sem cadeira definida</option>
                        <option v-for="c in chairs" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="form.errors.chair_id" />
                </div>

                <!-- Data, Horário e Duração -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Data *</label>
                        <input v-model="form.date" type="date" class="w-full border rounded-lg p-3" required />
                        <InputError :message="form.errors.start" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Horário *</label>
                        <input v-model="form.time" type="time"
                               :min="dayWindow && !dayWindow.closed ? dayWindow.start : undefined"
                               :max="dayWindow && !dayWindow.closed ? dayWindow.end : undefined"
                               class="w-full border rounded-lg p-3" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Duração (min) *</label>
                        <input v-model="form.duration_minutes" type="number" min="5" max="480" step="5" list="duration-presets"
                               class="w-full border rounded-lg p-3" required />
                        <datalist id="duration-presets">
                            <option value="15">15 min</option>
                            <option value="30">30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1h</option>
                            <option value="90">1h30</option>
                            <option value="120">2h</option>
                            <option value="150">2h30</option>
                        </datalist>
                    </div>
                </div>

                <p v-if="isDurationInvalid" class="text-xs text-red-600 -mt-2">Informe uma duração válida, maior que zero.</p>
                <p v-else class="text-xs text-slate-500 -mt-2">Término estimado: <span class="font-medium text-slate-700">{{ endTime || '—' }}</span></p>

                <p v-if="isHoliday" class="text-xs text-red-600 -mt-2">
                    Este dia está configurado como feriado ({{ holidayName }}) e não possui atendimento.
                </p>
                <p v-else-if="isOffDay" class="text-xs text-red-600 -mt-2">{{ offDayMessage }}</p>
                <p v-else-if="isOutsideWorkingHours" class="text-xs text-red-600 -mt-2">{{ workingHoursMessage }}</p>

                <div>
                    <div class="flex items-baseline justify-between mb-1.5">
                        <label class="block text-sm font-medium">Observações</label>
                        <span class="text-xs text-slate-400 tabular-nums">{{ form.notes.length }}/{{ NOTES_MAX }}</span>
                    </div>
                    <textarea v-model="form.notes" rows="3" :maxlength="NOTES_MAX" class="w-full border rounded-lg p-3" placeholder="Ex: urgência, preferências..."></textarea>
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-8 py-3 rounded-lg font-medium"
                            :disabled="form.processing || isOffDay || isOutsideWorkingHours || isHoliday || isDurationInvalid">
                        {{ form.processing ? 'Agendando...' : 'Confirmar Agendamento' }}
                    </button>
                    <Link :href="route('appointments.index')" class="ml-4 text-slate-600">Cancelar</Link>
                </div>
            </form>
    </AppLayout>
</template>
