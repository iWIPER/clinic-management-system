<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">Recuperar Senha</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Digite seu email e enviaremos um link para redefinir sua senha.
                </p>
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

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium"
                >
                    Enviar link de recuperação
                </button>

                <p class="text-center">
                    <a :href="route('login')" class="text-sm text-blue-600 hover:underline">Voltar para o login</a>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

defineProps({ status: String })

const form = useForm({ email: '' })

function submit() {
    form.post(route('password.email'))
}
</script>
