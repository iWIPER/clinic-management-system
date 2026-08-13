<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed, ref, toRef } from 'vue';
import InputError from '@/Components/InputError.vue';
import { useAppointmentFormRules } from '@/composables/useAppointmentFormRules';

const props = defineProps({
    appointment: Object,
    patients: Array,
    professionals: Array,
    chairs: Array,
    availableMarkers: { type: Array, default: () => [] },
    markerLimit: { type: Number, default: 6 },
    considerNationalHolidays: { type: Boolean, default: false },
    holidays: { type: Object, default: () => ({}) },
    businessHours: { type: Object, default: () => ({}) },
    businessHoursEnforced: { type: Boolean, default: false },
});

const NOTES_MAX = 200;
const RETURN_REASON_MAX = 500;

const existingReturn = props.appointment.appointment_return;

// appointment.start/end e appointment_return.due_date chegam do backend
// serializados em UTC (Eloquent sempre serializa datetime/date pra JSON em
// UTC, independente do app.timezone) — nunca fatiar a string ISO direto
// (um "15:00" local vira "18:00Z" na serialização). new Date(...) +
// getters locais fazem a mesma conversão que formatTime()/toLocaleString
// já fazem em todo o resto da Agenda.
const pad = (n) => String(n).padStart(2, '0');
const toLocalDateStr = (iso) => {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};
const toLocalTimeStr = (iso) => {
    const d = new Date(iso);
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

// Agendamento != Procedimento — sem tratamento aqui, igual à criação. Mesmo
// conceito, mesmos campos (paciente/profissional/cadeira/data/horário/
// duração/observações/etiquetas/retorno/confirmação), só que com Status
// editável a mais (só faz sentido numa consulta que já existe).
const form = useForm({
    patient_id: props.appointment.patient_id,
    professional_id: props.appointment.professional_id,
    chair_id: props.appointment.chair_id || '',
    date: toLocalDateStr(props.appointment.start),
    time: toLocalTimeStr(props.appointment.start),
    duration_minutes: Math.round((new Date(props.appointment.end) - new Date(props.appointment.start)) / 60000) || 30,
    status: props.appointment.status,
    notes: props.appointment.notes || '',
    // Um retorno já existente não guarda "qual opção foi escolhida", só a
    // due_date resultante — "custom" mostra essa data concreta pra edição
    // direta, sem tentar adivinhar se era "1 mês" ou outra coisa.
    return_option: existingReturn ? 'custom' : 'none',
    return_date: existingReturn ? toLocalDateStr(existingReturn.due_date) : '',
    return_reason: existingReturn?.reason || '',
    tag_ids: (props.appointment.tags || []).map(t => t.id),
    confirmation_requested: props.appointment.confirmation_requested || false,
});

// Horário ORIGINAL do agendamento — usado pra não travar edições que não
// mexem no horário/profissional/cadeira em si (ver useAppointmentFormRules
// ::scheduleChangedFromOriginal). Edit.vue está sempre em modo edição.
const originalSchedule = computed(() => ({
    professionalId: String(props.appointment.professional_id ?? ''),
    date: toLocalDateStr(props.appointment.start),
    time: toLocalTimeStr(props.appointment.start),
    durationMinutes: Math.round((new Date(props.appointment.end) - new Date(props.appointment.start)) / 60000) || 30,
}));

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
        originalSchedule,
    });

function toggleTag(id) {
    const i = form.tag_ids.indexOf(id);
    if (i === -1) form.tag_ids.push(id);
    else form.tag_ids.splice(i, 1);
}

const submit = () => {
    form.transform((data) => {
        const { date, time, ...rest } = data;
        return { ...rest, start: `${date}T${time}` };
    }).put(route('appointments.update', props.appointment.id));
};
</script>

<template>
    <AppLayout content-width="md">
        <div class="mb-6 flex items-center gap-x-3">
            <Link :href="route('appointments.index')" class="text-sm text-slate-500 hover:text-slate-700">← Voltar à agenda</Link>
            <h1 class="text-2xl font-semibold">Editar Agendamento</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white p-8 rounded-2xl border space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Paciente</label>
                    <select v-model="form.patient_id" class="w-full border rounded-lg p-3">
                        <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.nome }} {{ p.sobrenome }}</option>
                    </select>
                    <InputError :message="form.errors.patient_id" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Profissional</label>
                    <select v-model="form.professional_id" class="w-full border rounded-lg p-3">
                        <option v-for="prof in professionals" :key="prof.id" :value="prof.id">{{ prof.name }}</option>
                    </select>
                    <InputError :message="form.errors.professional_id" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Cadeira</label>
                <select v-model="form.chair_id" class="w-full border rounded-lg p-3">
                    <option value="">Sem cadeira definida</option>
                    <option v-for="c in chairs" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError :message="form.errors.chair_id" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Data</label>
                    <input v-model="form.date" type="date" class="w-full border rounded-lg p-3" />
                    <InputError :message="form.errors.start" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Horário</label>
                    <input v-model="form.time" type="time"
                           :min="dayWindow && !dayWindow.closed ? dayWindow.start : undefined"
                           :max="dayWindow && !dayWindow.closed ? dayWindow.end : undefined"
                           class="w-full border rounded-lg p-3" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Duração (min)</label>
                    <input v-model="form.duration_minutes" type="number" min="5" max="480" step="5" list="duration-presets"
                           class="w-full border rounded-lg p-3" />
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
                <label class="block text-sm font-medium mb-1.5">Status</label>
                <select v-model="form.status" class="w-full border rounded-lg p-3">
                    <option value="scheduled">Agendada</option>
                    <option value="confirmed">Confirmada</option>
                    <option value="in_attendance">Em atendimento</option>
                    <option value="completed">Concluída</option>
                    <option value="no_show">Faltou</option>
                    <option value="cancelled">Cancelada</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <label class="block text-sm font-medium">Observações</label>
                    <span class="text-xs text-slate-400 tabular-nums">{{ form.notes.length }}/{{ NOTES_MAX }}</span>
                </div>
                <textarea v-model="form.notes" rows="3" :maxlength="NOTES_MAX" class="w-full border rounded-lg p-3"></textarea>
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Retornar em</label>
                    <select v-model="form.return_option" class="w-full border rounded-lg p-3">
                        <option value="none">Sem retorno</option>
                        <option value="15d">15 dias</option>
                        <option value="1m">1 mês</option>
                        <option value="6m">6 meses</option>
                        <option value="12m">12 meses</option>
                        <option value="custom">Outro</option>
                    </select>
                </div>
                <div v-if="form.return_option === 'custom'">
                    <label class="block text-sm font-medium mb-1.5">Data do retorno</label>
                    <input v-model="form.return_date" type="date" class="w-full border rounded-lg p-3" />
                    <InputError :message="form.errors.return_date" />
                </div>
            </div>
            <div v-if="form.return_option !== 'none'">
                <div class="flex items-baseline justify-between mb-1.5">
                    <label class="block text-sm font-medium">Motivo do retorno</label>
                    <span class="text-xs text-slate-400 tabular-nums">{{ (form.return_reason || '').length }}/{{ RETURN_REASON_MAX }}</span>
                </div>
                <textarea v-model="form.return_reason" rows="2" :maxlength="RETURN_REASON_MAX" class="w-full border rounded-lg p-3" placeholder="Ex: Controle"></textarea>
                <InputError :message="form.errors.return_reason" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Etiquetas</label>
                <div v-if="availableMarkers.length" class="flex flex-wrap gap-1.5">
                    <button v-for="tag in availableMarkers" :key="tag.id" type="button" @click="toggleTag(tag.id)"
                            class="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-full border transition-colors"
                            :class="form.tag_ids.includes(tag.id) ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'">
                        <span class="h-2 w-2 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />
                        {{ tag.name }}
                    </button>
                </div>
                <p v-else class="text-xs text-slate-400">Nenhuma etiqueta cadastrada ainda.</p>
                <InputError :message="form.errors.tag_ids" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Enviar confirmação?</label>
                <div class="inline-flex rounded-lg overflow-hidden border border-slate-300 text-xs">
                    <button type="button" @click="form.confirmation_requested = true"
                            class="px-4 py-2 font-medium transition-colors"
                            :class="form.confirmation_requested ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
                        Sim
                    </button>
                    <button type="button" @click="form.confirmation_requested = false"
                            class="px-4 py-2 font-medium transition-colors border-l border-slate-300"
                            :class="!form.confirmation_requested ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
                        Não
                    </button>
                </div>
                <p v-if="form.confirmation_requested" class="text-[11px] text-slate-400 mt-1">
                    Só guarda a preferência — o envio automático ainda não está disponível.
                </p>
            </div>

            <div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-8 py-3 rounded-lg font-medium"
                        :disabled="form.processing || isOffDay || isOutsideWorkingHours || isHoliday || isDurationInvalid">
                    {{ form.processing ? 'Salvando...' : 'Salvar Alterações' }}
                </button>
                <Link :href="route('appointments.index')" class="ml-4 text-slate-600">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
