<script setup>
defineProps({
    reason: { type: String, default: 'expired' },
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center">
            <div class="text-5xl mb-4">{{ reason === 'expired' ? '⏰' : '✅' }}</div>
            <h1 class="text-xl font-bold text-slate-900 mb-2">
                {{ reason === 'expired' ? 'Convite expirado' : 'Convite já utilizado' }}
            </h1>
            <p class="text-sm text-slate-500 mb-6">
                {{ reason === 'expired'
                    ? 'Este convite não é mais válido. Solicite um novo convite ao administrador da clínica.'
                    : 'Este convite já foi aceito. Faça login para acessar sua conta.' }}
            </p>
            <a
                href="/"
                class="inline-block bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-blue-700 transition-colors"
            >
                Ir para o Wildental
            </a>
        </div>
    </div>
</template>
