<script setup>
// `label` vazio omite o rótulo do grupo — usado pelo grupo final
// (Equipe/Indicações/Configurações), que fica sem cabeçalho, só marcado
// por uma linha fina acima (`divider`) em vez de um título de seção.
//
// Sem padding vertical no rótulo: o espaçamento ENTRE grupos é
// responsabilidade do container pai (Sidebar.vue, via `space-y-5`), não
// deste componente — um `first:pt-0` aqui não funcionaria mesmo, já que
// cada SidebarSection é sua própria raiz de componente, então o rótulo é
// sempre "primeiro filho" do seu próprio wrapper (o seletor `first:` nunca
// veria as OUTRAS seções como irmãs).
defineProps({
    label: { type: String, default: '' },
    divider: { type: Boolean, default: false },
})
</script>

<template>
    <div>
        <div v-if="divider" class="mx-auto mb-4 h-px w-[85%] bg-slate-200/70" />
        <div v-if="label" class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            {{ label }}
        </div>
        <nav class="flex flex-col gap-0.5">
            <slot />
        </nav>
    </div>
</template>
