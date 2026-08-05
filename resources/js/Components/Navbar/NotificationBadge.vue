<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    count: { type: Number, default: 0 },
})

const pulsing = ref(false)
const prevCount = ref(props.count)

watch(() => props.count, (next, prev) => {
    if (next > prev && next > 0) {
        pulsing.value = true
        setTimeout(() => { pulsing.value = false }, 600)
    }
    prevCount.value = next
})
</script>

<template>
    <span
        v-if="count > 0"
        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[9px] font-bold leading-none text-white shadow-sm ring-1 ring-white"
        :class="{ 'navbar-badge-pulse': pulsing }"
    >
        {{ count > 9 ? '9+' : count }}
    </span>
</template>

<style scoped>
@keyframes navbar-badge-pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.12); }
}

.navbar-badge-pulse {
    animation: navbar-badge-pulse 0.5s ease;
}
</style>