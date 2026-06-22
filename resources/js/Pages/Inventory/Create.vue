<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    nome: '',
    marca: '',
    lote: '',
    validade: '',
    custo_unitario: 0,
    quantidade: 0,
    quantidade_minima: 5,
    local: '',
});

const submit = () => form.post(route('inventory.store'));
</script>

<template>
    <AppLayout>
        <h1 class="text-2xl font-semibold mb-6">Novo Item no Estoque</h1>
        <form @submit.prevent="submit" class="max-w-lg bg-white p-8 border rounded-2xl space-y-4">
            <div><label class="text-sm">Nome *</label><input v-model="form.nome" class="w-full border p-2 rounded" required /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-sm">Marca</label><input v-model="form.marca" class="w-full border p-2 rounded" /></div>
                <div><label class="text-sm">Lote</label><input v-model="form.lote" class="w-full border p-2 rounded" /></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-sm">Validade</label><input v-model="form.validade" type="date" class="w-full border p-2 rounded" /></div>
                <div><label class="text-sm">Custo Unit.</label><input v-model="form.custo_unitario" type="number" step="0.01" class="w-full border p-2 rounded" /></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="text-sm">Qtd Atual</label><input v-model="form.quantidade" type="number" class="w-full border p-2 rounded" /></div>
                <div><label class="text-sm">Qtd Mínima</label><input v-model="form.quantidade_minima" type="number" class="w-full border p-2 rounded" /></div>
                <div><label class="text-sm">Local</label><input v-model="form.local" class="w-full border p-2 rounded" /></div>
            </div>
            <div class="pt-4">
                <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded">Salvar</button>
                <Link :href="route('inventory.index')" class="ml-3 text-slate-600">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
