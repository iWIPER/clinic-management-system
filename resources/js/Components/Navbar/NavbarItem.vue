<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, inject, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    match: { type: [String, Array, Function], default: null },
    icon: { type: Boolean, default: false },
})

const page = usePage()
const itemRef = ref(null)
const register = inject('navbarRegister', null)
const unregister = inject('navbarUnregister', null)

const id = Symbol('navbar-item')

const isActive = computed(() => {
    const url = page.url.split('?')[0]

    if (typeof props.match === 'function') return props.match(url)
    if (Array.isArray(props.match)) return props.match.some(m => url.startsWith(m))
    if (props.match) return url.startsWith(props.match)

    try {
        const path = new URL(props.href, window.location.origin).pathname
        return url === path || url.startsWith(path + '/')
    } catch {
        return url === props.href || url.startsWith(props.href + '/')
    }
})

const getEl = () => itemRef.value

onMounted(() => {
    register?.(id, getEl, () => isActive.value)
})

onUnmounted(() => {
    unregister?.(id)
})
</script>

<template>
    <div ref="itemRef" class="relative flex items-center">
        <Link
            :href="href"
            class="navbar-link group relative inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-2 text-sm font-medium leading-none tracking-normal text-emerald-700 antialiased transition-all duration-[180ms] ease-out hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.98]"
            :class="isActive ? 'text-emerald-900 font-semibold' : ''"
        >
            <svg
                v-if="icon"
                class="h-3.5 w-3.5 shrink-0 opacity-70 transition-opacity duration-[180ms] ease group-hover:opacity-100"
                :class="isActive ? 'opacity-90' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>{{ label }}</span>
            <span
                class="absolute -bottom-2 left-2 right-2 h-0.5 origin-center rounded-full bg-emerald-600/0 transition-all duration-[180ms] ease group-hover:bg-emerald-600/25"
                :class="isActive ? 'opacity-0' : ''"
            />
        </Link>
    </div>
</template>