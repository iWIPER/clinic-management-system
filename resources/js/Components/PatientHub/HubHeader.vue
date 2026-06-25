<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    patient: Object,
    badges: Array,
    tags: Array,
    birthday: Object,
});

const age = computed(() => {
    if (!props.patient.nascimento) return null;
    const birth = new Date(props.patient.nascimento);
    return Math.floor((Date.now() - birth.getTime()) / (365.25 * 24 * 60 * 60 * 1000));
});

const initials = computed(() => {
    const n = (props.patient.nome?.[0] ?? '') + (props.patient.sobrenome?.[0] ?? '');
    return n.toUpperCase() || '?';
});

const badgeColors = {
    green: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    red: 'bg-red-100 text-red-800 border-red-200',
    blue: 'bg-blue-100 text-blue-800 border-blue-200',
    amber: 'bg-amber-100 text-amber-800 border-amber-200',
    purple: 'bg-purple-100 text-purple-800 border-purple-200',
    slate: 'bg-slate-200 text-slate-700 border-slate-300',
    orange: 'bg-orange-100 text-orange-800 border-orange-200',
    pink: 'bg-pink-100 text-pink-800 border-pink-200',
};

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—';
</script>

<template>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-teal-900 px-6 py-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-2xl bg-white/10 border-2 border-white/20 flex items-center justify-center text-white text-xl font-bold shrink-0">
                        {{ initials }}
                    </div>
                    <div class="text-white">
                        <h1 class="text-2xl font-bold">{{ patient.nome }} {{ patient.sobrenome }}</h1>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-slate-300">
                            <span v-if="patient.doc_numero">CPF {{ patient.doc_numero }}</span>
                            <span v-if="age">{{ age }} anos</span>
                            <span v-if="patient.telefone">{{ patient.telefone }}</span>
                            <span v-if="patient.email">{{ patient.email }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('appointments.create') + '?patient_id=' + patient.id"
                          class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold rounded-lg transition">
                        + Agendar
                    </Link>
                    <Link :href="route('patients.edit', patient.id)" :cache-for="0"
                          class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm rounded-lg border border-white/20 transition">
                        Editar
                    </Link>
                    <a v-if="birthday?.whatsapp_url" :href="birthday.whatsapp_url" target="_blank" rel="noopener"
                       class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-semibold rounded-lg transition">
                        WhatsApp
                    </a>
                    <Link :href="route('patients.index')" class="px-4 py-2 text-slate-300 hover:text-white text-sm">
                        ← Voltar
                    </Link>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-b border-slate-100">
            <div class="flex flex-wrap gap-2">
                <span v-for="badge in badges" :key="badge.key"
                      class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full border"
                      :class="badgeColors[badge.color] ?? badgeColors.slate">
                    {{ badge.label }}
                </span>
                <span v-for="tag in tags" :key="tag"
                      class="text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                    {{ tag }}
                </span>
            </div>
            <p v-if="patient.nascimento" class="text-xs text-slate-400 mt-2">
                Nascimento: {{ fmtDate(patient.nascimento) }}
            </p>
        </div>
    </div>
</template>