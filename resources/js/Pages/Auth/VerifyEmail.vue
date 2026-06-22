<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full">
            <div class="bg-white shadow rounded-lg p-8 space-y-6 text-center">
                <h2 class="text-2xl font-bold text-gray-900">Verifique seu email</h2>
                <p class="text-gray-600 text-sm">
                    Enviamos um link de verificação para o seu email. Clique no link para ativar sua conta.
                </p>

                <div v-if="status === 'verification-link-sent'" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
                    Um novo link foi enviado para o seu email.
                </div>

                <form @submit.prevent="resend">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        Reenviar email de verificação
                    </button>
                </form>

                <form @submit.prevent="logout" class="mt-2">
                    <button type="submit" class="text-sm text-gray-500 hover:underline">Sair</button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

defineProps({ status: String })

const form = useForm({})
const logoutForm = useForm({})

function resend() {
    form.post(route('verification.send'))
}

function logout() {
    logoutForm.post(route('logout'))
}
</script>
