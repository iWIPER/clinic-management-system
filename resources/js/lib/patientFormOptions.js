// Listas de opções do formulário de paciente (Create.vue/Edit.vue). Um lugar
// só — ORIGEM_OPTIONS já vivia duplicada nos dois arquivos antes desta
// reorganização; consolidada aqui junto com as novas.
export const ORIGEM_OPTIONS = [
    { value: 'manual',    label: 'Manual' },
    { value: 'indicacao', label: 'Indicação' },
    { value: 'google',    label: 'Google' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'facebook',  label: 'Facebook' },
    { value: 'whatsapp',  label: 'WhatsApp' },
    { value: 'site',      label: 'Site' },
    { value: 'convenio',  label: 'Convênio' },
    { value: 'outro',     label: 'Outro' },
]

export const SEXO_OPTIONS = [
    { value: 'masculino',            label: 'Masculino' },
    { value: 'feminino',             label: 'Feminino' },
    { value: 'nao_binario',          label: 'Não binário' },
    { value: 'prefiro_nao_informar', label: 'Prefiro não informar' },
]

// Reaproveitada tanto pelo Responsável legal quanto pelo Titular do convênio
// — mesmo conceito de parentesco, não faz sentido duas listas iguais.
export const PARENTESCO_OPTIONS = [
    { value: 'pai',      label: 'Pai' },
    { value: 'mae',      label: 'Mãe' },
    { value: 'tutor',    label: 'Tutor' },
    { value: 'conjuge',  label: 'Cônjuge' },
    { value: 'filho',    label: 'Filho' },
    { value: 'outro',    label: 'Outro' },
]

// 'nao_enviar' continua sendo um valor válido de canal_lembrete (mesma coluna,
// mesma constraint no backend) — só deixou de ser uma opção do <select> no
// formulário, que agora representa esse estado com um checkbox separado
// ("Não enviar lembretes automáticos"). Ver Create.vue/Edit.vue.
export const CANAL_LEMBRETE_OPTIONS = [
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'sms',      label: 'SMS' },
    { value: 'email',    label: 'Email' },
]

export const TIPO_ATENDIMENTO_OPTIONS = [
    { value: 'particular', label: 'Particular' },
    { value: 'convenio',   label: 'Convênio' },
    { value: 'outro',      label: 'Outro' },
]
