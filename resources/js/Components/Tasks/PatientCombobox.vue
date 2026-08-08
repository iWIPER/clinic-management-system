<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue'
import { CheckIcon } from '@heroicons/vue/20/solid'
import { computePosition, flip, shift, size, offset, autoUpdate } from '@floating-ui/dom'

// v-model carrega o paciente inteiro (ou null), não só o id — assim o campo
// já nasce mostrando o nome de um paciente pré-selecionado (editar tarefa)
// sem precisar buscar de novo só pra descobrir o nome de um id que já temos.
const selected = defineModel({ type: Object, default: null })

const query = ref('')
const results = ref([])
const searching = ref(false)
let debounceTimer = null

async function search(q) {
    searching.value = true
    try {
        const { data } = await window.axios.get(route('patients.search'), { params: { q } })
        results.value = data
    } catch {
        results.value = []
    } finally {
        searching.value = false
    }
}

watch(query, (q) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => search(q), 300)
})

// "—" em vez de um placeholder cinza pra "Sem paciente" — placeholder sempre
// lê como campo desabilitado; um traço no texto normal do valor deixa claro
// que é um estado válido e definitivo, não um campo vazio esperando algo.
const displayValue = (p) => p ? fullName(p) : '—'

const fullName = (p) => `${p.nome} ${p.sobrenome ?? ''}`.trim()

// ── Posicionamento via @floating-ui/dom — o mesmo motor por trás do
// Headless UI v2 e do Radix (a classe de solução que Select2 e componentes
// modernos usam). O dropdown é teleportado pra <body> com strategy 'absolute',
// o que tira ele de dentro do overflow-hidden do modal (causa raiz dos bugs
// anteriores de corte) — flip/shift decidem abrir pra cima ou embaixo
// automaticamente e nunca deixam o dropdown cortado; `size` limita a altura
// ao espaço disponível (~6 linhas no caso comum) com scroll interno.
//
// O elemento posicionado é uma <div> nativa própria (`dropdownRef`), não o
// `<ComboboxOptions>` diretamente — o `ref` do Headless UI Vue nesse
// componente não resolve pro elemento DOM real neste setup (vem como um
// objeto interno, floating-ui exige um Element de verdade). `static` no
// ComboboxOptions faz ele só cuidar da lógica (teclado/ARIA/seleção); quem
// decide se está visível e onde é esta div, via `v-if="open"`. ────────────
const inputWrapperRef = ref(null)
const dropdownRef = ref(null)
const dropdownStyle = ref({ maxHeight: '216px', width: '0px' })
let stopAutoUpdate = null
let stopManualScrollListener = null

const ROW_HEIGHT = 36
const VISIBLE_ROWS = 6
const EDGE_MARGIN = 28 // meio-termo dos 24-32px pedidos

async function updatePosition() {
    if (!inputWrapperRef.value || !dropdownRef.value) return

    const { x, y } = await computePosition(inputWrapperRef.value, dropdownRef.value, {
        placement: 'bottom-start',
        strategy: 'absolute',
        middleware: [
            offset(4),
            flip({ padding: EDGE_MARGIN }),
            shift({ padding: EDGE_MARGIN }),
            size({
                padding: EDGE_MARGIN,
                apply({ availableHeight }) {
                    dropdownStyle.value.maxHeight = `${Math.max(Math.min(availableHeight, ROW_HEIGHT * VISIBLE_ROWS), 80)}px`
                },
            }),
        ],
    })

    dropdownStyle.value.left = `${x}px`
    dropdownStyle.value.top = `${y}px`
    dropdownStyle.value.width = `${inputWrapperRef.value.getBoundingClientRect().width}px`
}

// `dropdownRef` só existe enquanto `open` é true (ver `v-if` no template) —
// reage a isso pra ligar/desligar o autoUpdate.
//
// `ancestorScroll`/`ancestorResize` desligados de propósito: o mecanismo
// interno do floating-ui pra rastrear ancestrais com scroll (`getOverflow
// Ancestors`) quebra neste app (chega em `window` e tenta chamar
// `getComputedStyle(window)`, que lança TypeError). `elementResize` (via
// ResizeObserver nos dois elementos) continua ligado, então flip/shift/size
// se recalculam sozinhos se o input ou o dropdown mudarem de tamanho; scroll
// dentro do formulário é coberto à parte por um listener manual simples.
watch(dropdownRef, (el) => {
    stopAutoUpdate?.()
    stopAutoUpdate = null
    stopManualScrollListener?.()
    stopManualScrollListener = null

    if (el && inputWrapperRef.value) {
        if (results.value.length === 0) search('')
        stopAutoUpdate = autoUpdate(inputWrapperRef.value, el, updatePosition, {
            ancestorScroll: false,
            ancestorResize: false,
        })
        stopManualScrollListener = attachManualScrollListener()
    }
})

function attachManualScrollListener() {
    const scrollable = findScrollableAncestor(inputWrapperRef.value)
    if (!scrollable) return null
    scrollable.addEventListener('scroll', updatePosition, { passive: true })
    return () => scrollable.removeEventListener('scroll', updatePosition)
}

function findScrollableAncestor(el) {
    let node = el?.parentElement
    while (node) {
        if (/(auto|scroll)/.test(getComputedStyle(node).overflowY)) return node
        node = node.parentElement
    }
    return null
}

onUnmounted(() => {
    stopAutoUpdate?.()
    stopManualScrollListener?.()
})

function onFocus(e) {
    e.target.select()
}
</script>

<template>
    <Combobox v-model="selected" by="id" nullable v-slot="{ open }">
        <div ref="inputWrapperRef" class="relative">
            <ComboboxInput
                class="w-full rounded-lg border-slate-300 text-sm text-slate-800 transition-colors focus:border-emerald-500 focus:ring-emerald-500"
                :display-value="displayValue"
                @change="query = $event.target.value"
                @focus="onFocus" />
            <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                </svg>
            </ComboboxButton>
        </div>

        <Teleport to="body">
            <div v-if="open" ref="dropdownRef" :style="{ position: 'absolute', ...dropdownStyle }"
                 class="z-[60] overflow-hidden rounded-lg border border-slate-200 bg-white text-sm shadow-lg">
                <ComboboxOptions static>
                    <ComboboxOption :value="null" v-slot="{ active }">
                        <div class="flex cursor-pointer items-center justify-between border-b border-slate-100 px-3.5 py-2 transition-colors"
                             :class="active ? 'bg-emerald-50 text-emerald-800' : 'text-slate-500'">
                            Sem paciente
                            <CheckIcon v-if="!selected" class="h-4 w-4 shrink-0 text-emerald-600" />
                        </div>
                    </ComboboxOption>

                    <div class="overflow-y-auto" :style="{ maxHeight: `calc(${dropdownStyle.maxHeight} - ${ROW_HEIGHT}px)` }">
                        <div v-if="searching" class="px-3.5 py-2 text-xs text-slate-400">Buscando...</div>
                        <div v-else-if="query && results.length === 0" class="px-3.5 py-2 text-xs text-slate-400">Nenhum paciente encontrado.</div>

                        <ComboboxOption v-for="p in results" :key="p.id" :value="p" v-slot="{ active }">
                            <div class="flex cursor-pointer items-center justify-between px-3.5 py-2 transition-colors"
                                 :class="active ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700'">
                                {{ fullName(p) }}
                                <CheckIcon v-if="selected?.id === p.id" class="h-4 w-4 shrink-0 text-emerald-600" />
                            </div>
                        </ComboboxOption>
                    </div>
                </ComboboxOptions>
            </div>
        </Teleport>
    </Combobox>
</template>
