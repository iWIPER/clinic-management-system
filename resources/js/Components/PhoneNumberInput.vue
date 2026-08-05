<script setup>
import { ref, computed, watch } from 'vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import { COUNTRIES, DEFAULT_COUNTRY, flagClass } from '@/lib/countries.js'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

// Sem coluna de DDI separada no banco: o valor final é uma string só
// ("+55 11987654321"). Ordenado por tamanho de DDI decrescente para casar o
// prefixo mais específico primeiro (ex: +1876 antes do +1 genérico).
const byDialLength = [...COUNTRIES].sort((a, b) => b.dial.length - a.dial.length)

function parseValue(value) {
    const raw = value || ''
    if (raw.startsWith('+')) {
        const match = byDialLength.find((c) => raw === c.dial || raw.startsWith(c.dial + ' '))
        if (match) {
            return { country: match, local: raw.slice(match.dial.length).trim() }
        }
    }
    return { country: DEFAULT_COUNTRY, local: raw }
}

const initial = parseValue(props.modelValue)
const selectedCountry = ref(initial.country)
const localNumber = ref(initial.local)

function compose() {
    return localNumber.value ? `${selectedCountry.value.dial} ${localNumber.value}` : ''
}

// Evita que o watch abaixo reprocesse o valor que a própria emissão acabou
// de gerar (o v-model do pai ecoa de volta como prop) — flag em vez de
// comparação de string, não depende de formatação/espaços baterem.
let emittedInternally = false

function emitChange() {
    emittedInternally = true
    emit('update:modelValue', compose())
}

// Ressincroniza se o valor mudar por fora (ex: form recarregado após salvar).
watch(() => props.modelValue, (value) => {
    if (emittedInternally) {
        emittedInternally = false
        return
    }
    const next = parseValue(value)
    selectedCountry.value = next.country
    localNumber.value = next.local
})

const search = ref('')
const filteredCountries = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return COUNTRIES
    return COUNTRIES.filter((c) => c.name.toLowerCase().includes(term) || c.dial.includes(term))
})

function selectCountry(country, close) {
    selectedCountry.value = country
    search.value = ''
    emitChange()
    close()
}
</script>

<template>
    <div class="flex items-center w-full border rounded-lg focus-within:border-slate-400">
        <NavbarDropdown align="left" width="w-64">
            <template #trigger>
                <button type="button"
                        class="flex items-center gap-1.5 rounded-l-lg border-r px-2.5 py-2.5 text-sm hover:bg-slate-50 shrink-0">
                    <span :class="flagClass(selectedCountry.code)" class="rounded-sm shrink-0"></span>
                    <span class="text-slate-600">{{ selectedCountry.dial }}</span>
                    <span class="text-slate-300 text-xs">▾</span>
                </button>
            </template>
            <template #default="{ close }">
                <div class="p-2">
                    <input v-model="search" type="text" placeholder="Buscar país..."
                           class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs mb-1.5" />
                    <ul class="max-h-56 overflow-y-auto">
                        <li v-for="country in filteredCountries" :key="country.code + country.dial">
                            <button type="button" @click="selectCountry(country, close)"
                                    class="w-full flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-left hover:bg-slate-50">
                                <span :class="flagClass(country.code)" class="rounded-sm shrink-0"></span>
                                <span class="flex-1 min-w-0 truncate">{{ country.name }}</span>
                                <span class="text-slate-400 text-xs shrink-0">{{ country.dial }}</span>
                            </button>
                        </li>
                        <li v-if="!filteredCountries.length" class="px-2 py-3 text-xs text-slate-400 text-center">
                            Nenhum país encontrado.
                        </li>
                    </ul>
                </div>
            </template>
        </NavbarDropdown>

        <input
            :value="localNumber"
            @input="localNumber = $event.target.value; emitChange()"
            type="text"
            :placeholder="placeholder"
            class="flex-1 min-w-0 border-0 p-2.5 focus:outline-none focus:ring-0"
        />
    </div>
</template>
