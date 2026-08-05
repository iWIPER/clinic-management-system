<script setup>
import { STATUS_VISUAL, LEGEND_STATUSES } from './permanentTeethPaths.js'
</script>

<template>
<div class="flex flex-wrap gap-1.5">
    <span v-for="key in LEGEND_STATUSES" :key="key"
          class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border"
          :style="`background:${STATUS_VISUAL[key].fill};border-color:${STATUS_VISUAL[key].stroke};color:${STATUS_VISUAL[key].stroke}`">
        {{ STATUS_VISUAL[key].label }}
    </span>
</div>
</template>
