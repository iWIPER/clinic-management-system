<template>
    <div class="max-w-2xl mx-auto py-10 px-4">
        <h1 class="text-2xl font-bold text-gray-900 mb-8">Meu Perfil</h1>

        <!-- Dados pessoais -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Dados Pessoais</h2>

            <form @submit.prevent="submitProfile" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <input
                        v-model="profileForm.name"
                        type="text"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                </div>

                <div v-if="mustVerifyEmail && !$page.props.auth.user.email_verified_at" class="rounded-md bg-yellow-50 p-3">
                    <p class="text-sm text-yellow-800">
                        Seu email não foi verificado.
                        <a href="/email/verification-notification" class="underline">Reenviar verificação</a>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        Salvar Alterações
                    </button>
                    <span v-if="status === 'Perfil atualizado com sucesso.'" class="text-sm text-green-600">
                        Salvo!
                    </span>
                </div>
            </form>
        </div>

        <!-- Alterar senha -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Alterar Senha</h2>

            <form @submit.prevent="submitPassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Senha Atual</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nova Senha</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Nova Senha</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
                >
                    Alterar Senha
                </button>
            </form>
        </div>

        <!-- Excluir conta -->
        <div class="bg-white shadow rounded-lg p-6 border border-red-200">
            <h2 class="text-lg font-semibold text-red-700 mb-2">Excluir Conta</h2>
            <p class="text-sm text-gray-600 mb-4">
                Ao excluir sua conta, todos os dados serão permanentemente removidos. Esta ação não pode ser desfeita.
            </p>

            <button
                v-if="!confirmDelete"
                @click="confirmDelete = true"
                class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700"
            >
                Excluir Minha Conta
            </button>

            <form v-else @submit.prevent="submitDelete" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Digite sua senha para confirmar</label>
                    <input
                        v-model="deleteForm.password"
                        type="password"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
                    />
                    <p v-if="deleteForm.errors.password" class="mt-1 text-sm text-red-600">{{ deleteForm.errors.password }}</p>
                </div>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="deleteForm.processing"
                        class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 disabled:opacity-50"
                    >
                        Confirmar Exclusão
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete = false"
                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200"
                    >
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
})

const page = usePage()
const confirmDelete = ref(false)

const profileForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const deleteForm = useForm({
    password: '',
})

function submitProfile() {
    profileForm.patch(route('profile.update'))
}

function submitPassword() {
    passwordForm.patch(route('profile.update'), {
        onSuccess: () => passwordForm.reset(),
    })
}

function submitDelete() {
    deleteForm.delete(route('profile.destroy'))
}
</script>
