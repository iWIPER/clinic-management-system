import { IdentificationIcon, GlobeAltIcon } from '@heroicons/vue/24/outline'
import { maskCpf } from '@/composables/useInputMasks.js'

// Tabela de tipos de documento suportados, em ordem de prioridade quando mais
// de um estiver preenchido (CPF > RG > Passaporte). Usada tanto para o
// documento do próprio paciente quanto para o de um responsável legal —
// quem chama passa só os 3 campos correspondentes, com os nomes que
// existirem no seu contexto (ex: responsavel_legal_cpf vira `cpf` aqui).
// Adicionar um novo tipo (RNE, DNI, NIE...) é só acrescentar uma entrada
// aqui — nenhuma outra lógica, em nenhum dos usos, precisa mudar.
const DOCUMENT_TYPES = [
    { field: 'cpf', label: 'CPF', icon: IdentificationIcon, mask: maskCpf },
    { field: 'rg', label: 'RG', icon: IdentificationIcon, mask: null },
    { field: 'passaporte', label: 'Passaporte', icon: GlobeAltIcon, mask: null },
]

/**
 * @param {{ cpf?: string, rg?: string, passaporte?: string }} fields
 * @returns {{ icon: object, label: string|null, text: string, copyValue: string|null }}
 */
export function resolvePatientDocument(fields) {
    const type = DOCUMENT_TYPES.find((t) => fields[t.field])
    if (!type) return { icon: IdentificationIcon, label: null, text: 'Sem documento', copyValue: null }

    const value = type.mask ? type.mask(fields[type.field]) : fields[type.field]
    return { icon: type.icon, label: type.label, text: `${type.label} ${value}`, copyValue: value }
}
