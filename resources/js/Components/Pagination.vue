<script setup>
import { computed } from 'vue'

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
        // { current_page, last_page, total, per_page }
    },
    // false remove a margem/borda superior do bloco de navegação — usado
    // quando o componente já está dentro de um container com espaçamento
    // próprio (ex: rodapé da listagem de Pacientes), pra não duplicar borda.
    // Default true preserva o visual de todos os consumidores existentes.
    bordered: { type: Boolean, default: true },
})
const emit = defineEmits(['change'])

const visible = computed(() => {
    const { current_page, last_page } = props.pagination
    if (last_page <= 1) return []
    const delta = 2
    const left  = Math.max(1, current_page - delta)
    const right = Math.min(last_page, current_page + delta)
    const pages = []
    for (let i = left; i <= right; i++) pages.push(i)
    return pages
})

const go = (page) => {
    const { current_page, last_page } = props.pagination
    if (page < 1 || page > last_page || page === current_page) return
    emit('change', page)
}
</script>

<template>
    <div v-if="pagination.last_page > 1">
        <div class="flex items-center justify-between" :class="bordered ? 'mt-4 pt-4 border-t border-slate-100' : ''">
            <!-- Anterior -->
            <button @click="go(pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1"
                    class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Anterior
            </button>

            <!-- Páginas -->
            <div class="flex items-center gap-0.5">
                <!-- Primeira página + reticências -->
                <template v-if="visible.length && visible[0] > 1">
                    <button @click="go(1)"
                            class="w-7 h-7 text-xs font-medium rounded-lg transition-colors text-slate-600 hover:bg-teal-50 hover:text-teal-700">
                        1
                    </button>
                    <span v-if="visible[0] > 2" class="text-slate-300 text-xs w-5 text-center">…</span>
                </template>

                <!-- Janela de páginas -->
                <button v-for="p in visible" :key="p"
                        @click="go(p)"
                        class="w-7 h-7 text-xs font-medium rounded-lg transition-colors"
                        :class="p === pagination.current_page
                            ? 'bg-teal-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'">
                    {{ p }}
                </button>

                <!-- Reticências + última página -->
                <template v-if="visible.length && visible[visible.length - 1] < pagination.last_page">
                    <span v-if="visible[visible.length - 1] < pagination.last_page - 1"
                          class="text-slate-300 text-xs w-5 text-center">…</span>
                    <button @click="go(pagination.last_page)"
                            class="w-7 h-7 text-xs font-medium rounded-lg transition-colors text-slate-600 hover:bg-teal-50 hover:text-teal-700">
                        {{ pagination.last_page }}
                    </button>
                </template>
            </div>

            <!-- Próxima -->
            <button @click="go(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page"
                    class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                Próxima
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <p class="text-center text-[10px] text-slate-400 mt-2">
            {{ pagination.total }} registro{{ pagination.total !== 1 ? 's' : '' }} no total
            · página {{ pagination.current_page }} de {{ pagination.last_page }}
        </p>
    </div>
</template>
