<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    href: { type: String, default: null },
    method: { type: String, default: 'get' },
    danger: { type: Boolean, default: false },
    muted: { type: Boolean, default: false },
    as: { type: String, default: 'link' },
})

const emit = defineEmits(['click'])

const baseClass = 'flex w-full items-center px-3.5 py-2 text-sm font-medium leading-none tracking-normal antialiased transition-colors duration-[180ms] ease focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500/35'
const normalClass = 'cursor-pointer text-slate-700 hover:bg-slate-50 hover:text-slate-900'
const dangerClass = 'cursor-pointer text-red-600 hover:bg-red-50 hover:text-red-700'
const mutedClass = 'cursor-default text-slate-400 hover:bg-transparent'

const stateClass = () => {
    if (props.danger) return dangerClass
    if (props.muted) return mutedClass
    return normalClass
}
</script>

<template>
    <button
        v-if="as === 'button'"
        type="button"
        :class="[baseClass, stateClass()]"
        @click="emit('click')"
    >
        <slot />
    </button>

    <Link
        v-else-if="href"
        :href="href"
        :method="method"
        :class="[baseClass, stateClass()]"
        @click="emit('click')"
    >
        <slot />
    </Link>
</template>