<script setup>
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { useToast } from '@/composables/useToast'

const page = usePage()
const { toasts, show: showToast, dismiss } = useToast()

watch(() => page.props.flash?.success, (val) => { if (val) showToast(val, 'success') }, { immediate: true })
watch(() => page.props.flash?.error,   (val) => { if (val) showToast(val, 'error') },   { immediate: true })

const toastIcon = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error:   'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const toastColors = {
    success: { bg: 'bg-emerald-50 border-emerald-200', icon: 'text-emerald-500', text: 'text-emerald-800' },
    error:   { bg: 'bg-red-50 border-red-200',         icon: 'text-red-500',     text: 'text-red-800' },
    warning: { bg: 'bg-amber-50 border-amber-200',     icon: 'text-amber-500',   text: 'text-amber-800' },
    info:    { bg: 'bg-blue-50 border-blue-200',        icon: 'text-blue-500',    text: 'text-blue-800' },
}
</script>

<template>
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none" style="min-width: 320px; max-width: 400px">
        <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
            <div v-for="toast in toasts" :key="toast.id"
                 class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm"
                 :class="toastColors[toast.type]?.bg">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="toastColors[toast.type]?.icon"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="toastIcon[toast.type]" />
                </svg>
                <span class="flex-1 leading-snug" :class="toastColors[toast.type]?.text">{{ toast.message }}</span>
                <button @click="dismiss(toast.id)"
                        class="flex-shrink-0 opacity-50 hover:opacity-100 transition-opacity"
                        :class="toastColors[toast.type]?.text">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(40px); }
.toast-leave-to     { opacity: 0; transform: translateX(40px); }
.toast-move         { transition: transform 0.3s ease; }
</style>
