<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ items: Object, filters: Object });

const search = ref(props.filters?.search || '');

const doSearch = () => router.get(route('inventory.index'), { search: search.value });

// items já é o paginator cru do Laravel (current_page/last_page/total/
// per_page no nível raiz) — mesmo formato consumido direto por
// Admin/Users/Index.vue, não precisa de reshape no controller.
const goToPage = (page) => router.get(route('inventory.index'), { ...props.filters, page }, { preserveState: true, preserveScroll: true });

// Substitui o prompt() nativo (ruim em qualquer viewport, pior em touch —
// achado da auditoria) por um modal simples reaproveitando o Modal.vue global.
const stockModalItem = ref(null);
const stockQty = ref('');
const stockError = ref('');

const openAddStock = (item) => {
    stockModalItem.value = item;
    stockQty.value = '';
    stockError.value = '';
};

const submitAddStock = () => {
    const qty = Number(stockQty.value);
    if (!qty || qty < 1) {
        stockError.value = 'Informe uma quantidade válida.';
        return;
    }
    router.post(route('inventory.add-stock', stockModalItem.value.id), { quantidade: qty }, {
        preserveScroll: true,
        onSuccess: () => { stockModalItem.value = null; },
    });
};
</script>

<template>
    <AppLayout>
        <div class="flex flex-wrap gap-3 justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Estoque Básico</h1>
            <Link :href="route('inventory.create')" class="bg-emerald-600 text-white px-4 py-2 rounded text-sm">+ Novo Item</Link>
        </div>

        <div class="mb-4">
            <input v-model="search" @keyup.enter="doSearch" placeholder="Buscar material..." class="border px-4 py-2 rounded w-full sm:w-80" />
        </div>

        <!-- Desktop/tablet: tabela completa -->
        <div class="hidden lg:block bg-white border rounded-2xl overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-4 text-left">Nome / Marca</th>
                        <th class="p-4">Qtd</th>
                        <th class="p-4">Mínimo</th>
                        <th class="p-4">Validade</th>
                        <th class="p-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id" class="border-t">
                        <td class="p-4">{{ item.nome }} <span class="text-xs text-slate-400">{{ item.marca }}</span></td>
                        <td class="p-4 text-center font-medium" :class="{ 'text-red-600': item.quantidade < item.quantidade_minima }">{{ item.quantidade }}</td>
                        <td class="p-4 text-center">{{ item.quantidade_minima }}</td>
                        <td class="p-4 text-center">{{ item.validade || '—' }}</td>
                        <td class="p-4 text-right">
                            <button @click="openAddStock(item)" class="text-emerald-600">+ Entrada</button>
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td colspan="5" class="p-12 text-center text-slate-400">Nenhum item encontrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile/tablet estreito: cards -->
        <div class="lg:hidden space-y-3">
            <div v-for="item in items.data" :key="item.id" class="bg-white border rounded-2xl p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800 truncate">{{ item.nome }}</p>
                        <p v-if="item.marca" class="text-xs text-slate-400 mt-0.5 truncate">{{ item.marca }}</p>
                    </div>
                    <span class="shrink-0 text-lg font-semibold" :class="item.quantidade < item.quantidade_minima ? 'text-red-600' : 'text-slate-800'">
                        {{ item.quantidade }}
                    </span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Mínimo</dt>
                        <dd class="text-slate-700 mt-0.5">{{ item.quantidade_minima }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Validade</dt>
                        <dd class="text-slate-700 mt-0.5">{{ item.validade || '—' }}</dd>
                    </div>
                </dl>
                <button @click="openAddStock(item)"
                        class="mt-3 w-full rounded-lg border border-emerald-200 bg-emerald-50 py-2 text-sm font-medium text-emerald-700">
                    + Entrada
                </button>
            </div>

            <div v-if="items.data.length === 0" class="bg-white border rounded-2xl p-12 text-center text-slate-400">
                Nenhum item encontrado.
            </div>
        </div>

        <Pagination :pagination="items" @change="goToPage" />

        <Modal :show="!!stockModalItem" title="Adicionar ao estoque" max-width="max-w-sm" @close="stockModalItem = null">
            <form @submit.prevent="submitAddStock" class="p-5 space-y-3">
                <p class="text-sm text-slate-600">{{ stockModalItem?.nome }}</p>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Quantidade a adicionar</label>
                    <input v-model="stockQty" type="number" min="1" autofocus
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                    <p v-if="stockError" class="text-xs text-red-600 mt-1">{{ stockError }}</p>
                </div>
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="stockModalItem = null"
                            class="px-3 py-1.5 border rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="button" @click="submitAddStock"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700">
                        Adicionar
                    </button>
                </div>
            </template>
        </Modal>
    </AppLayout>
</template>
