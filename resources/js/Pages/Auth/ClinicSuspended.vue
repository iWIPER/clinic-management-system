<script setup>
import { Head } from '@inertiajs/vue3'
import ClinicSuspendedModal from '@/Components/Auth/ClinicSuspendedModal.vue'
</script>

<template>
    <Head title="Clínica suspensa" />
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <ClinicSuspendedModal />
    </div>
</template>
