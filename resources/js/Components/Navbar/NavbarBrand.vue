<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import ClinicLogo from '@/Components/ClinicLogo.vue'

const props = defineProps({
    clinic: { type: Object, default: null },
})

const displayName = computed(() => props.clinic?.name || '')
</script>

<template>
    <Link
        :href="route('dashboard')"
        class="group flex min-w-0 cursor-pointer items-center gap-2.5 rounded-md antialiased transition-opacity duration-[180ms] ease hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-2"
    >
        <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-100">
            <ClinicLogo
                :clinic="clinic"
                img-class="h-full w-full object-contain p-0.5"
            />
        </div>

        <div class="flex min-w-0 flex-col leading-none">
            <span class="text-[15px] font-semibold tracking-tight text-slate-800">Wildental</span>
            <span
                v-if="displayName"
                class="mt-0.5 truncate text-[10px] font-medium tracking-wide text-slate-400"
            >
                by <span class="text-slate-500">{{ displayName }}</span>
            </span>
        </div>
    </Link>
</template>
