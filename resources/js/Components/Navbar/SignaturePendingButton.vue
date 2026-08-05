<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import NotificationBadge from './NotificationBadge.vue'
import NavbarDropdown from './NavbarDropdown.vue'

const count = ref(0)
const items = ref([])
let timer = null

const fetch_ = async () => {
    try {
        const res = await fetch(route('anamneses.pending-signatures'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
        if (res.ok) {
            const data = await res.json()
            count.value = data.count ?? 0
            items.value = data.items ?? []
        }
    } catch {}
}

onMounted(() => {
    fetch_()
    timer = setInterval(fetch_, 60000)
})

onUnmounted(() => clearInterval(timer))
</script>

<template>
    <NavbarDropdown width="w-96">
        <template #trigger="{ open }">
            <button
                type="button"
                :aria-expanded="open"
                aria-label="Assinaturas pendentes"
                class="relative cursor-pointer rounded-lg p-1.5 text-slate-500 transition-all duration-[180ms] ease hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1 active:scale-[0.97]"
            >
                <!-- ícone de caneta / documento -->
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <NotificationBadge :count="count" />
            </button>
        </template>

        <template #default="{ close }">
            <!-- Header do dropdown -->
            <div class="flex items-center justify-between border-b px-4 py-2.5">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-700">Assinaturas pendentes</span>
                <span
                    v-if="count > 0"
                    class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700"
                >
                    {{ count }} pendente{{ count > 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Lista -->
            <div v-if="items.length === 0" class="px-4 py-8 text-center">
                <div class="text-2xl mb-2">✅</div>
                <p class="text-xs font-medium text-slate-600">Nenhuma assinatura pendente.</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Tudo assinado por aqui.</p>
            </div>

            <div v-else class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="px-4 py-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[12px] font-semibold text-slate-800 truncate">{{ item.patient_name }}</p>
                            <p class="text-[11px] text-slate-500 truncate mt-px">{{ item.label }}</p>
                            <div class="flex items-center gap-1.5 mt-1.5">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                    <span>✎</span> {{ item.badge }}
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">{{ item.occurred_label }}</p>
                        </div>
                        <a
                            :href="item.show_url"
                            class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-700 transition-colors"
                            @click="close"
                        >
                            Abrir
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div v-if="items.length > 0" class="border-t px-4 py-2 text-[11px] text-slate-400 text-center">
                Aguardando assinatura
            </div>
        </template>
    </NavbarDropdown>
</template>
