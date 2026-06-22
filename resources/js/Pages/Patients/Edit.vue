<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ patient: Object });

const form = useForm({
    ...props.patient,
});

const submit = () => {
    form.put(route('patients.update', props.patient.id));
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Editar Paciente</h1>
            <Link :href="route('patients.show', patient.id)" class="text-sm text-slate-500 hover:text-slate-700">Ver ficha →</Link>
        </div>

        <form @submit.prevent="submit" class="max-w-4xl bg-white p-8 rounded-2xl border space-y-6">
            <!-- Same form fields as Create, but prefilled -->
            <div>
                <h3 class="font-medium text-slate-700 mb-3">Identificação</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input v-model="form.nome" type="text" class="w-full border rounded-lg p-2.5" required />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Sobrenome *</label>
                        <input v-model="form.sobrenome" type="text" class="w-full border rounded-lg p-2.5" required />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Data de Nascimento</label>
                        <input v-model="form.nascimento" type="date" class="w-full border rounded-lg p-2.5" />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Status</label>
                        <select v-model="form.status" class="w-full border rounded-lg p-2.5">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="falecido">Falecido</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Repeat other sections abbreviated for brevity (same as Create) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Telefone</label>
                    <input v-model="form.telefone" type="text" class="w-full border rounded-lg p-2.5" />
                </div>
                <div>
                    <label class="block text-sm mb-1">E-mail</label>
                    <input v-model="form.email" type="email" class="w-full border rounded-lg p-2.5" />
                </div>
            </div>

            <div class="pt-4 flex gap-x-4">
                <button type="submit" class="bg-emerald-600 text-white px-8 py-2.5 rounded-lg font-medium" :disabled="form.processing">
                    Salvar Alterações
                </button>
                <Link :href="route('patients.index')" class="px-6 py-2.5 text-slate-600">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
