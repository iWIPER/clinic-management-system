<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full">
            <div class="bg-white shadow rounded-lg p-8 space-y-6">
                <h2 class="text-2xl font-bold text-gray-900">Confirmar Senha</h2>
                <p class="text-sm text-gray-600">
                    Por segurança, confirme sua senha antes de continuar.
                </p>

                <form @submit.prevent="submit" class="space-y-4">
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

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium"
                    >
                        Confirmar
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({ password: '' })

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset('password'),
    })
}
</script>
