// Lógica única de orientação de faces por dente — usada tanto pelo seletor de
// dente/face (Components/Treatments/ToothFaceSelector.vue) quanto pelo overlay
// de pintura por face no odontograma (PermanentTeeth.vue/DeciduousTeeth.vue).
// Não duplicar esta regra em nenhum outro lugar.

export const ALL_FACES = ['M', 'D', 'V', 'L', 'O']

export const FACE_LABELS = {
    M: 'Mesial',
    D: 'Distal',
    V: 'Vestibular / Bucal',
    L: 'Lingual / Palatina',
    O: 'Oclusal / Incisal',
}

/**
 * Quadrante visual 1-4 a partir do número FDI do dente.
 * Permanentes: 1x→1, 2x→2, 3x→3, 4x→4.
 * Decíduos:    5x→1, 6x→2, 7x→3, 8x→4 (mesma orientação anatômica).
 */
export function getQuadrant(tooth) {
    const first = parseInt(String(tooth).charAt(0), 10)
    if (!first) return null
    return first > 4 ? first - 4 : first
}

/**
 * Layout da cruz de faces para um dente: qual letra (M/D/V/L/O) ocupa cada
 * posição visual (topo/baixo/esquerda/direita/centro).
 *
 * Regra (ver plano): eixo vertical depende da arcada (superior → V em cima;
 * inferior → L/P em cima); eixo horizontal depende do lado (direito → M à
 * esquerda; esquerdo → D à esquerda). Oclusal sempre no centro.
 */
export function getFaceLayout(tooth) {
    const quadrant = getQuadrant(tooth)
    if (!quadrant) return null

    const isUpper = quadrant === 1 || quadrant === 2
    const isRightSide = quadrant === 1 || quadrant === 4

    return {
        top: isUpper ? 'V' : 'L',
        bottom: isUpper ? 'L' : 'V',
        left: isRightSide ? 'M' : 'D',
        right: isRightSide ? 'D' : 'M',
        center: 'O',
    }
}

/* ── Tradução código de face (M/D/V/L/O, salvo no tratamento) → data-face
 * do SVG ──────────────────────────────────────────────────────────────────
 *
 * Os arquivos-fonte (public/odontogram/permanent.svg e deciduous.svg) já
 * anotam cada path de face com um atributo `data-face` explícito — mas o
 * NOME usado depende do dente: "palatina" (superior) vs "lingual" (inferior)
 * pro mesmo código L; "incisal" (anterior) vs "oclusal" (posterior) pro
 * mesmo código O. M/D/V não variam. Por isso a tradução não pode ser um mapa
 * fixo código→nome — precisa do número do dente pra escolher o nome certo.
 *
 * Anterior/posterior: 2º dígito do FDI 1-3 (incisivos/canino) → anterior;
 * 4-8 (pré-molares/molares, ou molares decíduos) → posterior. Confirmado
 * batendo contra os dois SVGs: "incisal" só aparece nos dentes 11-13/21-23/
 * 31-33/41-43 (e decíduos equivalentes); "oclusal" no resto.
 */
const isAnteriorTooth = (tooth) => {
    const pos = parseInt(String(tooth).charAt(1), 10)
    return pos >= 1 && pos <= 3
}

const isUpperArch = (tooth) => {
    const quadrant = getQuadrant(tooth)
    return quadrant === 1 || quadrant === 2
}

const FACE_CODE_TO_SVG_NAME = {
    M: () => 'mesial',
    D: () => 'distal',
    V: () => 'vestibular',
    L: (tooth) => isUpperArch(tooth) ? 'palatina' : 'lingual',
    O: (tooth) => isAnteriorTooth(tooth) ? 'incisal' : 'oclusal',
}

/**
 * Código de face salvo no tratamento (M/D/V/L/O) + número FDI do dente →
 * nome de `data-face` correto pra ESSE dente específico. Única função que
 * decide esse nome — nunca hardcodar "palatina"/"lingual"/"oclusal"/
 * "incisal" em outro lugar.
 */
export function getSvgFaceName(faceCode, tooth) {
    const resolver = FACE_CODE_TO_SVG_NAME[faceCode]
    return resolver ? resolver(tooth) : null
}

/**
 * Recebe o <g id="tooth-XX"> de um dente e o próprio número do dente, e
 * devolve um mapa letra → elementos (`{ M: [el], D: [el], V: [el], L: [el],
 * O: [el] }`), montando o seletor `[data-face="..."]` certo pra cada face via
 * getSvgFaceName (nunca um nome fixo). Se uma face não existir no grupo (SVG
 * incompleto), a chave correspondente fica com array vazio — quem consome já
 * trata isso (não pinta nada pra face ausente, só o preenchimento de dente
 * inteiro continua funcionando).
 */
export function getToothFacePaths(group, tooth) {
    const byLetter = {}
    for (const code of ALL_FACES) {
        const svgName = getSvgFaceName(code, tooth)
        byLetter[code] = svgName ? Array.from(group.querySelectorAll(`[data-face="${svgName}"]`)) : []
    }
    return byLetter
}
