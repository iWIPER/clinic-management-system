<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    invite:         { type: Object,  required: true },
    clinic:         { type: Object,  required: true },
    userExists:     { type: Boolean, default: false },
    isLoggedIn:     { type: Boolean, default: false },
    isCorrectUser:  { type: Boolean, default: false },
})

const showPassword = ref(false)

const form = useForm({
    password:              '',
    password_confirmation: '',
})

function submit() {
    form.post(route('invites.accept', props.invite.token), {
        onSuccess: () => form.reset(),
    })
}

const daysLeft = computed(() => {
    const diff = new Date(props.invite.expires_at) - new Date()
    return Math.max(0, Math.ceil(diff / 86400000))
})

// Casos
// A) isLoggedIn && isCorrectUser  → só botão aceitar (sem senha)
// B) isLoggedIn && !isCorrectUser → aviso de conta errada (bloqueado)
// C) !isLoggedIn && userExists    → login inline (senha para verificar identidade)
// D) !isLoggedIn && !userExists   → criar conta (senha + confirmação)

const mode = computed(() => {
    if (props.isLoggedIn && props.isCorrectUser)  return 'accept_logged'
    if (props.isLoggedIn && !props.isCorrectUser) return 'wrong_account'
    if (props.userExists)                          return 'login'
    return 'register'
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center p-4">

        <!-- Decoração de fundo -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute top-20 left-10 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative w-full max-w-md">

            <!-- Branding ClinicFlow -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">Clinic<span class="text-blue-400">Flow</span></span>
                </div>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

                <!-- Header gradient -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-6 text-center">
                    <!-- Logo da clínica -->
                    <div v-if="clinic.logo_url" class="mb-3">
                        <img :src="clinic.logo_url" :alt="clinic.name" class="h-12 max-w-[140px] object-contain rounded mx-auto" />
                    </div>
                    <div class="text-3xl mb-2">🎉</div>
                    <h1 class="text-xl font-bold text-white">Você foi convidado!</h1>
                    <p v-if="invite.type === 'affiliate'" class="text-blue-200 text-sm mt-1">
                        para se tornar um <strong class="text-white">Afiliado CliniFlow</strong>
                    </p>
                    <p v-else class="text-blue-200 text-sm mt-1">para fazer parte de <strong class="text-white">{{ clinic.name }}</strong></p>
                </div>

                <div class="p-6 space-y-5">

                    <!-- Resumo do convite -->
                    <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 space-y-2.5">
                        <div v-if="invite.type !== 'affiliate'" class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                <img v-if="clinic.logo_url" :src="clinic.logo_url" :alt="clinic.name" class="w-8 h-8 object-contain" />
                                <span v-else class="text-lg">🏥</span>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Clínica</p>
                                <p class="font-semibold text-slate-800">{{ clinic.name }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                <p class="text-slate-400">Convidado por</p>
                                <p class="font-medium text-slate-700">{{ invite.invited_by || 'Administrador' }}</p>
                            </div>
                            <div v-if="invite.job_title" class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                <p class="text-slate-400">Cargo</p>
                                <p class="font-medium text-slate-700">{{ invite.job_title }}</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2.5 flex items-center justify-between">
                            <span class="text-xs text-blue-600 font-medium">Código do convite</span>
                            <code class="font-mono font-bold text-blue-800 tracking-widest text-lg">{{ invite.short_token }}</code>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════
                         MODO A: Logado com conta correta — só aceitar
                         ═══════════════════════════════════════════════ -->
                    <template v-if="mode === 'accept_logged'">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3 text-sm text-emerald-700 flex items-start gap-2">
                            <span class="text-base flex-shrink-0">✅</span>
                            <span>Você está autenticado com o e-mail correto (<strong>{{ invite.email }}</strong>). Clique em aceitar para entrar na clínica.</span>
                        </div>

                        <button
                            @click="submit"
                            :disabled="form.processing"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold py-3 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-md hover:shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Processando...' : 'Aceitar convite e entrar' }}
                        </button>
                    </template>

                    <!-- ═══════════════════════════════════════════════
                         MODO B: Logado com conta errada — aviso
                         ═══════════════════════════════════════════════ -->
                    <template v-else-if="mode === 'wrong_account'">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
                            <div class="flex items-start gap-2">
                                <span class="text-lg flex-shrink-0">⚠️</span>
                                <div>
                                    <p class="font-semibold text-amber-800 text-sm">Você está logado com outra conta</p>
                                    <p class="text-sm text-amber-700 mt-1">
                                        Este convite é para o e-mail <strong>{{ invite.email }}</strong>,
                                        mas você está autenticado com uma conta diferente.
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-amber-600 pl-7">Para aceitar este convite, saia da conta atual e acesse com <strong>{{ invite.email }}</strong>.</p>
                        </div>

                        <a
                            href="/logout"
                            class="block w-full text-center bg-amber-500 text-white text-sm font-bold py-3 rounded-xl hover:bg-amber-600 transition-colors shadow-sm"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        >
                            Sair e usar outra conta
                        </a>
                        <form id="logout-form" method="POST" :action="route('logout')" class="hidden">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                        </form>
                    </template>

                    <!-- ═══════════════════════════════════════════════
                         MODO C: Usuário existente — login inline
                         ═══════════════════════════════════════════════ -->
                    <template v-else-if="mode === 'login'">
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 text-sm text-indigo-700">
                            Já existe uma conta com o e-mail <strong>{{ invite.email }}</strong>. Confirme sua senha para aceitar o convite.
                        </div>

                        <!-- Campo de e-mail (desabilitado, informativo) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">E-mail</label>
                            <input
                                type="email"
                                :value="invite.email"
                                disabled
                                class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-lg px-3 py-2.5 text-sm cursor-not-allowed"
                            />
                        </div>

                        <!-- Campo de senha -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Sua senha do ClinicFlow</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Digite sua senha"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                                    :class="form.errors.password ? 'border-red-400' : 'border-slate-300'"
                                    autocomplete="current-password"
                                    @keyup.enter="submit"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    tabindex="-1"
                                >
                                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <button
                            @click="submit"
                            :disabled="form.processing || !form.password"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold py-3 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-md hover:shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Verificando...' : 'Confirmar e aceitar convite' }}
                        </button>
                    </template>

                    <!-- ═══════════════════════════════════════════════
                         MODO D: Novo usuário — criar conta
                         ═══════════════════════════════════════════════ -->
                    <template v-else>
                        <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm text-slate-600">
                            Você está sendo convidado como <strong class="text-slate-800">{{ invite.email }}</strong>. Defina uma senha para criar sua conta no ClinicFlow.
                        </div>

                        <!-- Senha -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Definir senha</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Mínimo 8 caracteres"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                                    :class="form.errors.password ? 'border-red-400' : 'border-slate-300'"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    tabindex="-1"
                                >
                                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Confirmar senha -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmar senha</label>
                            <input
                                v-model="form.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Repita a senha"
                                class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                                :class="form.errors.password_confirmation ? 'border-red-400' : 'border-slate-300'"
                                autocomplete="new-password"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <button
                            @click="submit"
                            :disabled="form.processing || !form.password"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold py-3 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-md hover:shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Criando conta...' : 'Criar conta e aceitar convite' }}
                        </button>
                    </template>

                    <!-- Expiração -->
                    <p class="text-center text-xs text-slate-400">
                        Este convite expira em <strong class="text-slate-600">{{ daysLeft }} dia{{ daysLeft !== 1 ? 's' : '' }}</strong>
                    </p>

                </div>
            </div>

            <p class="text-center text-xs text-slate-500 mt-6">
                &copy; {{ new Date().getFullYear() }} ClinicFlow &middot; Sua privacidade é nossa prioridade
            </p>
        </div>
    </div>
</template>
