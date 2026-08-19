<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import NavbarIconButton from '@/Components/Navbar/NavbarIconButton.vue'
import NotificationCenter from '@/Components/Navbar/NotificationCenter.vue'
import ClinicLogo from '@/Components/ClinicLogo.vue'
import QuickActionsMenu from './QuickActionsMenu.vue'
import UserMenu from './UserMenu.vue'

// Três "pílulas" flutuantes independentes — não uma barra contínua. O
// espaço entre elas fica vazio de propósito (fundo cinza aparecendo),
// reforçando a sensação de elementos soltos/flutuantes em vez de uma
// topbar disfarçada de card único.
//
// A ilha de contexto da clínica foi REINTRODUZIDA (existia antes de virar
// o menu de Ações rápidas, ver decisão registrada em rodadas anteriores) —
// agora como uma pílula própria, separada de Atalhos por um gap deliberado
// (ml-10, ver template), não mais competindo pela mesma posição. Reaproveita
// ClinicLogo.vue (já usado no resto do sistema pra exibir a logo/imagem da
// clínica atual, com fallback próprio) em vez de duplicar a lógica de
// imagem — é só identificação (logo + nome), sem navegação nem troca de
// clínica.
const page = usePage()
const currentClinic = computed(() => page.props.currentClinic)

const emit = defineEmits(['open-tasks'])
</script>

<template>
    <div class="flex items-center">
        <QuickActionsMenu />

        <!-- Ilha da clínica — mesma família visual das outras pílulas
             (h-11, rounded-full, border-slate-200/80, bg-white, shadow-sm),
             só identificação (imagem + nome), não é troca de clínica nem
             navegação estrutural — mas leva ao mesmo lugar que o próprio
             usuário já iria pra trocar essa identidade (Configurações >
             geral, onde o logo/nome são editados), então funciona como um
             atalho direto pra lá. `ml-10` (40px, ≥lg) garante a distância
             deliberada de Atalhos, independente de quanto espaço sobra na
             tela — não é um gap "que sobrou" do justify-between; encolhe
             pra ml-6 (24px) abaixo de `lg` (mesmo breakpoint em que a
             sidebar já vira drawer — não é um valor novo). O nome some
             abaixo de `sm` (mesmo corte já usado pro ícone de Tarefas
             aqui do lado, ver mais abaixo) — a imagem sozinha continua
             identificando a clínica sem estourar a largura no mobile. -->
        <Link v-if="currentClinic" :href="route('clinic-settings.edit')"
              class="ml-6 lg:ml-10 flex h-11 shrink-0 items-center gap-2 rounded-full border border-slate-200/80 bg-white px-2.5 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/35 focus-visible:ring-offset-1"
              title="Configurações da clínica">
            <div class="h-7 w-7 shrink-0 overflow-hidden rounded-full">
                <ClinicLogo :clinic="currentClinic" img-class="h-full w-full object-cover" />
            </div>
            <span class="hidden max-w-[10rem] truncate text-sm font-medium text-slate-700 sm:inline">{{ currentClinic.name }}</span>
        </Link>

        <!-- Pílulas de utilitários + perfil — empurradas pro extremo
             direito (ml-auto), preservando "ícones + usuário sempre à
             direita" já aprovado, mesma altura h-11.

             O ícone de Tarefas antes só aparecia a partir de `sm` (640px) —
             abaixo disso não havia NENHUMA forma de abrir o painel de
             Tarefas, achado P0 já registrado na auditoria de responsividade
             ("Tarefas mobile — bloqueia uso real no dispositivo"). Corrigido
             aqui como pré-requisito da R3: sem o botão, nenhuma adaptação
             de Sidebar/Board dentro do painel é alcançável no smartphone.
             Unificado num único grupo sempre visível (a duplicata de
             NotificationCenter só existia pra cobrir esse corte). -->
        <div class="ml-auto flex h-11 shrink-0 items-center gap-2">
            <div class="flex h-11 items-center gap-1 rounded-full border border-slate-200/80 bg-white px-1.5 shadow-sm">
                <NavbarIconButton tooltip="Tarefas" @click="emit('open-tasks')">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </NavbarIconButton>

                <NotificationCenter />
            </div>

            <div class="flex h-11 items-center rounded-full border border-slate-200/80 bg-white px-1 shadow-sm">
                <UserMenu mode="clinic" />
            </div>
        </div>
    </div>
</template>
