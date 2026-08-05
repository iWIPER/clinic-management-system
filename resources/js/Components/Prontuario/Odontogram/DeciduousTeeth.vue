<script setup>
/**
 * DeciduousTeeth.vue
 *
 * O SVG (public/odontogram/deciduous.svg) tem cada dente já agrupado em seu
 * próprio <g id="tooth-XX"> (55, 54, ..., 75). Sua geometria vive no MESMO
 * sistema de coordenadas "cru" de public/odontogram/permanent.svg — os dois
 * arquivos-fonte compartilham o mesmo canvas de desenho (mesma altura de
 * arcada, mesma linha-guia horizontal em y=282.5) — por isso este componente
 * é estruturalmente idêntico a PermanentTeeth.vue, com CONTENT_WIDTH_PCT
 * como única diferença real: a arcada decíduo tem menos dentes, logo é mais
 * estreita, e não deve ser esticada para preencher a mesma largura total do
 * permanente (isso ampliaria os dentes) — ver comentário abaixo.
 *
 * Funcionamento (idêntico ao PermanentTeeth.vue — ver comentários lá):
 *   1. Carrega public/odontogram/deciduous.svg via fetch e injeta via innerHTML.
 *   2. Usa group.getBBox() para obter a bounding box de cada dente.
 *   3. Lê os paths de face de cada dente pelo atributo `data-face` já
 *      anotado no SVG (getToothFacePaths) — nenhum path é criado.
 *   4. Aplica a cor de status diretamente no fill dos paths (dente inteiro
 *      ou só o(s) path(s) da face selecionada) — nunca numa camada à parte.
 */

import { ref, watch, onMounted, nextTick } from 'vue'
import { getToothFacePaths, ALL_FACES } from './toothFaces.js'
import { statusFromTreatments, RAW_TO_VISUAL } from './toothStatusPriority.js'

const props = defineProps({
    teethData:     { type: Object,  default: () => ({}) },
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
// Precisa ser IDÊNTICO ao viewBox declarado em public/odontogram/deciduous.svg
// (veja o <svg viewBox="..."> do próprio arquivo). Overlay e base compartilham
// o mesmo sistema de coordenadas — se um dos dois for alterado, o outro
// precisa mudar junto, senão o overlay desalinha da arte. Como esse sistema
// de coordenadas é o mesmo (cru, sem matrix) de PermanentTeeth.vue, as
// constantes de rótulo (LABEL_R/LABEL_CY_*) também são as MESMAS — nenhum
// fator de escala é necessário aqui.
const SVG_X = 240.013
const SVG_Y = 32
const SVG_W = 551.3
const SVG_H = 494

const LABEL_R  = 12
const LABEL_CY_UPPER = 253.5
const LABEL_CY_LOWER = 311.5

// A arcada decíduo tem 10 dentes por fileira (permanente tem 16) — é
// naturalmente mais estreita no mesmo sistema de coordenadas. Se o SVG for
// esticado a 100% da mesma largura de contêiner do permanente, cada dente
// decíduo renderiza MAIOR que o correspondente permanente (exatamente o bug
// relatado). CONTENT_WIDTH_PCT é a fração da largura do permanente que essa
// arcada realmente ocupa — aplicado a um wrapper interno (ver template) para
// que a arte decíduo renderize na MESMA escala visual (mesmo px por unidade),
// só que naturalmente mais estreita, em vez de ampliada.
const PERMANENT_SVG_W = 959.026
const CONTENT_WIDTH_PCT = (SVG_W / PERMANENT_SVG_W) * 100

// Só 3 estados pintam — ver comentário equivalente em PermanentTeeth.vue.
const STATUS_COLORS = {
    completed:   '#22c55e',
    in_progress: '#ef4444',
    future:      '#8b5cf6',
}

const UPPER = ['55','54','53','52','51','61','62','63','64','65']
const LOWER = ['85','84','83','82','81','71','72','73','74','75']
const ALL   = [...UPPER, ...LOWER]

/* ── Estado Vue ─────────────────────────────────────────────────────────── */
const svgContainer = ref(null)
const overlayReady = ref(false)
const toothBBoxes  = ref({})
const toothFacePaths = ref({})

/* ── Estado DOM (não reativo) ───────────────────────────────────────────── */
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
// vencedor do dente inteiro — ver comentário equivalente em PermanentTeeth.vue.
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
    if (!orig) return
    if (orig.usesStyle) {
        p.setAttribute('style', color
            ? orig.value.replace(/fill\s*:[^;]+/, `fill:${color}`)
            : orig.value
        )
    } else if (color) {
        p.setAttribute('fill', color)
    } else if (orig.value === null) {
        p.removeAttribute('fill')
    } else {
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

        // Passada 2: por face — só pinta se algum tratamento que a marca tiver
        // o MESMO status vencedor (ver faceColor); senão fica transparente.
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
        const res = await fetch('/odontogram/deciduous.svg')
        // Componente pode ter sido desmontado durante o fetch (ex: troca
        // rápida de aba) — svgContainer.value já é null nesse caso.
        if (!svgContainer.value) return
        if (!res.ok) throw new Error(`HTTP ${res.status}`)

        const svgText = await res.text()
        if (!svgContainer.value) return

        // Injeta o SVG decíduo integralmente (nenhum path modificado aqui)
        svgContainer.value.innerHTML = svgText

        // Viewbox declarado no arquivo é a página inteira do Inkscape (proporção
        // retrato) — sobrescreve pro recorte real da arte (mesmo SVG_X/Y/W/H da
        // camada overlay), senão as duas camadas desalinham (ver PermanentTeeth.vue).
        const svgEl = svgContainer.value.querySelector('svg')
        if (svgEl) {
            svgEl.setAttribute('viewBox', `${SVG_X} ${SVG_Y} ${SVG_W} ${SVG_H}`)
            // Ver comentário equivalente em PermanentTeeth.vue — width/height em
            // mm no arquivo definem a proporção intrínseca por conta própria,
            // remove pra vir só do viewBox recém-ajustado.
            svgEl.removeAttribute('width')
            svgEl.removeAttribute('height')
            svgEl.style.width   = '100%'
            svgEl.style.height  = 'auto'
            svgEl.style.display = 'block'

            // Idem PermanentTeeth.vue: remove o transform do <g id="g1"> do
            // Inkscape — SVG_X/Y/W/H já são calibrados nas coordenadas antes
            // dessa conversão, com o transform ainda aplicado a arte fica
            // escalada duas vezes.
            const transformedGroup = svgEl.querySelector('[transform]')
            if (transformedGroup) transformedGroup.removeAttribute('transform')
        }

        await nextTick()
        if (!svgContainer.value) return

        // Salva o fill original dos paths de anatomia colorível — mesma
        // detecção tolerante do PermanentTeeth.vue (aceita fill branco
        // explícito ou ausência de atributo fill).
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

        // Bounding boxes via getBBox() nos grupos — coordenadas já em screen
        // space (mesmo sistema de coordenadas cru do permanent.svg) — e
        // leitura dos paths de face de cada dente pelo atributo data-face
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
        applyColors()

    } catch (e) {
        console.error('[DeciduousTeeth] Erro ao carregar SVG:', e)
    }
})
</script>

<template>
<div class="relative w-full select-none">

    <!-- Wrapper interno na largura real da arcada decíduo (ver comentário de
         CONTENT_WIDTH_PCT) — evita esticar o desenho para a largura total do
         permanente, o que ampliaria os dentes além do tamanho real. -->
    <div class="relative mx-auto" :style="{ width: CONTENT_WIDTH_PCT + '%' }">

        <!-- Camada visual: SVG original com grupos tooth-XX -->
        <div ref="svgContainer" style="line-height:0;pointer-events:none;filter:contrast(1.1)"></div>

        <!-- Carregando -->
        <div v-if="!overlayReady"
             class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <span class="text-slate-400 text-sm">A carregar odontograma decíduo…</span>
        </div>

        <!-- Overlay interativo: mesmo viewBox do SVG base — só seleção, hover,
             X de removido e rótulo FDI. Nenhuma pintura de face acontece aqui. -->
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
</div>
</template>
