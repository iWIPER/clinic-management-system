<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    show:        { type: Boolean, default: false },
    maxWidth:    { type: String,  default: 'max-w-lg' },
    title:       { type: String,  default: '' },
    // Quando true, o conteúdo do modal é montado uma única vez (na primeira
    // abertura) e permanece montado depois — só a visibilidade (v-show)
    // alterna. Usado por modais com widgets caros de inicializar (ex: editor
    // Tiptap) para evitar recriá-los a cada abertura. Default false preserva
    // o comportamento de sempre (v-if puro) para todo o resto do app.
    keepMounted: { type: Boolean, default: false },
})

defineEmits(['close'])

const hasOpenedOnce = ref(false)
watch(() => props.show, (visible) => {
    if (visible) hasOpenedOnce.value = true
}, { immediate: true })
</script>

<template>
<Teleport to="body">
    <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div v-if="(keepMounted && hasOpenedOnce) || show" v-show="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="$emit('close')">
            <Transition
                appear
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100">
                <div class="w-full flex flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                     :class="maxWidth" style="max-height: min(90vh, 720px)">
                    <div v-if="title || $slots.header" class="flex items-center justify-between border-b border-slate-100 px-5 py-4 shrink-0">
                        <slot name="header">
                            <h3 class="font-semibold text-slate-900">{{ title }}</h3>
                        </slot>
                        <button type="button" @click="$emit('close')"
                                class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto min-h-0">
                        <slot />
                    </div>

                    <div v-if="$slots.footer" class="border-t border-slate-100 px-5 py-3 shrink-0">
                        <slot name="footer" />
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</Teleport>
</template>
