<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ clinic: Object });

const form = useForm({
    invites: [
        { email: '', role: 'professional' },
    ],
});

const addInvite = () => {
    form.invites.push({ email: '', role: 'staff' });
};

const remove = (index) => {
    form.invites.splice(index, 1);
};

const submit = () => {
    form.post(route('onboarding.invite-team'));
};
</script>

<template>
    <div class="max-w-2xl mx-auto px-4">
        <h1 class="text-3xl font-semibold">Convide sua equipe</h1>
        <p class="text-slate-600 mb-6">Clínica: <strong>{{ clinic?.name }}</strong></p>

        <form @submit.prevent="submit" class="bg-white border rounded-2xl p-8 space-y-4">
            <div v-for="(invite, index) in form.invites" :key="index" class="flex gap-3 items-start border p-4 rounded-xl">
                <div class="flex-1">
                    <label class="text-xs text-slate-500">Email</label>
                    <input v-model="invite.email" type="email" class="w-full border p-2 rounded" placeholder="doutor@clinica.com" required />
                    <InputError :message="form.errors[`invites.${index}.email`]" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Papel</label>
                    <select v-model="invite.role" class="border p-2 rounded">
                        <option value="admin">Admin</option>
                        <option value="professional">Profissional</option>
                        <option value="staff">Staff / Recepção</option>
                    </select>
                    <InputError :message="form.errors[`invites.${index}.role`]" />
                </div>
                <button type="button" @click="remove(index)" class="text-red-500 px-2" v-if="form.invites.length > 1">×</button>
            </div>

            <button type="button" @click="addInvite" class="text-emerald-600 text-sm">+ Adicionar outro membro</button>

            <div class="pt-6">
                <button type="submit" class="bg-emerald-600 text-white px-8 py-3 rounded-lg" :disabled="form.processing">
                    Enviar Convites
                </button>

                <Link :href="route('dashboard')" class="ml-4 text-slate-500">Fazer isso depois</Link>
            </div>
        </form>

        <div class="mt-6 text-xs text-slate-500">
            Os convites expiram em 7 dias. O usuário receberá um link para aceitar.
        </div>
    </div>
</template>
