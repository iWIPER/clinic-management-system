<script setup>
/**
 * PermanentTeeth.vue
 *
 * O SVG (public/odontogram/permanent.svg) tem cada dente já agrupado em seu
 * próprio <g id="tooth-XX"> (32 grupos), com cada path de face já anotado
 * com `data-face="mesial|distal|vestibular|palatina|oclusal|..."` — a fonte
 * já vem estruturada, nenhuma inferência por geometria é necessária (ver
 * toothFaces.js). A pintura por face usa exatamente esses paths, nunca uma
 * forma desenhada por cima.
 *
 * Funcionamento:
 *   1. Carrega public/odontogram/permanent.svg via fetch e injeta via innerHTML.
 *   2. Usa group.getBBox() para obter a bounding box de cada dente —
 *      coordenadas no espaço de usuário do próprio SVG, independentes do
 *      viewBox (por isso o recorte de viewBox não exige tocar nos paths).
 *   3. Lê os paths de face de cada dente pelo atributo `data-face`
 *      (getToothFacePaths) — nenhum path é criado, só identificados os
 *      que já existem.
 *   4. Aplica a cor de status diretamente no fill dos paths (dente inteiro
 *      ou só o(s) path(s) da face selecionada).
 *   5. SVG overlay (mesmo viewBox) renderiza só seleção, hover, X de removido
 *      e o rótulo FDI — nenhuma pintura de face acontece nessa camada.
 */

import { ref, watch, onMounted, nextTick } from 'vue'
import { getToothFacePaths, ALL_FACES } from './toothFaces.js'
import { statusFromTreatments, RAW_TO_VISUAL } from './toothStatusPriority.js'

const props = defineProps({
    teethData:     { type: Object,  default: () => ({}) },
    // Mapa dente (FDI) → tratamentos ativos, no formato { status, faces }[].
    // Fonte de verdade para as cores/badges — ver App\Models\PatientTreatment::groupedByTooth().
    treatmentsByTooth: { type: Object, default: () => ({}) },
    // String = seleção única (uso normal, odontograma principal). Array =
    // multi-seleção (ver ToothFaceSelector.vue, seletor de dente no modal de
    // tratamento) — aceitar os dois formatos evita duplicar este componente
    // só pra suportar múltiplos dentes marcados ao mesmo tempo.
    selectedTooth: { type: [String, Array], default: null },
    hoveredTooth:  { type: String,  default: null },
    readonly:      { type: Boolean, default: false },
})
const emit = defineEmits(['tooth:click', 'tooth:hover', 'tooth:leave'])

/* ── Constantes ─────────────────────────────────────────────────────────── */
// Precisa ser IDÊNTICO ao viewBox declarado em public/odontogram/permanent.svg
// (veja o <svg viewBox="..."> do próprio arquivo). Overlay e base compartilham
// o mesmo sistema de coordenadas — se um dos dois for alterado, o outro
// precisa mudar junto, senão o overlay desalinha da arte (preserveAspectRatio
// passa a escalar/centralizar o overlay de forma diferente da base).
const SVG_X = 26
const SVG_Y = 32
const SVG_W = 959.026
const SVG_H = 494

// Linha do rótulo FDI: posição fixa por arcada (não a bbox de cada dente
// individualmente) para manter os círculos perfeitamente alinhados em uma
// única fileira reta, como no odontograma de referência. Calculado para
// ficar simetricamente distante da guia horizontal (y=282.5, a linha-guia real
// do SVG de origem) em ambos os lados, com um gap curto até a raiz do dente.
const LABEL_R  = 12
const LABEL_CY_UPPER = 253.5
const LABEL_CY_LOWER = 311.5

// Cores aplicadas diretamente nos paths da anatomia do dente. Só os 3 status
// de tratamento existem (ver PatientTreatment::STATUSES) — status clínico do
// dente (cariado/restaurado/...) não tem cor própria (ver plano de
// simplificação dos indicadores).
const STATUS_COLORS = {
    completed:   '#22c55e', // Finalizado
    in_progress: '#ef4444', // Em andamento
    future:      '#8b5cf6', // Futuro
}

const UPPER = ['18','17','16','15','14','13','12','11','21','22','23','24','25','26','27','28']
const LOWER = ['48','47','46','45','44','43','42','41','31','32','33','34','35','36','37','38']
const ALL   = [...UPPER, ...LOWER]

/* ── Estado Vue ─────────────────────────────────────────────────────────── */
const svgContainer = ref(null)
const overlayReady = ref(false)
const toothBBoxes  = ref({})   // { '18': { x, y, w, h, cx, cy }, ... }
const toothFacePaths = ref({}) // { '18': { M: [el], D: [el], V: [el], L: [el], O: [el] }, ... }

/* ── Estado DOM (não reativo — referências a elementos SVG) ─────────────── */
// SVGPathElement → { usesStyle, value } — "value" é o style ou fill original
const origFills = new Map()
const WHITE_FILLS = new Set(['#ffffff', '#fff', 'white', 'rgb(255,255,255)', 'rgb(255, 255, 255)'])
const isWhiteFill = v => WHITE_FILLS.has((v ?? '').trim().toLowerCase())

/* ── Helpers de status ──────────────────────────────────────────────────── */
const DEFAULTS = {
    status: 'saudavel', notes: '',
    removed: false, removed_at: null, removed_by: null, removal_reason: null,
}
const td = n => ({ ...DEFAULTS, ...props.teethData?.[n] })

// Todos os tratamentos do dente contribuem pro status vencedor — sem
// distinção por quantidade de face (ver statusFromTreatments/RAW_TO_VISUAL
// em toothStatusPriority.js, fonte única de prioridade).
const toothTreatments = n => props.treatmentsByTooth?.[n] ?? []
const toothStatus     = n => statusFromTreatments(toothTreatments(n))

const isRem = n => { const d = td(n); return d.removed || d.status === 'ausente' }
const hasNt = n => !!td(n).notes?.trim()
const isSel = n => Array.isArray(props.selectedTooth) ? props.selectedTooth.includes(n) : props.selectedTooth === n
const isHov = n => props.hoveredTooth  === n

// Uma face só é pintada se existir tratamento marcando-a com o MESMO status
// vencedor do dente inteiro — tratamento "perdedor" (status diferente do
// vencedor) nunca colore face nenhuma, mesmo tendo face(s) marcada(s). Sem
// tratamento vencedor tocando essa face, fica sem preenchimento (fill:none)
// e mostra a cor da coroa por trás (ver applyColors).
const faceColor = (n, face) => {
    const winner = toothStatus(n)
    if (!winner) return null
    const wins = toothTreatments(n).some(t => t.faces?.includes(face) && RAW_TO_VISUAL[t.status] === winner)
    return wins ? STATUS_COLORS[winner] : null
}

// Posição Y do rótulo FDI (entre as arcadas) — fileira reta por arcada
const lY = n => UPPER.includes(n) ? LABEL_CY_UPPER : LABEL_CY_LOWER

/* ── Handlers ───────────────────────────────────────────────────────────── */
const hc  = n => { if (!props.readonly) emit('tooth:click', n) }
const hov = (n, e) => emit('tooth:hover', n, e)
const unH = () => emit('tooth:leave')

/* ── Colorização: altera fill dos paths do grupo diretamente no DOM ──────── */
function paintPath(p, color) {
    const orig = origFills.get(p)
    // origFills só contém paths com fill branco (anatomia colorível do dente,
    // incluindo o path de centro inserido em runtime). Paths com fill:none ou
    // preto (contornos/acabamento) não estão no map → skip.
    if (!orig) return
    if (orig.usesStyle) {
        p.setAttribute('style', color
            ? orig.value.replace(/fill\s*:[^;]+/, `fill:${color}`)
            : orig.value // restaura #ffffff original
        )
    } else if (color) {
        p.setAttribute('fill', color)
    } else if (orig.value === null) {
        // Path não tinha atributo fill (herdava fill:none) — restaura removendo-o.
        p.removeAttribute('fill')
    } else {
        // Path usa o atributo fill="#ffffff" solto.
        p.setAttribute('fill', orig.value)
    }
}

function applyColors() {
    if (!overlayReady.value || !svgContainer.value) return

    for (const fdi of ALL) {
        const group = svgContainer.value.querySelector(`#tooth-${fdi}`)
        if (!group) continue

        // Passada 1: coroa + raiz sempre pintadas com o status vencedor do
        // dente inteiro (todos os tratamentos contribuem, sem exceção por
        // quantidade de face). Sem tratamento nenhum, volta ao branco original.
        const crownRootColor = STATUS_COLORS[toothStatus(fdi)] ?? null
        group.querySelectorAll('[data-part="crown"], [data-part="root"]').forEach(p => paintPath(p, crownRootColor))

        // Passada 2: por face — só pinta a face se algum tratamento que a
        // marca tiver o MESMO status vencedor (ver faceColor). Tratamento
        // perdedor não pinta nada; a face fica transparente, mostrando a cor
        // da coroa por trás.
        const facePaths = toothFacePaths.value[fdi]
        if (!facePaths) continue

        for (const letter of ALL_FACES) {
            const els = facePaths[letter]
            if (!els || !els.length) continue
            const color = faceColor(fdi, letter)
            if (color !== null) {
                els.forEach(el => paintPath(el, color))
            }
        }
    }
}

watch([() => props.teethData, () => props.treatmentsByTooth], applyColors, { deep: true })

/* ── Inicialização ──────────────────────────────────────────────────────── */
onMounted(async () => {
    try {
        const res = await fetch('/odontogram/permanent.svg')
        // Componente pode ter sido desmontado durante o fetch (ex: modal
        // fechado rapidamente) — svgContainer.value já é null nesse caso.
        if (!svgContainer.value) return
        if (!res.ok) throw new Error(`HTTP ${res.status}`)

        const svgText = await res.text()
        if (!svgContainer.value) return

        // Injeta SVG original integralmente (nenhum path modificado)
        svgContainer.value.innerHTML = svgText

        // Torna responsivo via CSS (não altera geometria) — o viewBox declarado
        // no arquivo é a página inteira do Inkscape (0 0 210 297, proporção
        // retrato), não o recorte da arte. Sobrescreve em runtime pro mesmo
        // recorte que a camada overlay já usa (SVG_X/Y/W/H) — sem isso as duas
        // camadas ficam com proporções diferentes e desalinham.
        const svgEl = svgContainer.value.querySelector('svg')
        if (svgEl) {
            svgEl.setAttribute('viewBox', `${SVG_X} ${SVG_Y} ${SVG_W} ${SVG_H}`)
            // O arquivo também declara width/height em mm (página inteira do
            // Inkscape) — isso por si só já define a proporção intrínseca do
            // elemento (independente do viewBox) pras regras de CSS height:auto
            // abaixo. Remove pra proporção vir só do viewBox recém-ajustado.
            svgEl.removeAttribute('width')
            svgEl.removeAttribute('height')
            svgEl.style.width   = '100%'
            svgEl.style.height  = 'auto'
            svgEl.style.display = 'block'

            // O Inkscape agrupa tudo num <g id="g1" transform="matrix(...)">
            // que converte as coordenadas do documento pro viewBox nativo
            // (0 0 210 297). SVG_X/Y/W/H acima já são calibrados nas coordenadas
            // ANTES dessa conversão (é o que group.getBBox() devolve mais abaixo)
            // — com o transform ainda aplicado, o desenho fica escalado duas
            // vezes (bem menor e fora de posição). Remove esse transform depois
            // de trocar o viewBox, senão a arte não alinha com o overlay.
            const transformedGroup = svgEl.querySelector('[transform]')
            if (transformedGroup) transformedGroup.removeAttribute('transform')
        }

        await nextTick()
        if (!svgContainer.value) return

        // Salva o fill original dos paths de anatomia colorível. Aceita path com
        // fill branco explícito (style="fill:#ffffff" ou fill="#ffffff") e path
        // sem atributo fill algum (herda fill:none da raiz do SVG, convenção de
        // line-art) — sem tratamento o path some (value: null → removeAttribute).
        svgContainer.value.querySelectorAll('path').forEach(p => {
            const style = p.getAttribute('style') ?? ''
            const styleFill = style.match(/(?:^|;)\s*fill\s*:\s*([^;]+)/)?.[1]
            const bareFill = p.getAttribute('fill')
            if (isWhiteFill(styleFill)) {
                origFills.set(p, { usesStyle: true, value: style })
            } else if (isWhiteFill(bareFill)) {
                origFills.set(p, { usesStyle: false, value: bareFill })
            } else if (styleFill === undefined && bareFill === null) {
                origFills.set(p, { usesStyle: false, value: null })
            }
        })

        // Bounding boxes via getBBox() nos grupos — coordenadas já em screen space
        // (os paths têm transform individual; getBBox() na <g> aplica esses transforms)
        // — e leitura dos paths de face de cada dente pelo atributo data-face
        // (ver toothFaces.js).
        const bboxes = {}
        const facePathsByTooth = {}
        for (const fdi of ALL) {
            const group = svgContainer.value.querySelector(`#tooth-${fdi}`)
            if (!group) continue
            const bb = group.getBBox()
            if (bb.width > 0 && bb.height > 0) {
                bboxes[fdi] = {
                    x:  bb.x,
                    y:  bb.y,
                    w:  bb.width,
                    h:  bb.height,
                    cx: bb.x + bb.width  / 2,
                    cy: bb.y + bb.height / 2,
                }
            }

            facePathsByTooth[fdi] = getToothFacePaths(group, fdi)
        }

        toothBBoxes.value  = bboxes
        toothFacePaths.value = facePathsByTooth
        overlayReady.value = true

        // Aplica cores iniciais
        applyColors()

    } catch (e) {
        console.error('[PermanentTeeth] Erro ao carregar SVG:', e)
    }
})
</script>

<template>
<div class="relative w-full select-none">

    <!-- ── Camada visual: SVG original com grupos tooth-XX ───────────────── -->
    <div ref="svgContainer" style="line-height:0;pointer-events:none;filter:contrast(1.1)"></div>

    <!-- Carregando -->
    <div v-if="!overlayReady"
         class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <span class="text-slate-400 text-sm">A carregar odontograma…</span>
    </div>

    <!-- ── Overlay interativo: mesmo viewBox do SVG base — só seleção, hover,
         X de removido e rótulo FDI. Nenhuma pintura de face acontece aqui. ── -->
    <svg v-if="overlayReady"
         class="absolute inset-0 w-full h-full"
         :viewBox="`${SVG_X} ${SVG_Y} ${SVG_W} ${SVG_H}`"
         xmlns="http://www.w3.org/2000/svg"
         style="overflow:visible;pointer-events:all">

        <g v-for="fdi in ALL" :key="fdi">
        <template v-if="toothBBoxes[fdi]">

            <!-- Anel de seleção -->
            <rect v-if="isSel(fdi)"
                  :x="toothBBoxes[fdi].x - 5"     :y="toothBBoxes[fdi].y - 5"
                  :width="toothBBoxes[fdi].w + 10" :height="toothBBoxes[fdi].h + 10"
                  rx="9" fill="none"
                  stroke="#0d9488" stroke-width="2.5" stroke-dasharray="6 3"
                  style="pointer-events:none" />

            <!-- Hover: borda discreta + sombra suave -->
            <rect v-else-if="isHov(fdi)"
                  :x="toothBBoxes[fdi].x - 2"    :y="toothBBoxes[fdi].y - 2"
                  :width="toothBBoxes[fdi].w + 4" :height="toothBBoxes[fdi].h + 4"
                  rx="6" fill="none"
                  stroke="#94a3b8" stroke-width="2"
                  style="pointer-events:none;filter:drop-shadow(0 1px 4px rgba(0,0,0,0.13))" />

            <!-- X de dente removido -->
            <template v-if="isRem(fdi)">
                <line :x1="toothBBoxes[fdi].x + 8"
                      :y1="toothBBoxes[fdi].y + 8"
                      :x2="toothBBoxes[fdi].x + toothBBoxes[fdi].w - 8"
                      :y2="toothBBoxes[fdi].y + toothBBoxes[fdi].h - 8"
                      stroke="#dc2626" stroke-width="6.5" stroke-linecap="round"
                      style="pointer-events:none" />
                <line :x1="toothBBoxes[fdi].x + toothBBoxes[fdi].w - 8"
                      :y1="toothBBoxes[fdi].y + 8"
                      :x2="toothBBoxes[fdi].x + 8"
                      :y2="toothBBoxes[fdi].y + toothBBoxes[fdi].h - 8"
                      stroke="#dc2626" stroke-width="6.5" stroke-linecap="round"
                      style="pointer-events:none" />
            </template>

            <!-- Indicador de notas (ponto âmbar) -->
            <circle v-if="hasNt(fdi) && !isRem(fdi)"
                    :cx="toothBBoxes[fdi].x + 9" :cy="toothBBoxes[fdi].y + 9"
                    r="5" fill="#f59e0b" stroke="white" stroke-width="1"
                    style="pointer-events:none" />

            <!-- Rótulo FDI: círculo + número, alinhado em fileira reta por arcada -->
            <circle :cx="toothBBoxes[fdi].cx" :cy="lY(fdi)" :r="LABEL_R"
                    fill="white"
                    :stroke="isSel(fdi) ? '#0d9488' : '#cbd5e1'"
                    stroke-width="1"
                    style="pointer-events:none" />
            <text :x="toothBBoxes[fdi].cx" :y="lY(fdi)"
                  text-anchor="middle" dominant-baseline="central"
                  font-family="system-ui,-apple-system,sans-serif"
                  font-size="16" font-weight="600"
                  :fill="isSel(fdi) ? '#0d9488' : '#64748b'"
                  style="pointer-events:none">{{ fdi }}</text>

            <!-- Área de hit transparente (captura click/hover) -->
            <rect :x="toothBBoxes[fdi].x"    :y="toothBBoxes[fdi].y"
                  :width="toothBBoxes[fdi].w" :height="toothBBoxes[fdi].h"
                  fill="transparent"
                  :style="!readonly ? 'cursor:pointer' : ''"
                  @click="hc(fdi)"
                  @mouseenter="hov(fdi, $event)"
                  @mouseleave="unH()" />

        </template>
        </g>
    </svg>
</div>
</template>
