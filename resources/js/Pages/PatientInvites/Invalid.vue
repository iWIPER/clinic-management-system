<script setup>
defineProps({
    reason: { type: String, default: 'expired' }, // expired | cancelled
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-emerald-950 via-slate-900 to-teal-950 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center">
            <div class="text-5xl mb-4">{{ reason === 'cancelled' ? '🚫' : '⏰' }}</div>
            <h1 class="text-xl font-bold text-slate-900 mb-2">
                {{ reason === 'cancelled' ? 'Convite cancelado' : 'Convite expirado' }}
            </h1>
            <p class="text-sm text-slate-500 mb-6">
                {{ reason === 'cancelled'
                    ? 'Este convite foi cancelado pela clínica. Caso ainda deseje concluir seu cadastro, entre em contato com a clínica para solicitar um novo convite.'
                    : 'Seu convite expirou. Entre em contato com a clínica para solicitar um novo link.' }}
            </p>
        </div>
    </div>
</template>
