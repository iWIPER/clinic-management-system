<script setup>
import {
    InboxIcon, CalendarDaysIcon, ClockIcon, CheckIcon, StarIcon, Cog6ToothIcon, PlusIcon,
} from '@heroicons/vue/24/outline'
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'

const MAX_CUSTOM_LISTS = 5

defineProps({
    activeView: { type: String, required: true },
    scope: { type: String, required: true },
    counts: { type: Object, required: true },
    // { mine: {name,color,...}, team: {...}, custom: [{id,name,color,is_owner,...}] }
    lists: { type: Object, default: () => ({}) },
})

defineEmits(['update:activeView', 'update:scope', 'close', 'open-list-settings', 'create-list'])

const VIEWS = [
    { id: 'inbox',    label: 'Entrada',    icon: InboxIcon },
    { id: 'today',    label: 'Hoje',       icon: CalendarDaysIcon },
    { id: 'upcoming', label: 'Próximas',   icon: ClockIcon },
    { id: 'done',     label: 'Concluídas', icon: CheckIcon },
]
</script>

<template>
    <aside class="flex w-56 shrink-0 flex-col border-r bg-slate-50">
        <div class="flex items-center justify-between px-4 py-4">
            <div class="flex items-start gap-1">
                <h2 class="text-sm font-semibold text-slate-800">Tarefas</h2>

                <!-- Ajuda — reaproveita o NavbarDropdown (mesma mecânica de
                     toggle/clique-fora/Esc já usada em todo o sistema, ex.:
                     PatientMarkerManager.vue), só troca o conteúdo. -->
                <NavbarDropdown align="left" width="w-[26rem]">
                    <template #trigger>
                        <!-- "?" solto, sem círculo ao redor — só o caractere,
                             menor que o título e elevado (tipo sobrescrito),
                             um pouco mais visível que o cinza claro padrão. -->
                        <button type="button" aria-label="Ajuda sobre o módulo de Tarefas"
                                class="-mt-0.5 flex h-3 w-3 items-center justify-center text-[10px] font-bold leading-none text-slate-400 transition-colors hover:text-slate-600">
                            ?
                        </button>
                    </template>
                    <template #default>
                        <div class="max-h-[85vh] overflow-y-auto p-4 text-xs leading-relaxed text-slate-600">
                            <p class="mb-3 text-sm font-bold text-slate-800">Como organizar suas tarefas</p>

                            <ul class="space-y-2.5">
                                <li class="flex gap-2">
                                    <InboxIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <p><span class="font-semibold text-slate-700">Entrada</span> — use para tarefas que ainda não têm uma data definida. É sua caixa de entrada para organizar depois.</p>
                                </li>
                                <li class="flex gap-2">
                                    <CalendarDaysIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <p><span class="font-semibold text-slate-700">Hoje</span> — para uma tarefa aparecer aqui, defina como vencimento a data de hoje.</p>
                                </li>
                                <li class="flex gap-2">
                                    <ClockIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <p><span class="font-semibold text-slate-700">Próximas</span> — para uma tarefa aparecer aqui, informe uma data de vencimento futura.</p>
                                </li>
                                <li class="flex gap-2">
                                    <CheckIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <p><span class="font-semibold text-slate-700">Concluídas</span> — ao marcar uma tarefa como concluída, ela é movida automaticamente para cá.</p>
                                </li>
                                <li class="flex gap-2">
                                    <StarIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" />
                                    <p><span class="font-semibold text-slate-700">Favoritos</span> — encontre rapidamente as tarefas importantes que você marcou com a estrela.</p>
                                </li>
                            </ul>

                            <div class="mt-3 border-t border-slate-100 pt-3">
                                <p class="font-semibold text-slate-700">Escopos</p>
                                <p class="mt-1"><span class="font-medium text-slate-700">Minhas tarefas</span> mostra suas tarefas pessoais.</p>
                                <p class="mt-1"><span class="font-medium text-slate-700">Tarefas da equipe</span> mostra tarefas compartilhadas com a equipe.</p>
                                <p class="mt-1">Os demais escopos podem ser usados para organizar tarefas por contexto, projeto ou finalidade.</p>
                            </div>

                            <div class="mt-3 rounded-lg bg-slate-50 p-2.5">
                                <p>Você pode alterar o vencimento de uma tarefa a qualquer momento — ela passa automaticamente para a categoria correspondente:</p>
                                <ul class="mt-1.5 space-y-0.5 text-slate-500">
                                    <li>Sem data → <span class="font-medium text-slate-700">Entrada</span></li>
                                    <li>Data de hoje → <span class="font-medium text-slate-700">Hoje</span></li>
                                    <li>Data futura → <span class="font-medium text-slate-700">Próximas</span></li>
                                    <li>Marcada como concluída → <span class="font-medium text-slate-700">Concluídas</span></li>
                                </ul>
                            </div>
                        </div>
                    </template>
                </NavbarDropdown>
            </div>

            <button type="button" @click="$emit('close')" aria-label="Fechar"
                    class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-0.5 px-2">
            <button v-for="v in VIEWS" :key="v.id" type="button"
                    @click="$emit('update:activeView', v.id)"
                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors"
                    :class="activeView === v.id ? 'bg-emerald-100 text-emerald-800' : 'text-slate-600 hover:bg-slate-100'">
                <span class="flex items-center gap-2">
                    <component :is="v.icon" class="h-4 w-4 shrink-0" />
                    {{ v.label }}
                </span>
                <span class="min-w-[1.25rem] rounded-full px-1.5 py-0.5 text-center text-[10px] font-semibold"
                      :class="[
                          activeView === v.id ? 'bg-emerald-200 text-emerald-800' : 'bg-slate-200 text-slate-500',
                          counts[v.id] ? '' : 'opacity-50',
                      ]">
                    {{ counts[v.id] ?? 0 }}
                </span>
            </button>

            <button type="button" @click="$emit('update:activeView', 'favorites')"
                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors"
                    :class="activeView === 'favorites' ? 'bg-emerald-100 text-emerald-800' : 'text-slate-600 hover:bg-slate-100'">
                <span class="flex items-center gap-2">
                    <StarIcon class="h-4 w-4 shrink-0" :class="activeView === 'favorites' ? '' : 'text-amber-400'" />
                    Favoritos
                </span>
                <span class="min-w-[1.25rem] rounded-full px-1.5 py-0.5 text-center text-[10px] font-semibold"
                      :class="[
                          activeView === 'favorites' ? 'bg-emerald-200 text-emerald-800' : 'bg-slate-200 text-slate-500',
                          counts.favorites ? '' : 'opacity-50',
                      ]">
                    {{ counts.favorites ?? 0 }}
                </span>
            </button>
        </nav>

        <!-- Bolinha reflete a cor configurada em cada lista (engrenagem abre
             TaskListSettingsModal) — diferencia visualmente "Minhas
             tarefas"/"Tarefas da equipe" além do texto, já que os nomes
             sozinhos são parecidos demais pra distinguir de relance. -->
        <div class="space-y-0.5 border-t px-2 py-3">
            <div class="flex items-center justify-between px-2.5 pb-1">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Escopos</p>
                <button type="button" @click="$emit('create-list')"
                        :disabled="(lists.custom?.length ?? 0) >= MAX_CUSTOM_LISTS"
                        :title="(lists.custom?.length ?? 0) >= MAX_CUSTOM_LISTS ? `Limite de ${MAX_CUSTOM_LISTS} escopos personalizados atingido` : 'Novo escopo'"
                        class="rounded-md p-0.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent">
                    <PlusIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <div v-for="key in ['mine', 'team']" :key="key"
                 class="group/scope flex items-center rounded-lg transition-colors"
                 :class="scope === key ? 'bg-white shadow-sm ring-1 ring-slate-200' : 'hover:bg-slate-100'">
                <button type="button" @click="$emit('update:scope', key)"
                        class="flex min-w-0 flex-1 items-center gap-2 px-2.5 py-1.5 text-sm font-medium"
                        :class="scope === key ? 'text-slate-900' : 'text-slate-600'">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: lists[key]?.color || '#94a3b8' }" />
                    <span class="truncate">{{ lists[key]?.name || (key === 'mine' ? 'Minhas tarefas' : 'Tarefas da equipe') }}</span>
                </button>
                <button type="button" @click="$emit('open-list-settings', lists[key])" title="Configurar lista"
                        class="mr-1 shrink-0 rounded-md p-1 text-slate-400 opacity-0 transition-opacity hover:bg-slate-200 hover:text-slate-600 group-hover/scope:opacity-100">
                    <Cog6ToothIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Escopos personalizados (Financeiro, Recepção...) — mesmo
                 padrão visual dos fixos; engrenagem só aparece pra quem criou
                 (é quem pode editar/excluir, ver TaskListController). -->
            <div v-for="custom in lists.custom" :key="custom.id"
                 class="group/scope flex items-center rounded-lg transition-colors"
                 :class="String(scope) === String(custom.id) ? 'bg-white shadow-sm ring-1 ring-slate-200' : 'hover:bg-slate-100'">
                <button type="button" @click="$emit('update:scope', String(custom.id))"
                        class="flex min-w-0 flex-1 items-center gap-2 px-2.5 py-1.5 text-sm font-medium"
                        :class="String(scope) === String(custom.id) ? 'text-slate-900' : 'text-slate-600'">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: custom.color }" />
                    <span class="truncate">{{ custom.name }}</span>
                </button>
                <button v-if="custom.is_owner" type="button" @click="$emit('open-list-settings', custom)" title="Configurar lista"
                        class="mr-1 shrink-0 rounded-md p-1 text-slate-400 opacity-0 transition-opacity hover:bg-slate-200 hover:text-slate-600 group-hover/scope:opacity-100">
                    <Cog6ToothIcon class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </aside>
</template>
