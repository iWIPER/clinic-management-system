<script setup>
defineProps({
    columns: { type: Number, default: 6 },
})
</script>

<template>
<tr class="animate-pulse">
    <td v-for="i in columns" :key="i" class="px-4 py-3.5">
        <div class="h-3.5 rounded bg-slate-100" :style="{ width: (55 + (i * 7) % 35) + '%' }"></div>
    </td>
</tr>
</template>
