import { computed, ref, watch } from 'vue';

// 'nao_enviar' continua sendo um valor válido de canal_lembrete (mesma coluna,
// mesma constraint no backend) — só deixou de ser uma opção do <select> no
// formulário, que agora representa esse estado com um checkbox separado.
// Extraído de Create.vue/Edit.vue porque os dois formulários precisam
// permanecer com o mesmo comportamento; um único lugar garante isso em vez
// de depender de manter duas cópias em sincronia manualmente.
export function useCanalLembrete(form) {
    const naoEnviarLembretes = computed({
        get: () => form.canal_lembrete === 'nao_enviar',
        set: (checked) => { form.canal_lembrete = checked ? 'nao_enviar' : 'whatsapp'; },
    });
    const canalLembreteSelecionado = computed({
        get: () => (form.canal_lembrete === 'nao_enviar' ? 'whatsapp' : form.canal_lembrete),
        set: (value) => { form.canal_lembrete = value; },
    });

    return { naoEnviarLembretes, canalLembreteSelecionado };
}

// Atalho de UX, não persistido — só pré-preenche e esconde os campos de
// titular quando o titular do convênio é o próprio paciente.
export function useConvenioTitular(form) {
    const convenioTitularEhPaciente = ref(false);
    watch(convenioTitularEhPaciente, (checked) => {
        if (!checked) return;
        form.convenio_titular = `${form.nome} ${form.sobrenome}`.trim();
        form.convenio_titular_cpf = form.cpf;
        form.convenio_titular_parentesco = '';
    });

    return { convenioTitularEhPaciente };
}
