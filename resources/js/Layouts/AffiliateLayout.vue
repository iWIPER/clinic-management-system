<script setup>
import { Link } from '@inertiajs/vue3'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
</script>

<template>
<div class="min-h-screen bg-slate-50">
    <nav class="border-b bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-[var(--app-navbar-h)] items-center">
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

    <ToastContainer />
</div>
</template>
