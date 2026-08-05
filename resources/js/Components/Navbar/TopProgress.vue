<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const visible = ref(false)
const width = ref(0)

let removeStart, removeProgress, removeFinish
let creepTimer = null

const clearCreep = () => {
    if (creepTimer) {
        clearInterval(creepTimer)
        creepTimer = null
    }
}

onMounted(() => {
    removeStart = router.on('start', () => {
        visible.value = true
        width.value = 12
        clearCreep()
        creepTimer = setInterval(() => {
            if (width.value < 88) width.value += Math.random() * 6 + 2
        }, 180)
    })

    removeProgress = router.on('progress', (event) => {
        const pct = event.detail.progress?.percentage
        if (pct != null) {
            clearCreep()
            width.value = pct
        }
    })

    removeFinish = router.on('finish', () => {
        clearCreep()
        width.value = 100
        setTimeout(() => {
            visible.value = false
            width.value = 0
        }, 200)
    })
})

onUnmounted(() => {
    clearCreep()
    removeStart?.()
    removeProgress?.()
    removeFinish?.()
})
</script>

<template>
    <div
        class="absolute top-0 left-0 right-0 h-0.5 overflow-hidden pointer-events-none z-50"
        :class="visible ? 'opacity-100' : 'opacity-0'"
        style="transition: opacity 200ms ease"
    >
        <div
            class="h-full bg-emerald-600 rounded-r-full"
            :style="{
                width: `${width}%`,
                transition: width >= 100 ? 'width 200ms ease' : 'width 180ms ease',
            }"
        />
    </div>
</template>