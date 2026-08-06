<script setup>
import { ref } from 'vue'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import { ClipboardDocumentIcon, CheckIcon } from '@heroicons/vue/24/outline'

// Cartão flutuante genérico de "dados + copiar", para qualquer bloco
// informativo do Patient Hub (Contato de emergência, Responsável, Convênio,
// Titular do plano, Empresa, Médico solicitante...). Quem usa só monta a
// lista de `fields` (e opcionalmente um ícone de título) — nenhuma lógica
// nova precisa entrar aqui para um novo bloco. Reaproveita o NavbarDropdown
// para toggle/clique-fora/ESC/animação em vez de reimplementar essa mecânica.
defineProps({
    title: { type: String, default: '' },
    // Heroicon opcional exibido antes do título (ex: UserIcon, BuildingOfficeIcon).
    titleIcon: { type: [Object, Function], default: null },
    // [{ label: string, value: string, copyValue?: string }]
    // copyValue omitido (ou falsy) = linha sem ação de copiar.
    fields: { type: Array, default: () => [] },
    // 'w-full' (padrão) faz o card ocupar a mesma largura do gatilho — como
    // o gatilho ocupa toda a largura disponível (ver PatientOverviewTab.vue),
    // o wrapper relative do NavbarDropdown também fica largura-cheia (bloco
    // padrão, sem inline-block), e o painel casa com essa mesma largura.
    width: { type: String, default: 'w-full min-w-[260px]' },
    align: { type: String, default: 'left' },
    direction: { type: String, default: 'down' },
})

const copiedIndex = ref(null)
function copy(value, index) {
    navigator.clipboard.writeText(value).then(() => {
        copiedIndex.value = index
        setTimeout(() => { copiedIndex.value = null }, 1000)
    })
}
</script>

<template>
    <NavbarDropdown :align="align" :width="width" :direction="direction">
        <template #trigger="{ open }">
            <slot name="trigger" :open="open" />
        </template>
        <template #default>
            <div class="p-4">
                <p v-if="title" class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wide pb-2.5 mb-3 border-b border-slate-100">
                    <component :is="titleIcon" v-if="titleIcon" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    {{ title }}
                </p>
                <dl class="space-y-3">
                    <div v-for="(field, i) in fields" :key="i" class="min-w-0">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                        <dd class="flex items-center gap-1 mt-0.5">
                            <span class="text-sm font-medium text-slate-700 truncate">{{ field.value }}</span>
                            <button v-if="field.copyValue" type="button"
                                    @click="copy(field.copyValue, i)"
                                    title="Copiar"
                                    class="shrink-0 cursor-pointer text-slate-500 hover:text-slate-800 transition-colors">
                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-50"
                                    enter-to-class="opacity-100 scale-100"
                                    mode="out-in"
                                >
                                    <CheckIcon v-if="copiedIndex === i" class="w-4 h-4 text-teal-600" />
                                    <ClipboardDocumentIcon v-else class="w-4 h-4" />
                                </Transition>
                            </button>
                        </dd>
                    </div>
                </dl>
            </div>
        </template>
    </NavbarDropdown>
</template>
