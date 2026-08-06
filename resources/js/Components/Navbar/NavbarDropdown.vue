<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

defineProps({
    align: { type: String, default: 'right' },
    width: { type: String, default: 'w-52' },
    // 'down' (padrão, comportamento inalterado) ou 'up' — usado por
    // controles próximos ao rodapé da página, onde um menu que abre pra
    // baixo ficaria cortado pela borda inferior da janela.
    direction: { type: String, default: 'down' },
})

const open = ref(false)
const containerRef = ref(null)

const toggle = () => { open.value = !open.value }
const close = () => { open.value = false }

const onOutside = (e) => {
    if (containerRef.value && !containerRef.value.contains(e.target)) close()
}

const onKeydown = (e) => {
    if (e.key === 'Escape') close()
}

onMounted(() => {
    document.addEventListener('mousedown', onOutside)
    document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
    document.removeEventListener('mousedown', onOutside)
    document.removeEventListener('keydown', onKeydown)
})

defineExpose({ close, open })
</script>

<template>
    <div ref="containerRef" class="relative">
        <div role="presentation" @click="toggle">
            <slot name="trigger" :open="open" :toggle="toggle" />
        </div>

        <Transition
            enter-active-class="transition duration-[180ms] ease-out"
            :enter-from-class="direction === 'up' ? 'opacity-0 scale-[0.98] translate-y-0.5' : 'opacity-0 scale-[0.98] -translate-y-0.5'"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-[150ms] ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            :leave-to-class="direction === 'up' ? 'opacity-0 scale-[0.98] translate-y-0.5' : 'opacity-0 scale-[0.98] -translate-y-0.5'"
        >
            <div
                v-if="open"
                class="absolute z-50 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                :class="[
                    width,
                    align === 'right' ? 'right-0' : 'left-0',
                    direction === 'up' ? 'bottom-full mb-2' : 'top-full mt-2',
                ]"
            >
                <slot :close="close" />
            </div>
        </Transition>
    </div>
</template>