<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, watch } from 'vue'
import AnamnesisSignaturePad from './AnamnesisSignaturePad.vue'

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Assinar Anamnese' },
    patientName: { type: String, default: '' },
    serverError: { type: String, default: '' },
})

const emit = defineEmits(['close', 'signed'])

const pad = ref(null)
const padEmpty = ref(true)
const saving = ref(false)
const error = ref('')

const form = ref({
    patient_name: '',
    patient_cpf: '',
    patient_email: '',
})

watch(() => props.show, (v) => {
    if (v) {
        form.value = { patient_name: props.patientName || '', patient_cpf: '', patient_email: '' }
        padEmpty.value = true
        error.value = ''
        saving.value = false
    }
})

watch(() => props.serverError, (v) => {
    if (v) {
        error.value = v
        saving.value = false
    }
})

const onPadChange = (empty) => {
    padEmpty.value = empty
}

const collectBrowserInfo = () => ({
    browser: navigator.userAgent,
    platform: navigator.platform,
    language: navigator.language,
    screen_width: window.screen.width,
    screen_height: window.screen.height,
    device_pixel_ratio: window.devicePixelRatio || 1,
})

const getGeolocation = () =>
    new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null)
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
            () => resolve(null),
            { timeout: 3000 }
        )
    })

const submit = async () => {
    if (!form.value.patient_name.trim()) {
        error.value = 'Informe o nome do paciente.'
        return
    }
    if (padEmpty.value) {
        error.value = 'Realize a assinatura no campo acima.'
        return
    }

    saving.value = true
    error.value = ''

    try {
        const signatureData = pad.value?.toDataUrl()
        if (!signatureData) throw new Error('Assinatura vazia.')

        const [geolocation] = await Promise.all([getGeolocation()])

        emit('signed', {
            signature_data: signatureData,
            patient_name: form.value.patient_name.trim(),
            patient_cpf: form.value.patient_cpf.trim() || null,
            patient_email: form.value.patient_email.trim() || null,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            browser_info: collectBrowserInfo(),
            geolocation,
        })
    } catch (e) {
        error.value = e.message || 'Erro ao processar assinatura.'
        saving.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            leave-active-class="transition-all duration-150 ease-in"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    leave-active-class="transition-all duration-150 ease-in"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="show" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-100">

                        <!-- Header -->
                        <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-100">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    A assinatura tem validade jurídica nos termos da MP 2.200-2/2001 e Lei 14.063/2020.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors ml-2 shrink-0"
                                @click="emit('close')"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="px-6 py-4 space-y-4">

                            <!-- Patient info -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2">
                                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">
                                        Nome completo <span class="text-red-400">*</span>
                                    </label>
                                    <input
                                        v-model="form.patient_name"
                                        type="text"
                                        placeholder="Nome do paciente"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20 transition-all placeholder:text-slate-300"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">CPF</label>
                                    <input
                                        v-model="form.patient_cpf"
                                        type="text"
                                        placeholder="000.000.000-00"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20 transition-all placeholder:text-slate-300"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">E-mail</label>
                                    <input
                                        v-model="form.patient_email"
                                        type="email"
                                        placeholder="email@exemplo.com"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20 transition-all placeholder:text-slate-300"
                                    />
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="flex items-center gap-3">
                                <div class="flex-1 border-t border-slate-100" />
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assinatura</span>
                                <div class="flex-1 border-t border-slate-100" />
                            </div>

                            <!-- Canvas pad -->
                            <AnamnesisSignaturePad ref="pad" @change="onPadChange" />

                            <!-- Google option (future) -->
                            <div class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 px-3 py-2">
                                <svg class="w-4 h-4 text-slate-300 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#94a3b8"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#cbd5e1"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#e2e8f0"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#f1f5f9"/>
                                </svg>
                                <span class="text-[11px] text-slate-400 flex-1">Entrar com Google</span>
                                <span class="text-[9px] font-semibold text-slate-300 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded">Em breve</span>
                            </div>

                            <InputError :message="error" />
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-2 px-6 pb-5">
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 transition-colors border border-slate-200"
                                @click="emit('close')"
                            >Cancelar</button>
                            <button
                                type="button"
                                class="rounded-lg bg-teal-600 px-5 py-2 text-[12px] font-semibold text-white hover:bg-teal-700 transition-colors disabled:opacity-50 shadow-sm"
                                :disabled="saving || padEmpty"
                                @click="submit"
                            >
                                <span v-if="saving" class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    Salvando…
                                </span>
                                <span v-else>Confirmar assinatura</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
