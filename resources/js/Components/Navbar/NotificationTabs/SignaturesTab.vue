<script setup>
defineProps({
    items: { type: Array, default: () => [] },
    close: { type: Function, default: () => {} },
})
</script>

<template>
    <div v-if="items.length === 0" class="px-4 py-8 text-center">
        <div class="text-2xl mb-2">✅</div>
        <p class="text-xs font-medium text-slate-600">Nenhuma assinatura pendente.</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Tudo assinado por aqui.</p>
    </div>

    <div v-else class="max-h-80 overflow-y-auto divide-y divide-slate-50">
        <div
            v-for="item in items"
            :key="item.id"
            class="flex items-center justify-between gap-3 px-4 py-2.5"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <p class="text-[12px] font-semibold text-slate-800 truncate">{{ item.patient_name }}</p>
                    <span class="shrink-0 inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-1.5 py-0.5 text-[9px] font-medium text-amber-700">
                        {{ item.badge }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 truncate">{{ item.label }}</p>
                <p class="text-[10px] text-slate-400">{{ item.occurred_label }}</p>
            </div>
            <a
                :href="item.show_url"
                class="shrink-0 inline-flex items-center rounded-lg border px-2.5 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                @click="close"
            >
                Abrir
            </a>
        </div>
    </div>

    <div v-if="items.length > 0" class="border-t px-4 py-2 text-[11px] text-slate-400 text-center">
        Aguardando assinatura
    </div>
</template>
