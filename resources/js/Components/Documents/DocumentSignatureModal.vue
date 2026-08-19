<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, watch } from 'vue'
import AnamnesisSignaturePad from '@/Components/Anamnesis/AnamnesisSignaturePad.vue'
import FormGrid from '@/Components/UI/FormGrid.vue'

const props = defineProps({
    show: Boolean,
    role: { type: String, default: 'patient' },
    roleLabel: { type: String, default: 'Paciente' },
    defaultName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'signed'])

const pad = ref(null)
const padEmpty = ref(true)
const saving = ref(false)
const error = ref('')

const form = ref({ signer_name: '', signer_cpf: '', signer_email: '' })

watch(() => props.show, (v) => {
    if (v) {
        form.value = { signer_name: props.role === 'professional' ? '' : (props.defaultName || ''), signer_cpf: '', signer_email: '' }
        padEmpty.value = true
        error.value = ''
        saving.value = false
    }
})

const onPadChange = (empty) => { padEmpty.value = empty }

const collectBrowserInfo = () => ({
    browser: navigator.userAgent,
    platform: navigator.platform,
    language: navigator.language,
    screen_width: window.screen.width,
    screen_height: window.screen.height,
    device_pixel_ratio: window.devicePixelRatio || 1,
})

const resetSaving = () => { saving.value = false }
defineExpose({ resetSaving, setError: (msg) => { error.value = msg; saving.value = false } })

const submit = () => {
    if (props.role !== 'professional' && !form.value.signer_name.trim()) {
        error.value = 'Informe o nome do signatário.'
        return
    }
    if (padEmpty.value) {
        error.value = 'Realize a assinatura no campo acima.'
        return
    }

    const signatureData = pad.value?.toDataUrl()
    if (!signatureData) {
        error.value = 'Assinatura vazia.'
        return
    }

    saving.value = true
    error.value = ''

    const payload = {
        signature_data: signatureData,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        browser_info: collectBrowserInfo(),
    }

    if (props.role !== 'professional') {
        payload.signer_name = form.value.signer_name.trim()
        payload.signer_cpf = form.value.signer_cpf.trim() || null
        payload.signer_email = form.value.signer_email.trim() || null
    }

    emit('signed', payload)
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
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-100">
                    <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Assinar como {{ roleLabel }}</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                A assinatura tem validade jurídica nos termos da MP 2.200-2/2001 e Lei 14.063/2020.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors ml-2 shrink-0" @click="emit('close')">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <FormGrid v-if="role !== 'professional'" :cols="2">
                            <div class="sm:col-span-2">
                                <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">Nome completo <span class="text-red-400">*</span></label>
                                <input v-model="form.signer_name" type="text" placeholder="Nome do signatário" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">CPF</label>
                                <input v-model="form.signer_cpf" type="text" placeholder="000.000.000-00" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1 block">E-mail</label>
                                <input v-model="form.signer_email" type="email" placeholder="email@exemplo.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20" />
                            </div>
                        </FormGrid>

                        <div class="flex items-center gap-3">
                            <div class="flex-1 border-t border-slate-100" />
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assinatura</span>
                            <div class="flex-1 border-t border-slate-100" />
                        </div>

                        <AnamnesisSignaturePad ref="pad" @change="onPadChange" />

                        <InputError :message="error" />
                    </div>

                    <div class="flex items-center justify-end gap-2 px-6 pb-5">
                        <button type="button" class="rounded-lg px-4 py-2 text-[12px] font-medium text-slate-600 hover:bg-slate-50 transition-colors border border-slate-200" @click="emit('close')">Cancelar</button>
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
            </div>
        </Transition>
    </Teleport>
</template>
