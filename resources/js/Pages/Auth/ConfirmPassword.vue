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
                        <PasswordInput v-model="form.password" autocomplete="current-password" required />
                        <InputError :message="form.errors.password" />
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
import InputError from '@/Components/InputError.vue'
import PasswordInput from '@/Components/PasswordInput.vue'

const form = useForm({ password: '' })

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset('password'),
    })
}
</script>
