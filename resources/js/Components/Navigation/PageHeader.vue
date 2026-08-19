<script setup>
// Título/descrição/ações contextuais da página — de propósito SEM caixa
// (sem borda/sombra/fundo próprio): fica dentro do mesmo wrapper sticky da
// TopIsland (slot #pageHeader do AppLayout), então é só tipografia
// flutuando sobre o fundo cinza — não é um card, é o espaço vazio acima do
// conteúdo "subindo" pra receber o título quando a página declara um.
// Páginas sem PageHeader simplesmente deixam esse espaço vazio.
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
})
</script>

<template>
    <header class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-1">
        <div class="flex min-w-0 items-baseline gap-2">
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ title }}</h1>
            <span v-if="description" class="hidden truncate text-sm text-slate-500 sm:inline">{{ description }}</span>
        </div>

        <div v-if="$slots.default" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
