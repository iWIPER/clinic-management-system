<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    show: { type: Boolean, default: false },
    // Incrementado pelo TaskPanel a cada edição/status/pin/favorito/exclusão
    // de tarefa — permite ao drawer já aberto refletir mudanças (ex.: título
    // editado numa tarefa concluída) sem precisar fechar e reabrir.
    refreshKey: { type: Number, default: 0 },
})
defineEmits(['close'])

const toast = useToast()

const loading = ref(false)
const sections = ref([])

// Busca ao abrir, e de novo a cada mutação de tarefa enquanto está aberto —
// fechado, não há razão pra buscar (só volta a sincronizar quando reabrir).
async function fetchControlData() {
    loading.value = true
    try {
        const { data } = await window.axios.get(route('tasks.controle'))
        sections.value = data.sections
    } catch {
        toast.error('Não foi possível carregar o painel de controle.')
    } finally {
        loading.value = false
    }
}

watch(() => props.show, (open) => { if (open) fetchControlData() })
watch(() => props.refreshKey, () => { if (props.show) fetchControlData() })
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-200 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-150 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full">
        <!-- `absolute` (não `fixed`) — sobrepõe só a área do painel de Tarefas,
             dentro do container relative em TaskPanel.vue, sem empurrar a
             largura dos cards da lista (que continuam no fluxo normal). -->
        <!-- Ancorado por `right-0` — aumentar a largura cresce pra esquerda,
             sem mover a borda direita (mesmo padrão de "+1cm" já usado em
             TaskListItem.vue). -->
        <aside v-if="show" class="absolute inset-y-0 right-0 z-20 flex w-[calc(20rem+1.5cm)] flex-col border-l border-slate-200 bg-white shadow-xl">
            <header class="flex items-center justify-between border-b px-4 py-4">
                <h3 class="text-sm font-semibold text-slate-800">Controle de tarefas</h3>
                <button type="button" @click="$emit('close')" aria-label="Fechar painel de controle"
                        class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto px-4 py-4">
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Concluídas hoje</p>

                <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Carregando...</div>

                <div v-else class="space-y-4">
                    <section v-for="sec in sections" :key="sec.key">
                        <div class="flex items-center justify-between">
                            <span class="flex min-w-0 items-center gap-1.5 text-sm font-medium text-slate-700">
                                <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: sec.color || '#94a3b8' }" />
                                <span class="truncate">{{ sec.name }}</span>
                            </span>
                            <!-- `mr-1.5` — pequeno respiro da borda direita pra
                                 não ficar colado na barra de rolagem quando o
                                 conteúdo excede a altura do painel. -->
                            <span class="ml-2 mr-1.5 min-w-[1.25rem] shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-center text-[10px] font-semibold text-slate-500">
                                {{ sec.count }}
                            </span>
                        </div>

                        <ul v-if="sec.tasks.length" class="mt-1.5 space-y-1 pl-3.5">
                            <li v-for="t in sec.tasks" :key="t.id" class="flex items-baseline gap-1 truncate text-xs text-slate-500">
                                <span class="shrink-0">•</span>
                                <span class="truncate line-through">{{ t.title }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-1 pl-3.5 text-xs text-slate-400">Nenhuma tarefa concluída hoje.</p>
                    </section>
                </div>
            </div>
        </aside>
    </Transition>
</template>
