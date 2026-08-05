<script setup>
import { categoryMeta } from '@/composables/useAnamnesisCategories'
import { ref } from 'vue'

const props = defineProps({
    categoryName: String,
    questions: Array,
    expanded: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'drag-start', 'drop', 'edit', 'duplicate', 'detach', 'deactivate', 'toggle-required'])

const dragId = ref(null)
const meta = categoryMeta(props.categoryName)

const titleLabel = props.categoryName
    .toLowerCase()
    .replace(/\b\w/g, c => c.toUpperCase())

const onDragStart = (id) => { dragId.value = id; emit('drag-start', id) }
const onDrop = (targetId) => {
    if (!dragId.value || dragId.value === targetId) return
    emit('drop', { from: dragId.value, to: targetId, category: props.categoryName })
    dragId.value = null
}
</script>

<template>
    <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
        <button
            type="button"
            @click="emit('toggle')"
            class="w-full flex items-center justify-between gap-2 px-4 py-2.5 text-left hover:bg-slate-50/80 transition-colors"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm text-slate-400 w-4 shrink-0">{{ expanded ? '▼' : '▶' }}</span>
                <span class="text-base leading-none">{{ meta.icon }}</span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-700">{{ titleLabel }}</p>
                    <p class="text-[10px] text-slate-400 truncate">{{ questions.length }} pergunta(s)</p>
                </div>
            </div>
        </button>

        <div v-show="expanded" class="border-t border-slate-100">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/60 text-left text-[10px] text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="p-2 w-8" />
                        <th class="p-2">Pergunta</th>
                        <th class="p-2 w-24">Tipo</th>
                        <th class="p-2 w-12 text-center">Obr.</th>
                        <th class="p-2 w-12 text-center">Ativa</th>
                        <th class="p-2 w-36 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="q in questions"
                        :key="q.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50"
                        draggable="true"
                        @dragstart="onDragStart(q.id)"
                        @dragover.prevent
                        @drop="onDrop(q.id)"
                    >
                        <td class="p-2 text-slate-300 cursor-grab select-none text-center">☰</td>
                        <td class="p-2">
                            <span class="text-[13px] text-slate-800">{{ q.text }}</span>
                            <span v-if="q.has_alert" class="ml-1.5 inline-block rounded px-1 py-px text-[9px] font-medium bg-amber-100 text-amber-700">Alerta</span>
                        </td>
                        <td class="p-2 text-[11px] text-slate-500">{{ q.type_label }}</td>
                        <td class="p-2 text-center">
                            <input type="checkbox" :checked="q.pivot_is_required" @change="emit('toggle-required', q)" />
                        </td>
                        <td class="p-2 text-center text-[11px]" :class="q.is_active ? 'text-slate-600' : 'text-slate-400'">
                            {{ q.is_active ? 'Sim' : 'Não' }}
                        </td>
                        <td class="p-2 text-right space-x-1">
                            <button type="button" @click="emit('edit', q)" class="text-[11px] text-slate-600 hover:text-slate-900">Editar</button>
                            <button type="button" @click="emit('duplicate', q)" class="text-[11px] text-slate-600 hover:text-slate-900">Duplicar</button>
                            <button type="button" @click="emit('detach', q.id)" class="text-[11px] text-red-600 hover:text-red-700">Excluir</button>
                            <button type="button" @click="emit('deactivate', q.id)" class="text-[11px] text-slate-500 hover:text-slate-700">Desativar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>