<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, watch } from 'vue'
import AnamnesisSignaturePad from './AnamnesisSignaturePad.vue'

const props = defineProps({
    show:             Boolean,
    professionalName: { type: String, default: '' },
    professionalCro:  { type: String, default: '' },
    serverError:      { type: String, default: '' },
})

const emit = defineEmits(['close', 'signed'])

const pad      = ref(null)
const padEmpty = ref(true)
const saving   = ref(false)
const error    = ref('')

watch(() => props.show, (v) => {
    if (v) {
        padEmpty.value = true
        error.value    = ''
        saving.value   = false
    }
})

watch(() => props.serverError, (v) => {
    if (v) {
        error.value  = v
        saving.value = false
    }
})

const onPadChange = (empty) => { padEmpty.value = empty }

const collectBrowserInfo = () => ({
    browser:            navigator.userAgent,
    platform:           navigator.platform,
    language:           navigator.language,
    screen_width:       window.screen.width,
    screen_height:      window.screen.height,
    device_pixel_ratio: window.devicePixelRatio || 1,
})

const submit = async () => {
    if (padEmpty.value) {
        error.value = 'Realize a assinatura no campo acima.'
        return
    }

    saving.value = true
    error.value  = ''

    try {
        const signatureData = pad.value?.toDataUrl()
        if (!signatureData) throw new Error('Assinatura vazia.')

        emit('signed', {
            signature_data: signatureData,
            timezone:       Intl.DateTimeFormat().resolvedOptions().timeZone,
            browser_info:   collectBrowserInfo(),
        })
    } catch (e) {
        error.value  = e.message || 'Erro ao processar assinatura.'
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
                                <h2 class="text-base font-bold text-slate-900">Assinar como Profissional</h2>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    Assinatura eletrônica com validade nos termos da MP 2.200-2/2001 e Lei 14.063/2020.
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

                            <!-- Identificação do profissional (somente leitura) -->
                            <div class="rounded-xl border border-blue-100 bg-blue-50/40 px-4 py-3 flex items-center gap-3">
                                <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-slate-800 truncate">{{ professionalName }}</p>
                                    <p v-if="professionalCro" class="text-[11px] text-slate-500 mt-px">CRO: {{ professionalCro }}</p>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="flex items-center gap-3">
                                <div class="flex-1 border-t border-slate-100" />
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assinatura</span>
                                <div class="flex-1 border-t border-slate-100" />
                            </div>

                            <!-- Canvas pad (mesmo componente do paciente) -->
                            <AnamnesisSignaturePad ref="pad" @change="onPadChange" />

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
                                class="rounded-lg bg-blue-600 px-5 py-2 text-[12px] font-semibold text-white hover:bg-blue-700 transition-colors disabled:opacity-50 shadow-sm"
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
                                <span v-else>Confirmar assinatura profissional</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
