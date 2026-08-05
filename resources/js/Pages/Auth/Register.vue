<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">Gestão Clínicas</h2>
                <p class="mt-2 text-sm text-gray-600">Crie sua conta</p>
            </div>

            <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-8 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                    <input
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <InputError :message="form.errors.name" />
                </div>

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
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar senha</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium"
                >
                    Criar conta
                </button>

                <p class="text-center text-sm text-gray-600">
                    Já tem conta?
                    <a :href="route('login')" class="text-blue-600 hover:underline">Entrar</a>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trackEvent } from '@/lib/analytics'
import InputError from '@/Components/InputError.vue'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

onMounted(() => trackEvent('cadastro_iniciado'))

function submit() {
    form.post(route('register'), {
        onSuccess: () => trackEvent('cadastro_concluido'),
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>
