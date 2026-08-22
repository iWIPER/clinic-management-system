<script setup>
import { Head } from '@inertiajs/vue3'
import AccountBlockedModal from '@/Components/Auth/AccountBlockedModal.vue'
</script>

<template>
    <Head title="Acesso bloqueado" />
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <AccountBlockedModal />
    </div>
</template>
