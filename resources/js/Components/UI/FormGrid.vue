<script setup>
// Grid de formulário responsivo — 1 coluna no mobile sempre, cresce a
// partir daí. Existe pra não repetir `grid grid-cols-1 sm:grid-cols-2...`
// em cada formulário na mão (e principalmente pra nunca mais nascer um
// `grid-cols-2`/`grid-cols-3` cravado sem o degrau mobile, que foi o
// problema real encontrado na auditoria).
//
// cols=2 -> 1 coluna (mobile) / 2 colunas a partir de `sm` (640px)
// cols=3 -> 1 coluna (mobile) / 2 colunas a partir de `sm` / 3 a partir de `lg` (1024px)
//
// Campos que precisam ocupar mais de 1 coluna (ex.: um endereço largo)
// continuam usando `sm:col-span-2` no próprio elemento filho — o mesmo
// breakpoint em que a 2ª coluna aparece aqui, então o span sempre bate
// com o momento em que faz sentido.
const props = defineProps({
    cols: { type: [Number, String], default: 2 },
})
</script>

<template>
    <div class="grid grid-cols-1 gap-4" :class="Number(cols) === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2'">
        <slot />
    </div>
</template>
