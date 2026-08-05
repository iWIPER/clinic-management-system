<script setup>
const props = defineProps({
    patientSignature:  { type: Object,   default: null },
    dentistSignature:  { type: Object,   default: null },
    canSignPatient:    { type: Boolean,  default: false },
    canSignDentist:    { type: Boolean,  default: false },
    onSignPatient:     { type: Function, default: null },
    onSignDentist:     { type: Function, default: null },
})

const fmt = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- ── Card 1: Paciente ── -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-[0_1px_4px_rgba(15,23,42,0.05)] overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    <span class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Assinatura do Paciente</span>
                </div>
                <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                    :class="patientSignature
                        ? 'bg-teal-50 text-teal-700 border border-teal-100'
                        : 'bg-amber-50 text-amber-700 border border-amber-100'"
                >
                    <span class="text-[8px]">{{ patientSignature ? '✔' : '⌛' }}</span>
                    {{ patientSignature ? 'Assinada' : 'Pendente' }}
                </span>
            </div>

            <!-- Assinado -->
            <div v-if="patientSignature" class="px-5 py-4">
                <div class="flex gap-4">
                    <div class="shrink-0">
                        <div class="w-36 h-14 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
                            <img
                                v-if="patientSignature.signature_url"
                                :src="patientSignature.signature_url"
                                alt="Assinatura"
                                class="max-w-full max-h-full object-contain p-1"
                            />
                            <span v-else class="text-[10px] text-slate-400">Sem imagem</span>
                        </div>
                        <p class="text-[9px] text-slate-400 text-center mt-1">{{ patientSignature.method }}</p>
                    </div>
                    <div class="flex-1 grid grid-cols-1 gap-2 min-w-0">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Nome</p>
                            <p class="text-[12px] font-semibold text-slate-800 truncate mt-px">{{ patientSignature.patient_name }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Data e Hora</p>
                            <p class="text-[12px] text-slate-700 mt-px">{{ fmt(patientSignature.signed_at) }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Método</p>
                            <p class="text-[12px] text-slate-700 mt-px">{{ patientSignature.method }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Não assinado -->
            <div v-else class="px-5 py-5 text-center">
                <p class="text-[13px] text-slate-500 mb-3">Aguardando assinatura do paciente.</p>
                <button
                    v-if="canSignPatient && onSignPatient"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-teal-700 transition-colors shadow-sm"
                    @click="onSignPatient"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    Assinar agora
                </button>
            </div>
        </section>

        <!-- ── Card 2: Dentista ── -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-[0_1px_4px_rgba(15,23,42,0.05)] overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Assinatura do Dentista</span>
                </div>
                <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                    :class="dentistSignature
                        ? 'bg-teal-50 text-teal-700 border border-teal-100'
                        : 'bg-slate-50 text-slate-500 border border-slate-200'"
                >
                    <span class="text-[8px]">{{ dentistSignature ? '✔' : '○' }}</span>
                    {{ dentistSignature ? 'Assinada' : 'Pendente' }}
                </span>
            </div>

            <!-- Assinado -->
            <div v-if="dentistSignature" class="px-5 py-4">
                <div class="flex gap-4">
                    <div class="shrink-0">
                        <div class="w-36 h-14 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
                            <img
                                v-if="dentistSignature.signature_url"
                                :src="dentistSignature.signature_url"
                                alt="Assinatura"
                                class="max-w-full max-h-full object-contain p-1"
                            />
                            <span v-else class="text-[10px] text-slate-400">Sem imagem</span>
                        </div>
                        <p class="text-[9px] text-slate-400 text-center mt-1">{{ dentistSignature.method }}</p>
                    </div>
                    <div class="flex-1 grid grid-cols-1 gap-2 min-w-0">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Nome</p>
                            <p class="text-[12px] font-semibold text-slate-800 truncate mt-px">{{ dentistSignature.professional_name }}</p>
                        </div>
                        <div v-if="dentistSignature.professional_cro">
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">CRO</p>
                            <p class="text-[12px] text-slate-700 mt-px">{{ dentistSignature.professional_cro }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Data e Hora</p>
                            <p class="text-[12px] text-slate-700 mt-px">{{ fmt(dentistSignature.signed_at) }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Método</p>
                            <p class="text-[12px] text-slate-700 mt-px">Presencial</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Não assinado -->
            <div v-else class="px-5 py-5 text-center">
                <p class="text-[13px] text-slate-500 mb-3">
                    {{ patientSignature ? 'Aguardando revisão e assinatura do profissional.' : 'Disponível após a assinatura do paciente.' }}
                </p>
                <button
                    v-if="canSignDentist && onSignDentist"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm"
                    @click="onSignDentist"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Assinar como profissional
                </button>
            </div>
        </section>

    </div>
</template>
