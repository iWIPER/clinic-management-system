<script setup>
import { computed } from 'vue'

const props = defineProps({
    question: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    isDisabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const baseInput = [
    'w-full rounded-md border border-[#E8EDF4] bg-white',
    'px-2.5 py-1 text-[12px] text-slate-800 placeholder:text-slate-400',
    'outline-none transition-all duration-[150ms]',
    'focus:border-teal-400 focus:ring-1 focus:ring-teal-400/20',
].join(' ')

const value = computed({
    get: () => props.modelValue,
    set: (v) => {
        emit('update:modelValue', v)
        emit('change')
    },
})

const normalizedType = computed(() => {
    const t = (props.question.type || '').toLowerCase()
    return ['text','yes_no','yes_no_text','yes_no_unknown','yes_no_unknown_text'].includes(t) ? t : 'text'
})

const hasSupplementary = computed(() =>
    normalizedType.value === 'yes_no_text' || normalizedType.value === 'yes_no_unknown_text'
)

const showSupplementary = computed(() => {
    if (!hasSupplementary.value) return false
    const v = value.value?.value
    if (normalizedType.value === 'yes_no_text') return v === 'sim'
    if (normalizedType.value === 'yes_no_unknown_text') return v === 'sim' || v === 'nao_sei'
    return false
})

const setChoice = (opt) => {
    const next = { ...value.value, value: opt }
    // Clear supplementary_text only when transitioning away from an option that shows the card
    const currentShows = value.value?.value === 'sim' || (normalizedType.value === 'yes_no_unknown_text' && value.value?.value === 'nao_sei')
    const nextShows = opt === 'sim' || (normalizedType.value === 'yes_no_unknown_text' && opt === 'nao_sei')
    if (currentShows && !nextShows) next.supplementary_text = ''
    value.value = next
}

const RADIO_OPTS = {
    yes_no:              [{ v: 'sim', l: 'Sim' }, { v: 'nao', l: 'Não' }],
    yes_no_text:         [{ v: 'sim', l: 'Sim' }, { v: 'nao', l: 'Não' }],
    yes_no_unknown:      [{ v: 'sim', l: 'Sim' }, { v: 'nao', l: 'Não' }, { v: 'nao_sei', l: 'Não sei' }],
    yes_no_unknown_text: [{ v: 'sim', l: 'Sim' }, { v: 'nao', l: 'Não' }, { v: 'nao_sei', l: 'Não sei' }],
}

const supplementaryTitle = computed(() =>
    props.question.supplementary_placeholder
        ? props.question.supplementary_placeholder.split('\n')[0]
        : 'Informações adicionais'
)

const supplementaryPlaceholder = computed(() =>
    props.question.supplementary_placeholder || 'Descreva com detalhes…'
)
</script>

<template>
    <div
        class="rounded-lg border bg-white px-3 py-2.5 transition-all duration-[150ms]"
        :class="[
            isDisabled
                ? 'border-[#E8EDF4] cursor-not-allowed select-none'
                : question.is_instance_question
                    ? 'border-teal-100 focus-within:border-teal-300 focus-within:ring-1 focus-within:ring-teal-300/20'
                    : 'border-[#E8EDF4] hover:border-slate-200 focus-within:border-teal-400 focus-within:ring-1 focus-within:ring-teal-400/15',
        ]"
    >
        <!-- Question text -->
        <div class="flex items-start gap-2 pr-8">
            <p class="flex-1 text-[12px] font-medium text-slate-800 leading-snug">
                {{ question.text }}
                <span v-if="question.is_required" class="ml-0.5 text-red-400 text-[10px]">*</span>
                <span
                    v-if="question.is_instance_question"
                    class="ml-1.5 text-[9px] font-semibold text-teal-600 bg-teal-50 border border-teal-100 px-1 py-px rounded"
                >esta anamnese</span>
            </p>
        </div>

        <!-- Answer area (hidden when disabled) -->
        <div v-if="!isDisabled" class="mt-2">

            <!-- TEXT -->
            <template v-if="normalizedType === 'text'">
                <input
                    v-model="value.value"
                    @input="emit('change')"
                    type="text"
                    :class="baseInput"
                    :placeholder="question.description || ''"
                />
            </template>

            <!-- YES/NO VARIANTS -->
            <template v-else>
                <div class="flex flex-wrap items-center gap-4">
                    <label
                        v-for="opt in RADIO_OPTS[normalizedType]"
                        :key="opt.v"
                        class="inline-flex items-center gap-1.5 text-[12px] text-slate-700 cursor-pointer select-none"
                    >
                        <input
                            type="radio"
                            :name="'q-' + question.id"
                            :checked="value.value === opt.v"
                            @change="setChoice(opt.v)"
                            class="text-teal-500 focus:ring-teal-400 w-3 h-3 shrink-0"
                        />
                        {{ opt.l }}
                    </label>
                </div>

                <!-- Supplementary card — fade + slide down -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out origin-top"
                    leave-active-class="transition-all duration-150 ease-in origin-top"
                    enter-from-class="opacity-0 -translate-y-1 scale-y-95"
                    leave-to-class="opacity-0 -translate-y-1 scale-y-95"
                >
                    <div
                        v-if="showSupplementary"
                        class="mt-2 rounded-lg border border-teal-100 bg-teal-50/40 overflow-hidden"
                    >
                        <!-- Card header -->
                        <div class="flex items-center gap-1.5 px-3 pt-2 pb-1 border-b border-teal-100/70">
                            <svg class="w-3 h-3 text-teal-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                            </svg>
                            <span class="text-[10px] font-semibold text-teal-700 tracking-wide uppercase">{{ supplementaryTitle }}</span>
                        </div>
                        <!-- Textarea -->
                        <textarea
                            v-model="value.supplementary_text"
                            @input="emit('change')"
                            rows="3"
                            class="w-full bg-transparent px-3 py-2 text-[12px] text-slate-700 placeholder:text-teal-400/70 outline-none resize-none leading-relaxed"
                            :placeholder="supplementaryPlaceholder"
                        />
                    </div>
                </Transition>
            </template>

        </div>
    </div>
</template>
