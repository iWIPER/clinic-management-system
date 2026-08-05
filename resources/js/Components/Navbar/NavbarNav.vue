<script setup>
import { usePage } from '@inertiajs/vue3'
import { ref, provide, watch, onMounted, onUnmounted, nextTick } from 'vue'

const page = usePage()
const navRef = ref(null)
const items = ref(new Map())

const indicator = ref({ left: 0, width: 0, visible: false })

const registerItem = (id, getEl, isActiveFn) => {
    items.value.set(id, { getEl, isActiveFn })
    items.value = new Map(items.value)
    scheduleUpdate()
}

const unregisterItem = (id) => {
    items.value.delete(id)
    items.value = new Map(items.value)
    scheduleUpdate()
}

provide('navbarRegister', registerItem)
provide('navbarUnregister', unregisterItem)

let rafId = null

const scheduleUpdate = () => {
    if (rafId) cancelAnimationFrame(rafId)
    rafId = requestAnimationFrame(() => {
        rafId = null
        updateIndicator()
    })
}

const updateIndicator = async () => {
    await nextTick()

    const nav = navRef.value
    if (!nav) return

    let active = null
    for (const item of items.value.values()) {
        if (item.isActiveFn()) {
            active = item
            break
        }
    }

    if (!active) {
        indicator.value.visible = false
        return
    }

    const el = active.getEl()
    if (!el) return

    const navRect = nav.getBoundingClientRect()
    const rect = el.getBoundingClientRect()

    indicator.value = {
        left: rect.left - navRect.left,
        width: rect.width,
        visible: true,
    }
}

watch(() => page.url, scheduleUpdate)

onMounted(() => {
    scheduleUpdate()
    window.addEventListener('resize', scheduleUpdate)
})

onUnmounted(() => {
    if (rafId) cancelAnimationFrame(rafId)
    window.removeEventListener('resize', scheduleUpdate)
})
</script>

<template>
    <div ref="navRef" class="relative flex items-center gap-2.5 self-stretch text-sm">
        <slot />

        <div
            v-show="indicator.visible"
            class="pointer-events-none absolute bottom-0 h-[2.5px] rounded-full bg-emerald-600 transition-all duration-200 ease-out"
            :style="{
                left: `${indicator.left}px`,
                width: `${indicator.width}px`,
            }"
        />
    </div>
</template>