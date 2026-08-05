<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    invite: { type: Object, required: true },
});

const toast = useToast();
const acting = ref(false);
const copied = ref(false);

// BRD PATIENT_INVITATIONS_BRD.md §9 — tabela status × kind × progresso
const BADGE_MAP = {
    gerado:                { cadastro: ['🟠', 'Cadastro pendente'],                atualizacao: ['🔵', 'Atualização cadastral enviada'] },
    enviado:                { cadastro: ['🟠', 'Cadastro pendente'],                atualizacao: ['🔵', 'Atualização cadastral enviada'] },
    visualizado:            { cadastro: ['🟡', 'Em preenchimento'],                 atualizacao: ['🔵', 'Atualização em preenchimento'] },
    em_preenchimento:       { cadastro: ['🟡', 'Em preenchimento'],                 atualizacao: ['🔵', 'Atualização em preenchimento'] },
    aguardando_conclusao:   { cadastro: ['🟡', 'Aguardando conclusão (anamnese)'],  atualizacao: ['🔵', 'Aguardando conclusão (anamnese)'] },
    concluido:              { cadastro: ['🟢', 'Cadastro concluído'],               atualizacao: ['🟢', 'Atualização concluída'] },
    expirado:               { cadastro: ['🔴', 'Expirado'],                        atualizacao: ['🔴', 'Expirado'] },
    cancelado:              { cadastro: ['⚪', 'Cancelado'],                        atualizacao: ['⚪', 'Cancelado'] },
};

// "Em preenchimento" ganha percentual só quando há algo preenchido; os
// demais status usam o rótulo puro (ver BRD §9, nota sobre 0% no envio).
const SHOWS_PERCENT = ['gerado', 'enviado', 'visualizado', 'em_preenchimento'];

const badge = computed(() => {
    const { status, kind, progress } = props.invite;
    const isUpdate = kind === 'atualizacao';
    const [emoji, label] = BADGE_MAP[status]?.[isUpdate ? 'atualizacao' : 'cadastro'] ?? ['⚪', status];
    const suffix = SHOWS_PERCENT.includes(status) && progress > 0 ? ` (${progress}%)` : '';

    return { emoji, label: label + suffix };
});

const isActive = computed(() => !['concluido', 'expirado', 'cancelado'].includes(props.invite.status));

const fmtDateTime = (iso) => iso
    ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';

function reload() {
    router.reload({ only: ['latestPatientInvite'] });
}

async function copyLink(close) {
    try {
        await navigator.clipboard.writeText(props.invite.share.url);
        copied.value = true;
        toast.success('Link copiado!');
        setTimeout(() => { copied.value = false; }, 2000);
        await window.axios.post(route('patient-invites.log-event', props.invite.id), { action: 'link_copied' });
    } catch {
        toast.error('Não foi possível copiar o link.');
    }
    close();
}

async function resend(close) {
    acting.value = true;
    try {
        await window.axios.post(route('patient-invites.resend', props.invite.id));
        toast.success('Convite reenviado.');
        reload();
    } catch {
        toast.error('Não foi possível reenviar o convite.');
    } finally {
        acting.value = false;
        close();
    }
}

async function cancelInvite(close) {
    acting.value = true;
    try {
        await window.axios.post(route('patient-invites.cancel', props.invite.id));
        toast.success('Convite cancelado.');
        reload();
    } catch {
        toast.error('Não foi possível cancelar o convite.');
    } finally {
        acting.value = false;
        close();
    }
}

async function regenerate(close) {
    acting.value = true;
    try {
        await window.axios.post(route('patient-invites.regenerate', props.invite.id));
        toast.success('Novo convite gerado.');
        reload();
    } catch {
        toast.error('Não foi possível gerar um novo convite.');
    } finally {
        acting.value = false;
        close();
    }
}
</script>

<template>
    <NavbarDropdown align="left" width="w-80">
        <template #trigger>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 cursor-pointer">
                <span>{{ badge.emoji }}</span>
                <span>{{ badge.label }}</span>
                <span v-if="invite.not_responded_flagged_at" title="Sem resposta há 72h" class="text-amber-500">●</span>
            </span>
        </template>

        <template #default="{ close }">
            <div class="p-4 space-y-3">
                <div class="text-sm font-semibold text-slate-800">{{ badge.emoji }} {{ badge.label }}</div>

                <div class="space-y-1 text-xs text-slate-600">
                    <div class="flex justify-between"><span>Data de envio</span><span>{{ fmtDateTime(invite.created_at) }}</span></div>
                    <div class="flex justify-between"><span>Última abertura</span><span>{{ fmtDateTime(invite.opened_at) }}</span></div>
                </div>

                <div v-if="isActive" class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="copyLink(close)"
                            class="text-xs font-medium px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
                        {{ copied ? '✓ Copiado' : 'Copiar link' }}
                    </button>
                    <button type="button" @click="resend(close)" :disabled="acting"
                            class="text-xs font-medium px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-50">
                        Reenviar
                    </button>
                    <button type="button" @click="cancelInvite(close)" :disabled="acting"
                            class="text-xs font-medium px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50">
                        Cancelar
                    </button>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <button type="button" @click="regenerate(close)" :disabled="acting"
                            class="w-full text-xs font-medium px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                        Gerar novo convite
                    </button>
                </div>
            </div>
        </template>
    </NavbarDropdown>
</template>
