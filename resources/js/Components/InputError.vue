<script setup>
defineProps({
    message: { type: String, default: null },
})
</script>

<template>
    <p v-if="message" class="mt-1 text-xs text-red-600">{{ message }}</p>
</template>
