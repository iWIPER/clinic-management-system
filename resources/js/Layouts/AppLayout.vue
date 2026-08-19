<script setup>
import { ref, computed } from 'vue'
import TaskPanel from '@/Components/Tasks/TaskPanel.vue'
import Sidebar from '@/Components/Navigation/Sidebar.vue'
import TopIsland from '@/Components/Navigation/TopIsland.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import TopProgress from '@/Components/Navbar/TopProgress.vue'

// ── Largura do conteúdo — content shell padrão do Wildental ─────────────
// `full` (padrão) é o CONTENT SHELL GLOBAL: largura = 100% da área
// disponível depois da sidebar MENOS 2×G (--shell-gutter, ver app.css) —
// não mais uma % fixa da largura. G é o mesmo token usado no respiro
// vertical acima/abaixo da TopIsland, então o resultado é uma margem
// esquerda/direita sempre igual ao respiro vertical, em vez de duas
// réguas independentes. Sem teto de largura: em monitores muito largos o
// conteúdo estica mais, mas isso é consequência direta da regra "100% -
// 2G", não uma exceção — nunca uma régua estreita tradicional.
// TopIsland e o slot `#pageHeader` (ver abaixo) vivem DENTRO deste mesmo
// `main`, então herdam exatamente esses limites automaticamente — não é
// preciso alinhar nada à parte.
//
// Páginas de conteúdo naturalmente estreito (formulários de cadastro/
// edição de uma entidade) continuam podendo pedir `content-width="sm|md|lg"`
// em vez de recriar um wrapper `max-w-*` próprio — é uma decisão explícita
// daquela página, não o padrão do sistema. `screen` é pra telas de
// trabalho de verdade (Agenda em fullscreen) que precisam de 100% da
// largura sem teto nenhum.
const props = defineProps({
    contentWidth: {
        type: String,
        default: 'full',
        validator: (v) => ['sm', 'md', 'lg', 'full', 'screen'].includes(v),
    },
    // Usado só pela Agenda em modo fullscreen: esconde a sidebar E a
    // TopIsland pra devolver o máximo de espaço (horizontal e vertical)
    // pra grade. Fullscreen prioriza a grade acima de qualquer elemento
    // global.
    hideSidebar: {
        type: Boolean,
        default: false,
    },
})

const CONTENT_WIDTH_CLASSES = {
    sm: 'max-w-lg px-4 sm:px-6 lg:px-8',
    md: 'max-w-2xl px-4 sm:px-6 lg:px-8',
    lg: 'max-w-4xl px-4 sm:px-6 lg:px-8',
    full: 'w-full px-[var(--shell-gutter)]',
    screen: 'max-w-none px-4 sm:px-6 lg:px-8',
}

const mainWidthClass = computed(() => CONTENT_WIDTH_CLASSES[props.contentWidth] ?? CONTENT_WIDTH_CLASSES.full)

// O wrapper sticky (TopIsland + PageHeader) cancela e reaplica o padding
// horizontal do `main` só pra fazer o próprio fundo opaco sangrar até a
// borda da área rolável (ver comentário no template) — cada tier cancela
// exatamente o padding que ele mesmo aplica em `main`, pra TopIsland e
// conteúdo nascerem sempre com o mesmo left/right.
const stickyBleedClass = computed(() =>
    props.contentWidth === 'full'
        ? '-mx-[var(--shell-gutter)] px-[var(--shell-gutter)]'
        : '-mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8'
)

// ── Painel de Tarefas ────────────────────────────────────────────────────
// Overlay client-side, não uma página — fica montado no layout para abrir
// sobre qualquer tela sem navegar (ver TaskPanel.vue).
const showTasksPanel = ref(false)
</script>

<template>
<div class="relative h-screen flex bg-slate-100 overflow-hidden">

  <!-- Barra fina de progresso de navegação Inertia — renderizada uma única
       vez aqui, ancorada nesta raiz `relative` pra cobrir a largura inteira
       da viewport (sidebar incluída), do jeito que já se comportava antes
       da sidebar existir. O Admin tem a sua própria instância dentro de
       Topbar.vue — não é a mesma, mas também não há duplicação: cada modo
       de shell (clínica vs. admin) usa exatamente uma. -->
  <TopProgress />

  <Sidebar v-if="!hideSidebar" />

  <!-- ── Região rolável. `scroll-region`: diz ao Inertia que esta div — e
       não a window — é a área cujo scroll ele deve resetar/salvar/
       restaurar entre visitas (ver @inertiajs/core Scroll.regions()). Sem
       isso, o reset de scroll nas navegações e a restauração via voltar/
       avançar do navegador silenciosamente deixam de funcionar. ── -->
  <div class="flex-1 overflow-y-auto" scroll-region style="scrollbar-gutter: stable">
    <main :class="[mainWidthClass, 'mx-auto pb-6']">

      <!-- ── Wrapper sticky único: TopIsland + toolbar da página (quando
           houver) ficam UM DENTRO DO OUTRO aqui, não em dois elementos
           sticky posicionados independentemente — assim o espaçamento
           entre eles é margem normal (não precisa bater offsets), e o
           fundo opaco do wrapper cobre toda a região de uma vez, sem
           frestas por onde o conteúdo rolado vazaria. `stickyBleedClass`
           cancela o padding horizontal de `main` e reaplica o mesmo valor
           aqui dentro (ver acima) — assim TopIsland e conteúdo nascem com
           exatamente o mesmo left/right, qualquer que seja o tier. pt/pb
           usam o MESMO --shell-gutter (ver app.css) — é a mesma distância
           acima da TopIsland e abaixo dela antes do conteúdo começar;
           junto com o --shell-top-h derivado dele, é o que qualquer sticky
           dentro do fluxo de página (ex.: PatientHubTabs.vue) usa pra não
           ficar escondido atrás da TopIsland. ── -->
      <div v-if="!hideSidebar" :class="['sticky top-0 z-30 space-y-3 bg-slate-100 pb-[var(--shell-gutter)] pt-[var(--shell-gutter)]', stickyBleedClass]">
        <TopIsland @open-tasks="showTasksPanel = true" />
        <slot name="pageHeader" />
      </div>

      <slot />
    </main>
  </div>

  <TaskPanel :show="showTasksPanel" @close="showTasksPanel = false" />

  <ToastContainer />
</div>
</template>
