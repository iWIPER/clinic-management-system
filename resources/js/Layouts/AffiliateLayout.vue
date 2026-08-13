<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { useToast } from '@/composables/useToast'

const page = usePage()
const { toasts, show: showToast, dismiss } = useToast()

watch(() => page.props.flash?.success, (val) => { if (val) showToast(val, 'success') }, { immediate: true })
watch(() => page.props.flash?.error,   (val) => { if (val) showToast(val, 'error') },   { immediate: true })

const toastColors = {
    success: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-800' },
    error:   { bg: 'bg-red-50 border-red-200',         text: 'text-red-800' },
}
</script>

<template>
<div class="min-h-screen bg-slate-50">
    <nav class="border-b bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <span class="text-lg font-semibold text-slate-900">Wildental <span class="text-emerald-600 font-medium">Afiliados</span></span>

                <div class="flex items-center gap-4 text-sm">
                    <span class="text-slate-600">{{ $page.props.auth.user.name }}</span>
                    <Link :href="route('logout')" method="post" as="button"
                          class="text-slate-500 hover:text-red-600 font-medium">
                        Sair
                    </Link>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <slot />
    </main>

    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none" style="min-width: 320px; max-width: 400px">
        <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
            <div v-for="toast in toasts" :key="toast.id"
                 class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm"
                 :class="toastColors[toast.type]?.bg">
                <span class="flex-1 leading-snug" :class="toastColors[toast.type]?.text">{{ toast.message }}</span>
                <button @click="dismiss(toast.id)" class="flex-shrink-0 opacity-50 hover:opacity-100">✕</button>
            </div>
        </TransitionGroup>
    </div>
</div>
</template>

<style scoped>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(40px); }
.toast-leave-to     { opacity: 0; transform: translateX(40px); }
.toast-move         { transition: transform 0.3s ease; }
</style>
