<script setup>
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
    templateName: String,
    customName: { type: String, default: null },
    displayName: String,
    progress: { type: Number, default: 0 },
    answeredCount: { type: Number, default: 0 },
    totalQuestions: { type: Number, default: 0 },
    alertCount: { type: Number, default: 0 },
    saving: Boolean,
    savedAt: { type: [Date, null], default: null },
    anamnesisDate: { type: String, default: null },
})

const emit = defineEmits(['rename', 'update-date'])

// ─── Rename logic ───
const renaming = ref(false)
const renameValue = ref('')
const renameInput = ref(null)

const startRename = async () => {
    renameValue.value = props.customName || props.templateName || ''
    renaming.value = true
    await nextTick()
    renameInput.value?.select()
}

const commitRename = () => {
    renaming.value = false
    const v = renameValue.value.trim()
    if (v !== (props.customName || props.templateName)) {
        emit('rename', v || props.templateName)
    }
}

const cancelRename = () => {
    renaming.value = false
}

// ─── Date edit logic ───
const editingDate = ref(false)
const dateValue = ref('')
const dateInput = ref(null)

const formattedDate = computed(() => {
    if (!props.anamnesisDate) return null
    const d = new Date(props.anamnesisDate)
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
})

const startEditDate = async () => {
    if (props.anamnesisDate) {
        const d = new Date(props.anamnesisDate)
        const pad = (n) => String(n).padStart(2, '0')
        dateValue.value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
    }
    editingDate.value = true
    await nextTick()
    dateInput.value?.showPicker?.()
}

const commitDate = () => {
    editingDate.value = false
    if (dateValue.value) emit('update-date', dateValue.value)
}

// ─── Save label ───
const saveLabel = computed(() => {
    if (props.saving) return 'Salvando…'
    if (props.savedAt) {
        const m = Math.floor((Date.now() - props.savedAt.getTime()) / 60000)
        return m < 1 ? 'Salvo agora' : `Salvo há ${m} min`
    }
    return null
})

const estimatedMinutes = computed(() => {
    const remaining = Math.max(0, props.totalQuestions - props.answeredCount)
    return remaining === 0 ? 0 : Math.max(1, Math.ceil((remaining * 3) / 60))
})
</script>

<template>
    <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm px-4 py-3">
        <!-- Top row: title + save status -->
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Modelo</p>

                <!-- Rename inline -->
                <div v-if="renaming" class="flex items-center gap-2">
                    <input
                        ref="renameInput"
                        v-model="renameValue"
                        type="text"
                        class="flex-1 text-sm font-semibold text-slate-900 border-b border-teal-400 bg-transparent outline-none py-px pr-2"
                        @keyup.enter="commitRename"
                        @keyup.escape="cancelRename"
                        @blur="commitRename"
                    />
                    <button type="button" @click="commitRename" class="text-[10px] text-teal-600 font-medium hover:text-teal-700 shrink-0">Salvar</button>
                    <button type="button" @click="cancelRename" class="text-[10px] text-slate-400 hover:text-slate-600 shrink-0">Cancelar</button>
                </div>
                <div v-else class="flex items-center gap-1.5 group/rename">
                    <p class="text-sm font-semibold text-slate-900 truncate">{{ displayName || templateName }}</p>
                    <button
                        type="button"
                        class="opacity-0 group-hover/rename:opacity-100 transition-opacity p-0.5 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        title="Renomear esta anamnese"
                        @click="startRename"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>
                        </svg>
                    </button>
                    <span v-if="customName" class="text-[9px] text-teal-600 bg-teal-50 border border-teal-100 px-1.5 py-px rounded font-medium shrink-0">renomeada</span>
                </div>

                <!-- Date row -->
                <div class="flex items-center gap-1 mt-0.5 group/date">
                    <span class="text-[10px] text-slate-400">
                        {{ formattedDate || '—' }}
                    </span>
                    <button
                        v-if="!editingDate"
                        type="button"
                        class="opacity-0 group-hover/date:opacity-100 transition-opacity p-0.5 rounded text-slate-400 hover:text-slate-600"
                        title="Alterar data da anamnese"
                        @click="startEditDate"
                    >
                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/>
                        </svg>
                    </button>
                    <div v-else class="flex items-center gap-1.5">
                        <input
                            ref="dateInput"
                            v-model="dateValue"
                            type="datetime-local"
                            class="text-[11px] border border-teal-300 rounded-md px-1.5 py-0.5 outline-none focus:border-teal-400 bg-white text-slate-700"
                            @change="commitDate"
                            @blur="commitDate"
                            @keyup.escape="editingDate = false"
                        />
                        <button type="button" @click="editingDate = false" class="text-[10px] text-slate-400 hover:text-slate-600">✕</button>
                    </div>
                </div>
            </div>

            <div class="shrink-0 text-right">
                <div v-if="saveLabel" class="flex items-center gap-1 justify-end">
                    <span
                        class="inline-block w-1.5 h-1.5 rounded-full"
                        :class="saving ? 'bg-amber-400 animate-pulse' : 'bg-teal-400'"
                    />
                    <span class="text-[10px] text-slate-400">{{ saveLabel }}</span>
                </div>
            </div>
        </div>

        <!-- Stats row -->
        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1">Progresso</p>
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div
                            class="h-full bg-teal-500 rounded-full transition-all duration-500"
                            :style="{ width: progress + '%' }"
                        />
                    </div>
                    <span class="text-xs font-semibold text-teal-600 tabular-nums">{{ progress }}%</span>
                </div>
            </div>
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Respondidas</p>
                <p class="text-sm font-medium text-slate-800 tabular-nums">{{ answeredCount }} / {{ totalQuestions }}</p>
            </div>
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Alertas</p>
                <p class="text-sm font-medium" :class="alertCount ? 'text-amber-600' : 'text-slate-800'">
                    {{ alertCount || '—' }}
                </p>
            </div>
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Restante</p>
                <p class="text-sm font-medium text-slate-800">
                    {{ estimatedMinutes === 0 ? '—' : `~${estimatedMinutes} min` }}
                </p>
            </div>
        </div>
    </div>
</template>
