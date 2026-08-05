<script setup>
defineProps({
    name: { type: String, required: true },
})
</script>

<template>
    <div class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium tracking-wide text-emerald-700 shadow-sm ring-1 ring-emerald-100/80 antialiased">
        {{ name }}
    </div>
</template>