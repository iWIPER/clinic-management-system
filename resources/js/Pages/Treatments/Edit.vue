<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ treatment: Object });

const form = useForm({
    ...props.treatment,
});

const submit = () => {
    form.put(route('treatments.update', props.treatment.id));
};
</script>

<template>
    <AppLayout>
        <h1 class="text-2xl font-semibold mb-6">Editar Tratamento</h1>

        <form @submit.prevent="submit" class="max-w-lg bg-white p-8 rounded-2xl border space-y-4">
            <div>
                <label class="block text-sm">Nome *</label>
                <input v-model="form.nome" type="text" class="w-full border rounded p-2" required />
            </div>

            <div>
                <label class="block text-sm">Especialidade</label>
                <input v-model="form.especialidade" type="text" class="w-full border rounded p-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm">Duração Padrão (min)</label>
                    <input v-model="form.duracao_padrao" type="number" class="w-full border rounded p-2" />
                </div>
                <div>
                    <label class="block text-sm">Preço Base (R$)</label>
                    <input v-model="form.preco_base" type="number" step="0.01" class="w-full border rounded p-2" />
                </div>
            </div>

            <div>
                <label class="block text-sm">Descrição</label>
                <textarea v-model="form.descricao" rows="3" class="w-full border rounded p-2"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" v-model="form.ativo" id="ativo" />
                <label for="ativo" class="text-sm">Ativo</label>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg" :disabled="form.processing">
                    Salvar
                </button>
                <Link :href="route('treatments.index')" class="px-4 py-2 text-slate-600">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
