<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import ScrollFadeX from '@/Components/UI/ScrollFadeX.vue'

const props = defineProps({
    groupedTreatments: Array,
    inactiveTreatments: Array,
    categories: Array,
    filters: Object,
    catalogCount: Number,
})

const search = ref(props.filters?.search || '')
const categoria = ref(props.filters?.categoria || '')

const applyFilters = () => {
    router.get(route('treatments.index'), { search: search.value, categoria: categoria.value }, { preserveState: true })
}

const fmtCurrency = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

const tipoLabel = (tipo) => {
    if (tipo === 'variacao') return 'Variação'
    if (tipo === 'grupo') return 'Grupo'
    return 'Procedimento'
}

const deactivate = (t) => {
    if (confirm(`Desativar "${t.nome}"? O histórico será preservado.`)) {
        router.post(route('treatments.deactivate', t.id), {}, { preserveScroll: true })
    }
}

const reactivate = (t) => {
    router.post(route('treatments.reactivate', t.id), {}, { preserveScroll: true })
}

const bookableCount = () => props.groupedTreatments?.reduce((sum, g) =>
    sum + g.items.filter(i => i.tipo !== 'grupo').length, 0) ?? 0
</script>

<template>
<AppLayout>
  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Catálogo de Tratamentos</h1>
      <p class="text-sm text-slate-500 mt-1">
        {{ bookableCount() }} procedimentos ativos · {{ catalogCount }} itens no catálogo
      </p>
    </div>
    <Link :href="route('treatments.create')"
          class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
      + Novo Procedimento
    </Link>
  </div>

  <!-- Filtros -->
  <div class="flex flex-wrap gap-2 mb-6">
    <input v-model="search" @keyup.enter="applyFilters"
           placeholder="Buscar procedimento..."
           class="border border-slate-200 rounded-lg px-4 py-2 w-72 text-sm bg-white" />
    <select v-model="categoria" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
      <option value="">Todas as categorias</option>
      <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
    </select>
    <button @click="applyFilters" class="px-4 py-2 border border-slate-200 rounded-lg text-sm bg-white hover:bg-slate-50">Filtrar</button>
  </div>

  <!-- Catálogo por categoria -->
  <div class="space-y-6 mb-10">
    <div v-for="group in groupedTreatments" :key="group.categoria"
         class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="flex items-center gap-3 px-5 py-3 border-b" :style="{ borderLeftColor: group.cor, borderLeftWidth: '4px' }">
        <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: group.cor }" />
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">{{ group.categoria }}</h2>
        <span class="text-[10px] text-slate-400 font-medium">{{ group.items.length }} itens</span>
      </div>

      <ScrollFadeX>
        <table class="min-w-[720px] w-full text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-500 uppercase">Procedimento</th>
              <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-500 uppercase">Tipo</th>
              <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-500 uppercase">Duração</th>
              <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-500 uppercase">Preço sugerido</th>
              <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-500 uppercase">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="t in group.items" :key="t.id"
                class="hover:bg-slate-50/60 transition-colors"
                :class="t.tipo === 'grupo' ? 'bg-slate-50/40' : ''">
              <td class="px-5 py-3">
                <div :class="t.parent_id ? 'pl-4 border-l-2 border-slate-200 ml-2' : ''">
                  <Link :href="route('treatments.show', t.id)"
                        class="font-semibold hover:underline"
                        :class="t.tipo === 'grupo' ? 'text-slate-600' : 'text-emerald-700 hover:text-emerald-900'"
                        :style="t.tipo !== 'grupo' ? { color: group.cor } : {}">
                    {{ t.nome }}
                  </Link>
                  <p v-if="t.descricao && t.tipo !== 'grupo'" class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">{{ t.descricao }}</p>
                  <p v-if="t.parent" class="text-[10px] text-slate-400 mt-0.5">↳ {{ t.parent.nome }}</p>
                </div>
              </td>
              <td class="px-5 py-3">
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                      :class="t.tipo === 'grupo' ? 'bg-slate-200 text-slate-600' : t.tipo === 'variacao' ? 'bg-violet-100 text-violet-700' : 'bg-emerald-100 text-emerald-700'">
                  {{ tipoLabel(t.tipo) }}
                </span>
              </td>
              <td class="px-5 py-3 text-slate-600">
                {{ t.tipo === 'grupo' ? '—' : t.duracao_padrao + ' min' }}
              </td>
              <td class="px-5 py-3 font-medium text-slate-800">
                {{ t.tipo === 'grupo' ? '—' : fmtCurrency(t.preco_base) }}
              </td>
              <td class="px-5 py-3 text-right space-x-2">
                <template v-if="t.tipo !== 'grupo'">
                  <Link :href="route('treatments.show', t.id)" class="text-xs text-slate-500 hover:text-slate-700">Detalhes</Link>
                  <Link :href="route('treatments.edit', t.id)" class="text-xs text-emerald-600">Editar</Link>
                  <button @click="deactivate(t)" class="text-xs text-amber-600">Desativar</button>
                </template>
                <template v-else>
                  <Link :href="route('treatments.show', t.id)" class="text-xs text-slate-500">Ver grupo</Link>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </ScrollFadeX>
    </div>

    <div v-if="!groupedTreatments?.length" class="bg-white rounded-2xl border p-12 text-center text-slate-400">
      Nenhum procedimento encontrado.
    </div>
  </div>

  <!-- Desativados -->
  <div v-if="inactiveTreatments.length" class="bg-slate-100 rounded-2xl border border-slate-300 overflow-hidden">
    <div class="px-5 py-3 bg-slate-200/70 border-b border-slate-300">
      <h2 class="text-xs font-bold text-slate-600 uppercase tracking-wide">Tratamentos desativados</h2>
      <p class="text-[10px] text-slate-500 mt-0.5">Preservados no histórico · não disponíveis para novos agendamentos</p>
    </div>
    <ScrollFadeX fade-from="from-slate-100">
      <table class="min-w-[720px] w-full text-sm text-slate-500">
        <thead class="bg-slate-200/40">
          <tr>
            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase">Nome</th>
            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase">Categoria</th>
            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase">Status</th>
            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase">Desativado em</th>
            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase">Responsável</th>
            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60">
          <tr v-for="t in inactiveTreatments" :key="t.id" class="hover:bg-slate-200/30">
            <td class="px-5 py-3">
              <Link :href="route('treatments.show', t.id)" class="font-medium text-slate-600 hover:underline">{{ t.nome }}</Link>
            </td>
            <td class="px-5 py-3">{{ t.categoria || '—' }}</td>
            <td class="px-5 py-3">
              <span class="text-[10px] font-semibold px-2 py-1 rounded-full bg-slate-300 text-slate-700">Desativado</span>
            </td>
            <td class="px-5 py-3">{{ fmtDateTime(t.deactivated_at) }}</td>
            <td class="px-5 py-3">{{ t.deactivated_by?.name || '—' }}</td>
            <td class="px-5 py-3 text-right space-x-2">
              <Link :href="route('treatments.show', t.id)" class="text-xs text-slate-500">Detalhes</Link>
              <button @click="reactivate(t)" class="text-xs text-teal-600 font-medium">Reativar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </ScrollFadeX>
  </div>
</AppLayout>
</template>