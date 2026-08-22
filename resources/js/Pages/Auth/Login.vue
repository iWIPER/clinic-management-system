<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">Wildental</h2>
                <p class="mt-2 text-sm text-gray-600">Entre na sua conta</p>
            </div>

            <div v-if="status" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-8 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <PasswordInput v-model="form.password" autocomplete="current-password" required />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
                        Lembrar de mim
                    </label>
                    <a v-if="canResetPassword" :href="route('password.request')" class="text-sm text-blue-600 hover:underline">
                        Esqueceu a senha?
                    </a>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium"
                >
                    Entrar
                </button>

                <template v-if="canUseGoogle || canUseApple">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200" />
                        </div>
                        <div class="relative flex justify-center text-xs">
                            <span class="bg-white px-2 text-gray-400">ou</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a
                            v-if="canUseGoogle"
                            :href="route('oauth.google.redirect')"
                            class="flex items-center justify-center gap-2 w-full border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.47a5.54 5.54 0 0 1-2.4 3.63v3h3.87c2.27-2.09 3.55-5.17 3.55-8.66Z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.94-2.92l-3.87-3c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.27v3.11A12 12 0 0 0 12 24Z"/>
                                <path fill="#FBBC05" d="M5.27 14.27a7.2 7.2 0 0 1 0-4.54v-3.1H1.27a12 12 0 0 0 0 10.75l4-3.11Z"/>
                                <path fill="#EA4335" d="M12 4.75c1.76 0 3.34.6 4.58 1.79l3.43-3.43C17.94 1.19 15.24 0 12 0A12 12 0 0 0 1.27 6.63l4 3.1C6.22 6.86 8.87 4.75 12 4.75Z"/>
                            </svg>
                            Continuar com Google
                        </a>

                        <a
                            v-if="canUseApple"
                            :href="route('oauth.apple.redirect')"
                            class="flex items-center justify-center gap-2 w-full border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M16.365 1.43c0 1.14-.475 2.14-1.246 2.905-.845.83-2.174 1.475-3.3 1.386-.148-1.086.472-2.24 1.226-2.983.83-.83 2.24-1.44 3.32-1.308Zm3.914 16.42c-.516 1.187-.762 1.717-1.424 2.766-.925 1.464-2.23 3.29-3.848 3.304-1.44.014-1.812-.94-3.767-.93-1.955.01-2.363.945-3.803.93-1.618-.014-2.853-1.66-3.778-3.124-2.59-4.087-2.862-8.885-1.264-11.436 1.135-1.813 2.93-2.873 4.62-2.873 1.72 0 2.802 1.014 4.226 1.014 1.383 0 2.222-1.017 4.213-1.017 1.508 0 3.104.822 4.24 2.242-3.727 2.043-3.123 7.36.585 9.124Z"/>
                            </svg>
                            Continuar com Apple
                        </a>
                    </div>
                </template>

                <p class="text-center text-sm text-gray-600">
                    Não tem conta?
                    <a :href="route('register')" class="text-blue-600 hover:underline">Cadastre-se</a>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import PasswordInput from '@/Components/PasswordInput.vue'

defineProps({
    canResetPassword: Boolean,
    status: String,
    canUseGoogle: Boolean,
    canUseApple: Boolean,
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>
