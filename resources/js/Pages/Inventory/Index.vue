<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ items: Object, filters: Object });

const search = ref(props.filters?.search || '');

const doSearch = () => router.get(route('inventory.index'), { search: search.value });

const addStock = (item) => {
    const qty = prompt('Quantidade a adicionar?');
    if (qty) router.post(route('inventory.add-stock', item.id), { quantidade: qty });
};
</script>

<template>
    <AppLayout>
        <div class="flex justify-between mb-6">
            <h1 class="text-2xl font-semibold">Estoque Básico</h1>
            <Link :href="route('inventory.create')" class="bg-emerald-600 text-white px-4 py-2 rounded text-sm">+ Novo Item</Link>
        </div>

        <div class="mb-4">
            <input v-model="search" @keyup.enter="doSearch" placeholder="Buscar material..." class="border px-4 py-2 rounded w-80" />
        </div>

        <div class="bg-white border rounded-2xl overflow-hidden">
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
                            <button @click="addStock(item)" class="text-emerald-600">+ Entrada</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
