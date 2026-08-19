<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

// Wrapper de scroll horizontal com fade nas bordas quando há mais conteúdo
// fora da tela — mesmo padrão do SettingsTabs.vue (R1), extraído aqui porque
// Procedimentos precisa de várias instâncias independentes na mesma página
// (uma por categoria do catálogo + uma para desativados).
const props = defineProps({
    // Cor de fundo por trás da tabela, pro fade combinar (branco dentro de
    // um card bg-white; slate-100 no bloco de desativados).
    fadeFrom: { type: String, default: 'from-white' },
})

const scrollRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

function updateScrollState() {
    const el = scrollRef.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 1
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1
}

onMounted(() => {
    updateScrollState()
    scrollRef.value?.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', updateScrollState)
})
onUnmounted(() => {
    scrollRef.value?.removeEventListener('scroll', updateScrollState)
    window.removeEventListener('resize', updateScrollState)
})
</script>

<template>
<div class="relative">
    <div ref="scrollRef" class="overflow-x-auto">
        <slot />
    </div>
    <div v-show="canScrollLeft" class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r to-transparent transition-opacity duration-150" :class="fadeFrom" />
    <div v-show="canScrollRight" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l to-transparent transition-opacity duration-150" :class="fadeFrom" />
</div>
</template>
