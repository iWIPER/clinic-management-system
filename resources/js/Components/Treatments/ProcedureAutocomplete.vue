<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
    modelValue:  { type: [Number, String], default: null },
    procedureName: { type: String, default: '' },
    treatments:  { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Buscar procedimento...' },
    invalid:     { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:procedureName', 'select'])

const query = ref(props.procedureName || '')
const open = ref(false)
const containerRef = ref(null)
const highlighted = ref(0)

watch(() => props.procedureName, (v) => { if (v !== query.value) query.value = v || '' })

const results = computed(() => {
    const q = query.value.trim().toLowerCase()
    const list = q
        ? props.treatments.filter(t => t.nome.toLowerCase().includes(q))
        : props.treatments
    return list.slice(0, 30)
})

const onInput = () => {
    open.value = true
    highlighted.value = 0
    emit('update:procedureName', query.value)
    if (props.modelValue) emit('update:modelValue', null)
}

const select = (treatment) => {
    query.value = treatment.nome
    open.value = false
    emit('update:modelValue', treatment.id)
    emit('update:procedureName', treatment.nome)
    emit('select', treatment)
}

const onKeydown = (e) => {
    if (!open.value && (e.key === 'ArrowDown' || e.key === 'Enter')) { open.value = true; return }
    if (e.key === 'ArrowDown') { e.preventDefault(); highlighted.value = Math.min(highlighted.value + 1, results.value.length - 1) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlighted.value = Math.max(highlighted.value - 1, 0) }
    else if (e.key === 'Enter') { e.preventDefault(); if (results.value[highlighted.value]) select(results.value[highlighted.value]) }
    else if (e.key === 'Escape') { open.value = false }
}

const onOutside = (e) => {
    if (containerRef.value && !containerRef.value.contains(e.target)) open.value = false
}

const onFocus = async () => {
    open.value = true
    await nextTick()
}
</script>

<template>
<div ref="containerRef" class="relative" @focusout="onOutside">
    <input v-model="query" type="text" :placeholder="placeholder"
           class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400/60 focus:border-teal-400"
           :class="invalid ? 'border-red-400' : 'border-slate-200'"
           @input="onInput" @focus="onFocus" @keydown="onKeydown"
           autocomplete="off" />

    <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0">
        <div v-if="open && results.length"
             class="absolute z-30 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
            <button v-for="(t, i) in results" :key="t.id" type="button"
                    class="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-sm transition-colors"
                    :class="i === highlighted ? 'bg-teal-50 text-teal-800' : 'hover:bg-slate-50 text-slate-700'"
                    @mousedown.prevent="select(t)"
                    @mouseenter="highlighted = i">
                <span class="truncate">{{ t.nome }}</span>
                <span class="shrink-0 text-xs font-semibold text-emerald-600">
                    {{ Number(t.preco_base ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
                </span>
            </button>
        </div>
        <div v-else-if="open && query.trim()"
             class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-xl px-3.5 py-3 text-xs text-slate-400">
            Nenhum procedimento do catálogo encontrado — será salvo como texto livre.
        </div>
    </Transition>
</div>
</template>
