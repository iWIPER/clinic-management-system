<script setup>
import { ref } from 'vue'

const props = defineProps({
    show:            { type: Boolean, default: false },
    clinicName:      { type: String,  default: '' },
    doctorName:      { type: String,  default: '' },
    patientFullName: { type: String,  default: '' },
})

const emit = defineEmits(['close', 'confirm', 'recovered'])

const STRUCTURE_CATEGORIES = [
    'Fotografias Clínicas', 'Radiografias', 'Exames', 'Documentação', 'Outros',
]

const RECOVERY_STEP_LABELS = [
    'Pasta ClinicFlow',
    'Pasta da Clínica',
    'Pasta do Profissional',
    'Pasta Pacientes',
    'Categorias',
]

const disasterRecoveryChecked = ref(false)
const recoveryInProgress      = ref(false)
const recoveryStepsDone       = ref(0)
const recoveryPhase           = ref('idle') // 'idle' | 'steps' | 'preparing' | 'finalizing'
const recoveryServerDone      = ref(false)
const recoveryAnimDone        = ref(false)

function resetState() {
    disasterRecoveryChecked.value = false
    recoveryInProgress.value      = false
    recoveryPhase.value           = 'idle'
    recoveryStepsDone.value       = 0
    recoveryServerDone.value      = false
    recoveryAnimDone.value        = false
}

function finalizeIfReady() {
    if (!recoveryServerDone.value || !recoveryAnimDone.value) return
    recoveryPhase.value = 'finalizing'
    setTimeout(() => {
        resetState()
        emit('recovered')
        emit('close')
    }, 700)
}

function runRecoveryAnimation() {
    recoveryPhase.value     = 'steps'
    recoveryStepsDone.value = 0
    RECOVERY_STEP_LABELS.forEach((_, i) => {
        setTimeout(() => {
            recoveryStepsDone.value = i + 1
            if (i === RECOVERY_STEP_LABELS.length - 1) {
                setTimeout(() => {
                    if (!recoveryServerDone.value) recoveryPhase.value = 'preparing'
                    recoveryAnimDone.value = true
                    finalizeIfReady()
                }, 450)
            }
        }, 480 * (i + 1))
    })
}

function confirmRecovery() {
    if (!disasterRecoveryChecked.value) return

    recoveryInProgress.value = true
    recoveryServerDone.value = false
    recoveryAnimDone.value   = false
    recoveryStepsDone.value  = 0

    runRecoveryAnimation()
    emit('confirm')
}

/** Called by the parent once its own recreate/resume request settles. */
function finish(success) {
    if (success) {
        recoveryServerDone.value = true
        finalizeIfReady()
    } else {
        recoveryInProgress.value = false
        recoveryPhase.value      = 'idle'
    }
}

function cancel() {
    if (recoveryInProgress.value) return
    resetState()
    emit('close')
}

defineExpose({ finish, cancel })
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div v-if="show"
             class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
             @click.self="cancel">
            <div class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">

                <!-- ── View de loading (recovery em andamento) ────────────── -->
                <template v-if="recoveryInProgress">
                    <div class="bg-amber-600 px-6 py-5 flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-200 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <div>
                            <h2 class="text-base font-bold text-white">Recriando estrutura de armazenamento...</h2>
                            <p class="text-xs text-amber-200 mt-0.5">Aguarde. Isso pode levar alguns instantes.</p>
                        </div>
                    </div>
                    <div class="px-8 py-10 space-y-3">
                        <div v-for="(label, i) in RECOVERY_STEP_LABELS" :key="label"
                             class="flex items-center gap-3 text-sm">
                            <template v-if="recoveryStepsDone > i">
                                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-slate-900 font-medium">{{ label }}</span>
                            </template>
                            <template v-else-if="recoveryStepsDone === i">
                                <svg class="w-4 h-4 animate-spin text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <span class="text-slate-700">{{ label }}</span>
                            </template>
                            <template v-else>
                                <span class="w-4 h-4 rounded-full border-2 border-slate-200 shrink-0 inline-block"></span>
                                <span class="text-slate-400">{{ label }}</span>
                            </template>
                        </div>

                        <Transition
                            enter-active-class="transition duration-500"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0">
                            <div v-if="recoveryStepsDone >= RECOVERY_STEP_LABELS.length"
                                 class="flex items-center gap-3 text-sm">
                                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-emerald-700 font-semibold">Estrutura concluída</span>
                            </div>
                        </Transition>

                        <Transition
                            enter-active-class="transition duration-500"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0">
                            <div v-if="recoveryPhase === 'preparing' || recoveryPhase === 'finalizing'"
                                 class="flex items-center gap-3 text-sm text-slate-500 pt-1">
                                <svg class="w-4 h-4 animate-spin text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <span>Preparando upload...</span>
                            </div>
                        </Transition>
                    </div>
                </template>

                <!-- ── Conteúdo normal do modal ───────────────────────────── -->
                <template v-else>
                    <div class="bg-amber-500 px-6 py-5">
                        <h2 class="text-lg font-bold text-white">⚠ Estrutura de armazenamento removida</h2>
                        <p class="text-sm text-amber-100 mt-1">
                            Foi detectado que a estrutura anteriormente utilizada pelo ClinicFlow para armazenar os arquivos desta clínica não está mais disponível no Google Drive.
                        </p>
                    </div>

                    <div class="px-6 py-5 space-y-5 text-sm text-slate-700">
                        <section>
                            <h3 class="font-semibold text-slate-900 mb-2">O que aconteceu?</h3>
                            <p class="leading-relaxed">
                                Detectamos que a estrutura criada anteriormente pelo ClinicFlow foi removida da conta Google Drive vinculada à clínica.
                            </p>
                            <p class="leading-relaxed mt-2">
                                Como consequência, os arquivos armazenados nessa estrutura poderão não estar mais disponíveis.
                            </p>
                            <p class="leading-relaxed mt-2">
                                Caso tenham sido excluídos permanentemente da conta Google Drive, eles não poderão ser recuperados automaticamente pelo ClinicFlow.
                            </p>
                        </section>

                        <section>
                            <h3 class="font-semibold text-slate-900 mb-2">O que acontecerá agora?</h3>
                            <p class="leading-relaxed">
                                Ao continuar, o ClinicFlow irá recriar automaticamente toda a estrutura de armazenamento utilizando o mesmo padrão anterior.
                            </p>
                            <p class="leading-relaxed mt-2">
                                Após a recriação, o upload que originou esta operação será retomado automaticamente.
                            </p>
                            <p class="leading-relaxed mt-2 font-medium text-slate-800">
                                Você não precisará selecionar novamente o arquivo.
                            </p>
                        </section>

                        <section>
                            <h3 class="font-semibold text-slate-900 mb-3">Estrutura que será criada</h3>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 font-mono text-xs text-slate-700 leading-relaxed">
                                <p>ClinicFlow</p>
                                <p class="pl-4">└── {{ clinicName || 'Clínica' }}</p>
                                <p class="pl-8">└── Dr(a). {{ doctorName || '—' }}</p>
                                <p class="pl-12">└── Pacientes</p>
                                <p class="pl-16">└── {{ patientFullName }}</p>
                                <p v-for="cat in STRUCTURE_CATEGORIES" :key="cat" class="pl-20">├── {{ cat }}</p>
                            </div>
                        </section>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 space-y-2">
                            <p class="font-semibold text-sm">Importante</p>
                            <p>O ClinicFlow não armazena cópias dos arquivos enviados.</p>
                            <p>Todo o armazenamento ocorre exclusivamente na conta Google Drive da clínica.</p>
                            <p>Ao excluir arquivos ou pastas diretamente no Google Drive:</p>
                            <ul class="list-none space-y-1 pl-1">
                                <li>• imagens clínicas poderão ser perdidas;</li>
                                <li>• exames poderão deixar de existir;</li>
                                <li>• documentos do prontuário poderão não ser recuperados;</li>
                                <li>• a recuperação dependerá exclusivamente de backups existentes na conta Google Drive.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-900 space-y-2">
                            <p class="font-semibold text-sm">Responsabilidade pela guarda dos documentos</p>
                            <p>Ao utilizar a integração com o Google Drive, a clínica permanece como custodiante exclusiva dos documentos clínicos e imagens dos pacientes.</p>
                            <p>A guarda, preservação e disponibilidade dessas informações são responsabilidades da própria clínica, conforme a legislação brasileira, a LGPD e as normas éticas aplicáveis aos profissionais e estabelecimentos de saúde.</p>
                            <p>A exclusão indevida de prontuários, exames, radiografias ou documentos clínicos poderá comprometer o histórico assistencial do paciente e gerar riscos jurídicos, regulatórios e éticos.</p>
                        </div>
                    </div>

                    <div class="border-t px-6 py-4 bg-slate-50">
                        <label class="flex items-start gap-3 cursor-pointer select-none text-sm text-slate-700 mb-4">
                            <input type="checkbox" v-model="disasterRecoveryChecked"
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                            <span>Li e compreendi que a estrutura anterior foi removida e autorizo o ClinicFlow a recriar automaticamente uma nova estrutura de armazenamento.</span>
                        </label>
                        <div class="flex justify-end gap-3">
                            <button @click="cancel"
                                    class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">
                                Cancelar
                            </button>
                            <button @click="confirmRecovery"
                                    :disabled="!disasterRecoveryChecked"
                                    class="px-5 py-2 rounded-lg bg-amber-600 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                Recriar estrutura
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </Transition>
</template>
