import { ref } from 'vue'

/**
 * Seleção de intervalo por arraste na grade da Agenda (estilo Excel),
 * compartilhada por Index.vue e Fullscreen.vue — evita duplicar a mesma
 * matemática de snap/threshold nos dois arquivos.
 *
 * mousedown registra o Y inicial; só vira "arraste" se o mouse se mover
 * além de DRAG_THRESHOLD_PX antes do mouseup (senão é um clique normal —
 * ver consumeDragFlag(), que o @click da coluna consulta pra saber se deve
 * ignorar o clique nativo que o navegador sempre dispara depois de um
 * mouseup, mesmo tendo havido arraste no meio). Sempre confinado a UMA
 * coluna — não faz sentido arrastar entre dias/cadeiras diferentes.
 *
 * @param {import('vue').Ref<number>} pxPerMin - pixels por minuto (zoom-aware)
 * @param {number} stepMinutes - granularidade do snap (15min, mesma da grade)
 * @param {(columnKey: any, startMinutesFromTop: number, durationMinutes: number) => void} onSelect
 */
export function useAgendaDragSelect({ pxPerMin, stepMinutes = 15, onSelect }) {
    const DRAG_THRESHOLD_PX = 4

    const dragging = ref(false)
    const dragColumnKey = ref(null)
    const dragStartY = ref(0)
    const dragCurrentY = ref(0)

    let containerTop = 0
    let pointerDownY = 0
    let justDragged = false

    function snap(minutes) {
        return Math.max(0, Math.round(minutes / stepMinutes) * stepMinutes)
    }

    function onPointerDown(e, columnKey) {
        if (e.button !== 0) return
        pointerDownY = e.clientY
        containerTop = e.currentTarget.getBoundingClientRect().top
        dragColumnKey.value = columnKey
        dragStartY.value = e.clientY - containerTop
        dragCurrentY.value = dragStartY.value
        window.addEventListener('mousemove', onPointerMove)
        window.addEventListener('mouseup', onPointerUp)
    }

    function onPointerMove(e) {
        if (!dragging.value && Math.abs(e.clientY - pointerDownY) > DRAG_THRESHOLD_PX) {
            dragging.value = true
        }
        if (dragging.value) {
            dragCurrentY.value = e.clientY - containerTop
        }
    }

    function onPointerUp() {
        window.removeEventListener('mousemove', onPointerMove)
        window.removeEventListener('mouseup', onPointerUp)

        if (dragging.value) {
            justDragged = true
            const top = Math.min(dragStartY.value, dragCurrentY.value)
            const bottom = Math.max(dragStartY.value, dragCurrentY.value)
            const startMinutes = snap(top / pxPerMin.value)
            const durationMinutes = Math.max(stepMinutes, snap((bottom - top) / pxPerMin.value))
            onSelect(dragColumnKey.value, startMinutes, durationMinutes)
        }

        dragging.value = false
        dragColumnKey.value = null
    }

    // Chamado pelo @click da coluna — true = esse clique é o "eco" nativo
    // de um arraste que já foi tratado no mouseup; quem chamou deve
    // ignorá-lo (não abrir o modal de novo com o horário do ponto de clique).
    function consumeDragFlag() {
        if (justDragged) {
            justDragged = false
            return true
        }
        return false
    }

    return { dragging, dragColumnKey, dragStartY, dragCurrentY, onPointerDown, consumeDragFlag }
}
