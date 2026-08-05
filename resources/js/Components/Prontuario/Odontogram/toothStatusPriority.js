// Prioridade única de "qual status vence" quando um dente tem múltiplos
// tratamentos simultâneos — usada tanto pela pintura do SVG (PermanentTeeth.vue
// e DeciduousTeeth.vue) quanto pelo badge/label do tooltip de hover
// (OdontogramChart.vue). Os três precisam do MESMO resultado pro mesmo dente,
// senão a pintura da coroa/raiz e o tooltip podem mostrar cores diferentes.
// Não duplicar esta lógica em nenhum outro lugar — importar daqui.

// Tradução do status bruto do tratamento (PatientTreatment::STATUSES, salvo
// no banco) → chave visual usada pelas cores/badges do odontograma.
export const RAW_TO_VISUAL = {
    em_andamento: 'in_progress',
    concluido:    'completed',
    futuro:       'future',
}

// Ordem única de prioridade entre status brutos — em_andamento sempre vence,
// futuro só vence se não houver nenhum tratamento em andamento nem concluído.
// Fonte única tanto pro status vencedor (statusFromTreatments) quanto pra
// ordenação da lista de tratamentos no tooltip (sortByStatusPriority) — não
// duplicar esta ordem em nenhum outro lugar.
const STATUS_ORDER = ['em_andamento', 'concluido', 'futuro']

/**
 * Tratamentos de UM dente ordenados por prioridade de status (mesma ordem de
 * STATUS_ORDER). Sort é estável — tratamentos com o MESMO status mantêm a
 * ordem relativa original entre si (ex: por data, como já vêm de
 * PatientTreatment::groupedByTooth()).
 *
 * @param {Array<{status: string}>} treatments
 * @returns {Array} Nova array ordenada (não muta o array recebido).
 */
export function sortByStatusPriority(treatments) {
    return [...treatments].sort((a, b) => {
        const pa = STATUS_ORDER.indexOf(a.status)
        const pb = STATUS_ORDER.indexOf(b.status)
        return (pa === -1 ? STATUS_ORDER.length : pa) - (pb === -1 ? STATUS_ORDER.length : pb)
    })
}

/**
 * Status vencedor entre os tratamentos de UM dente — o de maior prioridade
 * em STATUS_ORDER entre os presentes (ver sortByStatusPriority).
 *
 * @param {Array<{status: string}>} treatments Tratamentos (PatientTreatment)
 *   de um único dente — todos contribuem, independente de quantas faces cada
 *   um marca (não há mais distinção por quantidade de face).
 * @returns {'in_progress'|'completed'|'future'|null} Chave visual do
 *   vencedor, ou null se a lista estiver vazia.
 */
export function statusFromTreatments(treatments) {
    if (!treatments.length) return null
    return RAW_TO_VISUAL[sortByStatusPriority(treatments)[0].status] ?? null
}
