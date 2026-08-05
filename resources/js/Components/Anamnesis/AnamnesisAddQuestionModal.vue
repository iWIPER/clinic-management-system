<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    initialCategory: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
    serverError: { type: String, default: '' },
})

const emit = defineEmits(['close', 'save'])

const TYPE_OPTIONS = [
    { value: 'text', label: 'Apenas Texto' },
    { value: 'yes_no', label: 'Sim / Não' },
    { value: 'yes_no_unknown', label: 'Sim / Não / Não sei' },
    { value: 'yes_no_text', label: 'Sim / Não + Informações adicionais' },
    { value: 'yes_no_unknown_text', label: 'Sim / Não / Não sei + Informações adicionais' },
]

const form = ref({
    text: '',
    category: '',
    type: 'yes_no_unknown',
    is_required: false,
    is_active: true,
})

const saving = ref(false)
const error = ref('')

watch(() => props.show, (v) => {
    if (v) {
        form.value = {
            text: '',
            category: props.initialCategory || (props.categories[0] || ''),
            type: 'yes_no_unknown',
            is_required: false,
            is_active: true,
        }
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

const save = async () => {
    if (!form.value.text.trim()) {
        error.value = 'Digite o texto da pergunta.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        await emit('save', { ...form.value })
    } finally {
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
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30 backdrop-blur-[2px]"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    leave-active-class="transition-all duration-150 ease-in"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <div v-if="show" class="w-full max-w-md rounded-xl bg-white shadow-xl border border-[#E8EDF4]">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-[#E8EDF4] px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Adicionar pergunta</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">Exclusiva desta anamnese — não altera o modelo.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                                @click="emit('close')"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="px-4 py-4 space-y-3">
                            <!-- Question text -->
                            <div>
                                <label class="text-[11px] font-medium text-slate-600 mb-1 block">Pergunta <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.text"
                                    type="text"
                                    placeholder="Digite a pergunta..."
                                    class="w-full rounded-lg border border-[#E8EDF4] px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20 transition-all placeholder:text-slate-300"
                                    @keyup.enter="save"
                                />
                                <InputError :message="error" />
                            </div>

                            <!-- Category -->
                            <div>
                                <label class="text-[11px] font-medium text-slate-600 mb-1 block">Categoria</label>
                                <select
                                    v-model="form.category"
                                    class="w-full rounded-lg border border-[#E8EDF4] px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20 transition-all bg-white"
                                >
                                    <option v-for="cat in categories" :key="cat" :value="cat">
                                        {{ cat.toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) }}
                                    </option>
                                </select>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="text-[11px] font-medium text-slate-600 mb-1 block">Tipo de resposta</label>
                                <div class="space-y-1.5">
                                    <label
                                        v-for="opt in TYPE_OPTIONS"
                                        :key="opt.value"
                                        class="flex items-center gap-2.5 cursor-pointer rounded-lg px-3 py-2 border transition-all duration-150"
                                        :class="form.type === opt.value
                                            ? 'border-teal-400 bg-teal-50/60 text-teal-800'
                                            : 'border-[#E8EDF4] text-slate-600 hover:border-slate-300'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.type"
                                            :value="opt.value"
                                            class="text-teal-500 focus:ring-teal-400 w-3.5 h-3.5 shrink-0"
                                        />
                                        <span class="text-[12px] font-medium">{{ opt.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Toggles row -->
                            <div class="flex items-center gap-4 pt-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <button
                                        type="button"
                                        class="relative inline-block w-8 h-4 rounded-full transition-colors duration-200 focus:outline-none"
                                        :class="form.is_required ? 'bg-teal-500' : 'bg-slate-200'"
                                        @click="form.is_required = !form.is_required"
                                    >
                                        <span class="absolute top-0.5 w-3 h-3 rounded-full bg-white shadow transition-all duration-200" :class="form.is_required ? 'left-[17px]' : 'left-0.5'" />
                                    </button>
                                    <span class="text-[12px] text-slate-600">Obrigatória</span>
                                </label>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <button
                                        type="button"
                                        class="relative inline-block w-8 h-4 rounded-full transition-colors duration-200 focus:outline-none"
                                        :class="form.is_active ? 'bg-teal-500' : 'bg-slate-200'"
                                        @click="form.is_active = !form.is_active"
                                    >
                                        <span class="absolute top-0.5 w-3 h-3 rounded-full bg-white shadow transition-all duration-200" :class="form.is_active ? 'left-[17px]' : 'left-0.5'" />
                                    </button>
                                    <span class="text-[12px] text-slate-600">Ativa</span>
                                </label>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-2 border-t border-[#E8EDF4] px-4 py-3">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-[12px] text-slate-600 hover:bg-slate-50 transition-colors"
                                @click="emit('close')"
                            >Cancelar</button>
                            <button
                                type="button"
                                class="rounded-lg bg-teal-600 px-4 py-1.5 text-[12px] font-medium text-white hover:bg-teal-700 transition-colors disabled:opacity-60"
                                :disabled="saving || !form.text.trim()"
                                @click="save"
                            >{{ saving ? 'Salvando…' : 'Salvar pergunta' }}</button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
