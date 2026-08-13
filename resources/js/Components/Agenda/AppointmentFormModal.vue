<script setup>
import { computed, ref, toRef, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import InputError from '@/Components/InputError.vue'
import PatientCombobox from '@/Components/Tasks/PatientCombobox.vue'
import { useAppointmentFormRules } from '@/composables/useAppointmentFormRules'

const NOTES_MAX = 200
const RETURN_REASON_MAX = 500

const props = defineProps({
    show: { type: Boolean, default: false },
    professionals: { type: Array, default: () => [] },
    chairs: { type: Array, default: () => [] },
    availableMarkers: { type: Array, default: () => [] },
    markerLimit: { type: Number, default: 6 },
    considerNationalHolidays: { type: Boolean, default: false },
    holidays: { type: Object, default: () => ({}) },
    businessHours: { type: Object, default: () => ({}) },
    businessHoursEnforced: { type: Boolean, default: false },
    // Contexto de onde o modal foi aberto na Agenda (clique/arraste num
    // dia/horário/cadeira específica, ou o filtro de agenda em foco) —
    // pré-preenche o formulário pra não perder o que o usuário já tinha
    // "mirado" ao abrir.
    prefill: { type: Object, default: () => ({}) },
    // Preserva a semana/filtros visíveis na Agenda através do redirect do
    // backend após salvar (ver AppointmentController::store/update) — sem
    // isso o redirect sempre volta pra semana atual e "Todos".
    redirectWeek: { type: String, default: null },
    redirectProfessionalId: { type: [String, Number], default: null },
    redirectChairId: { type: [String, Number], default: null },
    // Presente = modo edição (mesmo modal, reaproveitado — ver popover da
    // Agenda). Precisa vir com patient/tags/appointment_return carregados
    // (mesmo shape que Index.vue/Fullscreen.vue já usam no popover).
    appointment: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const todayStr = new Date().toISOString().slice(0, 10)

// Agendamento != Procedimento — sem tratamento, duração livre em minutos
// (30 é o padrão, igual formulário avulso de antes). Nada aqui depende de
// Treatment nem de duracao_padrao.
const DEFAULT_DURATION = 30

const isEditMode = computed(() => !!props.appointment)

// appointment.start/end e appointment_return.due_date chegam do backend
// serializados em UTC (Eloquent sempre serializa datetime/date pra JSON em
// UTC, independente do app.timezone) — nunca fatiar a string ISO direto (um
// "15:00" local vira "18:00Z" na serialização). new Date(...) + getters
// locais fazem a mesma conversão que formatTime()/toLocaleString já fazem
// em todo o resto da Agenda (mesma lógica antes usada só em Edit.vue).
const pad = (n) => String(n).padStart(2, '0')
const toLocalDateStr = (iso) => {
    const d = new Date(iso)
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
const toLocalTimeStr = (iso) => {
    const d = new Date(iso)
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const form = useForm({
    patient_id: '',
    professional_id: '',
    chair_id: '',
    date: todayStr,
    time: '09:00',
    duration_minutes: DEFAULT_DURATION,
    status: 'scheduled',
    notes: '',
    return_option: 'none',
    return_date: '',
    return_reason: '',
    tag_ids: [],
    confirmation_requested: false,
    redirect_week: null,
    redirect_professional_id: null,
    redirect_chair_id: null,
})

const patient = ref(null)

// Reabrir o modal sempre parte de um formulário limpo — em modo criação, só
// com o contexto desta abertura (evita herdar paciente/etiquetas de uma
// tentativa anterior); em modo edição, com os dados atuais do agendamento.
watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    showSlotsPanel.value = false
    partialNotice.value = ''

    if (isEditMode.value) {
        const a = props.appointment
        const existingReturn = a.appointment_return
        patient.value = a.patient ?? null
        form.patient_id = a.patient_id
        form.professional_id = String(a.professional_id ?? '')
        form.chair_id = a.chair_id ? String(a.chair_id) : ''
        form.date = toLocalDateStr(a.start)
        form.time = toLocalTimeStr(a.start)
        form.duration_minutes = Math.round((new Date(a.end) - new Date(a.start)) / 60000) || DEFAULT_DURATION
        form.status = a.status
        form.notes = a.notes || ''
        // Um retorno já existente não guarda "qual opção foi escolhida", só a
        // due_date resultante — "custom" mostra essa data concreta pra edição
        // direta, sem tentar adivinhar se era "1 mês" ou outra coisa.
        form.return_option = existingReturn ? 'custom' : 'none'
        form.return_date = existingReturn ? toLocalDateStr(existingReturn.due_date) : ''
        form.return_reason = existingReturn?.reason || ''
        form.tag_ids = (a.tags || []).map(t => t.id)
        form.confirmation_requested = a.confirmation_requested || false
    } else {
        patient.value = null
        form.patient_id = ''
        form.professional_id = String(props.prefill.professionalId ?? '')
        form.chair_id = String(props.prefill.chairId ?? '')
        form.date = props.prefill.date ?? todayStr
        form.time = props.prefill.time ?? '09:00'
        form.duration_minutes = props.prefill.durationMinutes ?? DEFAULT_DURATION
        form.status = 'scheduled'
        form.notes = ''
        form.return_option = 'none'
        form.return_date = ''
        form.return_reason = ''
        form.tag_ids = []
        form.confirmation_requested = false
    }

    form.redirect_week = props.redirectWeek
    form.redirect_professional_id = props.redirectProfessionalId
    form.redirect_chair_id = props.redirectChairId
}, { immediate: true })

watch(patient, (p) => { form.patient_id = p?.id ?? '' })

// Horário ORIGINAL do agendamento (só em modo edição) — usado pra não
// travar edições que não mexem no horário/profissional/cadeira em si (ver
// useAppointmentFormRules::scheduleChangedFromOriginal).
const originalSchedule = computed(() => {
    if (!isEditMode.value) return null
    const a = props.appointment
    return {
        professionalId: String(a.professional_id ?? ''),
        date: toLocalDateStr(a.start),
        time: toLocalTimeStr(a.start),
        durationMinutes: Math.round((new Date(a.end) - new Date(a.start)) / 60000) || DEFAULT_DURATION,
    }
})

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
        originalSchedule,
        businessHoursEnforced: toRef(props, 'businessHoursEnforced'),
    })

// ── "Encontrar horário" — painel com navegação por dia, horários cheios,
// sugestões parciais e "próximo horário disponível", tudo calculado no
// backend (AppointmentController::availableSlots) — nunca inventado aqui. ──
const showSlotsPanel = ref(false)
const slotsDate = ref(todayStr)
const daySlots = ref([])
const partialSlots = ref([])
const nextAvailable = ref(null)
const slotsMessage = ref('')
const loadingSlots = ref(false)
const partialNotice = ref('')

const slotsDateLabel = computed(() => {
    if (!slotsDate.value) return ''
    const label = new Date(`${slotsDate.value}T00:00:00`)
        .toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    return label.charAt(0).toUpperCase() + label.slice(1)
})

async function fetchDaySlots() {
    if (!form.professional_id || !slotsDate.value || isDurationInvalid.value) return
    loadingSlots.value = true
    daySlots.value = []
    partialSlots.value = []
    nextAvailable.value = null
    slotsMessage.value = ''
    try {
        const { data } = await window.axios.get(route('appointments.available-slots'), {
            params: {
                professional_id: form.professional_id,
                date: slotsDate.value,
                duration_minutes: form.duration_minutes,
                chair_id: form.chair_id || undefined,
            },
        })
        daySlots.value = data.slots || []
        partialSlots.value = data.partial_slots || []
        nextAvailable.value = data.next_available || null
        if (data.message) {
            slotsMessage.value = data.message
        } else if (!daySlots.value.length && !partialSlots.value.length && !nextAvailable.value) {
            slotsMessage.value = 'Nenhum horário livre encontrado nos próximos dias.'
        }
    } catch {
        slotsMessage.value = 'Não foi possível buscar horários agora.'
    } finally {
        loadingSlots.value = false
    }
}

function openSlotsPanel() {
    slotsDate.value = form.date || todayStr
    showSlotsPanel.value = true
    fetchDaySlots()
}

function navDay(delta) {
    const d = new Date(`${slotsDate.value}T00:00:00`)
    d.setDate(d.getDate() + delta)
    slotsDate.value = d.toISOString().slice(0, 10)
    fetchDaySlots()
}

// Reabastece o painel sozinho se profissional/cadeira/duração mudarem
// enquanto ele está aberto — evita mostrar sugestões de um cenário que já
// não é mais o que está no formulário.
watch([() => form.professional_id, () => form.chair_id, () => form.duration_minutes], () => {
    if (showSlotsPanel.value) fetchDaySlots()
})

function pickFullSlot(date, time) {
    form.date = date
    form.time = time
    partialNotice.value = ''
    showSlotsPanel.value = false
}

// Sugestão parcial: aceitar significa reduzir a duração pro tamanho da
// janela livre — deixado bem explícito (nunca silencioso) via aviso ao
// lado do campo Duração.
function pickPartialSlot(partial) {
    form.date = slotsDate.value
    form.time = partial.start
    form.duration_minutes = partial.minutes
    partialNotice.value = `Duração ajustada para ${partial.minutes} min — esse é o intervalo livre disponível nesse horário.`
    showSlotsPanel.value = false
}

function useNextAvailable() {
    if (!nextAvailable.value) return
    form.date = nextAvailable.value.date
    form.time = nextAvailable.value.time
    partialNotice.value = ''
    showSlotsPanel.value = false
}

// ── Etiquetas — mesmo catálogo/paleta de PatientTag (marcadores do
// paciente), reaproveitado via availableMarkers (PatientMarkerService). O
// limite de verdade é sempre no backend (união com os demais agendamentos
// do paciente); aqui só evita marcar visualmente mais do que o teto simples
// de novas seleções neste formulário. ──────────────────────────────────
function toggleTag(id) {
    const i = form.tag_ids.indexOf(id)
    if (i === -1) form.tag_ids.push(id)
    else form.tag_ids.splice(i, 1)
}

function submit() {
    const transformed = form.transform((data) => {
        const { date, time, ...rest } = data
        return { ...rest, start: `${date}T${time}` }
    })

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('close'),
    }

    if (isEditMode.value) {
        transformed.put(route('appointments.update', props.appointment.id), options)
    } else {
        transformed.post(route('appointments.store'), options)
    }
}
</script>

<template>
<Modal :show="show" :title="isEditMode ? 'Editar agendamento' : 'Nova consulta'" max-width="max-w-2xl" @close="$emit('close')">
    <form @submit.prevent="submit" class="p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Profissional <span class="text-red-500">*</span></label>
                <select v-model="form.professional_id"
                        class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" required>
                    <option value="">Selecione o profissional</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <InputError :message="form.errors.professional_id" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cadeira</label>
                <select v-model="form.chair_id"
                        class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Sem cadeira definida</option>
                    <option v-for="c in chairs" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError :message="form.errors.chair_id" />
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Paciente <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <div class="flex-1 min-w-0">
                    <PatientCombobox v-model="patient" :allow-empty="false"
                                      search-placeholder="Buscar por nome, telefone ou CPF..." />
                </div>
                <!-- Nova aba: não perde o preenchimento em andamento neste modal. -->
                <a :href="route('patients.create')" target="_blank" rel="noopener"
                   class="shrink-0 flex items-center px-3 rounded-lg border border-slate-300 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    Cadastrar
                </a>
            </div>
            <InputError :message="form.errors.patient_id" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Data <span class="text-red-500">*</span></label>
                <input v-model="form.date" type="date"
                       class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" required />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horário <span class="text-red-500">*</span></label>
                <input v-model="form.time" type="time"
                       :min="dayWindow && !dayWindow.closed ? dayWindow.start : undefined"
                       :max="dayWindow && !dayWindow.closed ? dayWindow.end : undefined"
                       class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" required />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Duração (min) <span class="text-red-500">*</span></label>
                <input v-model="form.duration_minutes" type="number" min="5" max="480" step="5" list="duration-presets"
                       class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" required />
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
        <p v-else-if="partialNotice" class="text-xs text-amber-600 -mt-2">{{ partialNotice }}</p>
        <p v-else class="text-xs text-slate-400 -mt-2">Término estimado: <span class="font-medium text-slate-600">{{ endTime || '—' }}</span></p>

        <InputError :message="form.errors.start" />
        <p v-if="isHoliday" class="text-xs text-red-600 -mt-2">
            Este dia está configurado como feriado ({{ holidayName }}) e não possui atendimento.
        </p>
        <p v-else-if="isOffDay" class="text-xs text-red-600 -mt-2">{{ offDayMessage }}</p>
        <p v-else-if="isOutsideWorkingHours" class="text-xs text-red-600 -mt-2">{{ workingHoursMessage }}</p>

        <div>
            <button type="button" @click="showSlotsPanel ? (showSlotsPanel = false) : openSlotsPanel()"
                    :disabled="!form.professional_id || !form.date || isDurationInvalid"
                    class="text-xs font-medium px-3 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition-colors hover:bg-emerald-100 disabled:opacity-50 disabled:cursor-not-allowed">
                {{ showSlotsPanel ? 'Fechar horários' : 'Encontrar horário' }}
            </button>

            <div v-if="showSlotsPanel" class="mt-2 rounded-lg border border-slate-200 overflow-hidden">
                <!-- Navegação por dia -->
                <div class="flex items-center justify-between px-3 py-2 bg-slate-50 border-b border-slate-200">
                    <button type="button" @click="navDay(-1)" class="p-1 rounded hover:bg-slate-200 text-slate-500">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <span class="text-xs font-medium text-slate-700 text-center">{{ slotsDateLabel }}</span>
                    <button type="button" @click="navDay(1)" class="p-1 rounded hover:bg-slate-200 text-slate-500">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                <div class="p-3 max-h-56 overflow-y-auto space-y-3">
                    <p v-if="loadingSlots" class="text-xs text-slate-400">Buscando horários...</p>

                    <template v-else>
                        <!-- Horários completos -->
                        <div v-if="daySlots.length">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Horários disponíveis</div>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="slot in daySlots" :key="slot" type="button" @click="pickFullSlot(slotsDate, slot)"
                                        class="text-xs font-medium px-2.5 py-1 rounded-full border transition-colors"
                                        :class="form.date === slotsDate && form.time === slot ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    {{ slot }}
                                </button>
                            </div>
                        </div>

                        <!-- Sugestões parciais — visualmente separadas, nunca tratadas como horário cheio -->
                        <div v-if="partialSlots.length">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-amber-500 mb-1.5">Sugestões (disponibilidade parcial)</div>
                            <div class="space-y-1">
                                <button v-for="p in partialSlots" :key="p.start" type="button" @click="pickPartialSlot(p)"
                                        class="w-full flex items-center justify-between text-xs px-2.5 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">
                                    <span class="font-medium">{{ p.start }}</span>
                                    <span>Disponibilidade parcial de {{ p.minutes }} min</span>
                                </button>
                            </div>
                        </div>

                        <!-- Próximo horário disponível (só quando o dia exibido não tem horário cheio) -->
                        <div v-if="nextAvailable" class="rounded-lg border border-emerald-200 bg-emerald-50 p-2.5">
                            <div class="text-xs text-emerald-800">
                                <span class="font-semibold">Próximo horário disponível:</span>
                                {{ new Date(nextAvailable.date + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) }}
                                às {{ nextAvailable.time }}
                            </div>
                            <button type="button" @click="useNextAvailable"
                                    class="mt-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                                Usar este horário
                            </button>
                        </div>

                        <p v-if="slotsMessage" class="text-xs text-slate-400">{{ slotsMessage }}</p>
                    </template>
                </div>
            </div>
        </div>

        <div v-if="isEditMode">
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="form.status"
                    class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500">
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
            <div class="flex items-baseline justify-between mb-1">
                <label class="block text-xs font-medium text-slate-600">Observações</label>
                <span class="text-[10px] text-slate-400 tabular-nums">{{ form.notes.length }}/{{ NOTES_MAX }}</span>
            </div>
            <textarea v-model="form.notes" rows="2" :maxlength="NOTES_MAX" placeholder="Ex: urgência, preferências..."
                      class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            <InputError :message="form.errors.notes" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Retornar em</label>
                <select v-model="form.return_option"
                        class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="none">Sem retorno</option>
                    <option value="15d">15 dias</option>
                    <option value="1m">1 mês</option>
                    <option value="6m">6 meses</option>
                    <option value="12m">12 meses</option>
                    <option value="custom">Outro</option>
                </select>
            </div>
            <div v-if="form.return_option === 'custom'">
                <label class="block text-xs font-medium text-slate-600 mb-1">Data do retorno</label>
                <input v-model="form.return_date" type="date"
                       class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500" />
                <InputError :message="form.errors.return_date" />
            </div>
        </div>
        <div v-if="form.return_option !== 'none'">
            <div class="flex items-baseline justify-between mb-1">
                <label class="block text-xs font-medium text-slate-600">Motivo do retorno</label>
                <span class="text-[10px] text-slate-400 tabular-nums">{{ (form.return_reason || '').length }}/{{ RETURN_REASON_MAX }}</span>
            </div>
            <textarea v-model="form.return_reason" rows="2" :maxlength="RETURN_REASON_MAX" placeholder="Ex: Controle"
                      class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            <InputError :message="form.errors.return_reason" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Etiquetas</label>
            <div v-if="availableMarkers.length" class="flex flex-wrap gap-1.5">
                <button v-for="tag in availableMarkers" :key="tag.id" type="button" @click="toggleTag(tag.id)"
                        class="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full border transition-colors"
                        :class="form.tag_ids.includes(tag.id) ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                    <span class="h-2 w-2 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />
                    {{ tag.name }}
                </button>
            </div>
            <p v-else class="text-xs text-slate-400">Nenhuma etiqueta cadastrada ainda.</p>
            <InputError :message="form.errors.tag_ids" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Enviar confirmação?</label>
            <div class="inline-flex rounded-lg overflow-hidden border border-slate-200 text-xs">
                <button type="button" @click="form.confirmation_requested = true"
                        class="px-3 py-1.5 font-medium transition-colors"
                        :class="form.confirmation_requested ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
                    Sim
                </button>
                <button type="button" @click="form.confirmation_requested = false"
                        class="px-3 py-1.5 font-medium transition-colors border-l border-slate-200"
                        :class="!form.confirmation_requested ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-50'">
                    Não
                </button>
            </div>
            <p v-if="form.confirmation_requested" class="text-[11px] text-slate-400 mt-1">
                Só guarda a preferência — o envio automático ainda não está disponível.
            </p>
        </div>
    </form>

    <template #footer>
        <div class="flex justify-end gap-2">
            <button type="button" @click="$emit('close')"
                    class="px-4 py-2 border rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                Cancelar
            </button>
            <button type="button" @click="submit"
                    :disabled="form.processing || isOffDay || isOutsideWorkingHours || isHoliday || isDurationInvalid"
                    class="px-5 py-2 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                {{ form.processing ? 'Salvando...' : (isEditMode ? 'Salvar alterações' : 'Agendar consulta') }}
            </button>
        </div>
    </template>
</Modal>
</template>
