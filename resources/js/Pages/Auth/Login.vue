<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">Gestão Clínicas</h2>
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
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
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

defineProps({
    canResetPassword: Boolean,
    status: String,
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
