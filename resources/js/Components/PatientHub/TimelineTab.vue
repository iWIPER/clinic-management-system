<script setup>
import { ref, computed } from 'vue';

const props = defineProps({ timeline: Array });

const filter = ref('todos');

const filters = [
    { id: 'todos', label: 'Todos' },
    { id: 'clinico', label: 'Clínico' },
    { id: 'financeiro', label: 'Financeiro' },
    { id: 'documentos', label: 'Documentos' },
    { id: 'arquivos', label: 'Arquivos' },
    { id: 'consultas', label: 'Consultas' },
];

const categoryIcons = {
    clinico: 'text-teal-600 bg-teal-50',
    financeiro: 'text-emerald-600 bg-emerald-50',
    documentos: 'text-blue-600 bg-blue-50',
    arquivos: 'text-purple-600 bg-purple-50',
    consultas: 'text-indigo-600 bg-indigo-50',
};

const filtered = computed(() => {
    if (filter.value === 'todos') return props.timeline;
    return props.timeline.filter(e => e.category === filter.value);
});

const fmtDateTime = (iso) => new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
});
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-2 mb-6">
            <button v-for="f in filters" :key="f.id" @click="filter = f.id"
                    class="px-3 py-1.5 text-xs font-medium rounded-full transition"
                    :class="filter === f.id ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                {{ f.label }}
            </button>
        </div>

        <div v-if="filtered.length" class="relative">
            <div class="absolute left-[11px] top-3 bottom-3 w-px bg-slate-200"></div>
            <div class="space-y-0">
                <div v-for="(event, i) in filtered" :key="i" class="flex gap-4 pb-6">
                    <div class="relative shrink-0 mt-1">
                        <span class="flex h-6 w-6 rounded-full border-2 border-white shadow ring-1 ring-slate-200 items-center justify-center text-[10px] font-bold"
                              :class="categoryIcons[event.category] ?? 'bg-slate-100 text-slate-600'">●</span>
                    </div>
                    <div class="flex-1 min-w-0 bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm">
                        <div class="text-xs text-slate-400 font-medium">{{ fmtDateTime(event.occurred_at) }}</div>
                        <div class="text-sm font-semibold text-slate-900 mt-0.5">{{ event.title }}</div>
                        <div v-if="event.detail" class="text-sm text-slate-600 mt-0.5">{{ event.detail }}</div>
                        <div v-if="event.meta?.preview" class="text-xs text-slate-500 mt-1 italic">{{ event.meta.preview }}…</div>
                    </div>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 text-center py-12">Nenhum evento nesta categoria.</p>
    </div>
</template>