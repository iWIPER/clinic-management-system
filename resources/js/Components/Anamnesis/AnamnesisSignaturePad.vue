<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const emit = defineEmits(['change'])

const canvasRef = ref(null)
let ctx = null
let drawing = false
let lastX = 0
let lastY = 0
let isEmpty = true

const getPos = (e, canvas) => {
    const rect = canvas.getBoundingClientRect()
    const scaleX = canvas.width / rect.width
    const scaleY = canvas.height / rect.height

    if (e.touches) {
        return [
            (e.touches[0].clientX - rect.left) * scaleX,
            (e.touches[0].clientY - rect.top) * scaleY,
        ]
    }
    return [
        (e.clientX - rect.left) * scaleX,
        (e.clientY - rect.top) * scaleY,
    ]
}

const startDraw = (e) => {
    e.preventDefault()
    drawing = true
    ;[lastX, lastY] = getPos(e, canvasRef.value)
}

const draw = (e) => {
    if (!drawing) return
    e.preventDefault()

    const [x, y] = getPos(e, canvasRef.value)

    ctx.beginPath()
    ctx.moveTo(lastX, lastY)
    ctx.lineTo(x, y)
    ctx.strokeStyle = '#1e293b'
    ctx.lineWidth = 2.5
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.stroke()

    ;[lastX, lastY] = [x, y]
    isEmpty = false
    emit('change', false)
}

const endDraw = () => {
    drawing = false
}

const clear = () => {
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
    isEmpty = true
    emit('change', true)
}

const toDataUrl = () => {
    if (isEmpty) return null
    return canvasRef.value.toDataURL('image/png')
}

defineExpose({ clear, toDataUrl, isEmpty: () => isEmpty })

onMounted(() => {
    const canvas = canvasRef.value
    ctx = canvas.getContext('2d')
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)

    canvas.addEventListener('mousedown', startDraw)
    canvas.addEventListener('mousemove', draw)
    canvas.addEventListener('mouseup', endDraw)
    canvas.addEventListener('mouseleave', endDraw)
    canvas.addEventListener('touchstart', startDraw, { passive: false })
    canvas.addEventListener('touchmove', draw, { passive: false })
    canvas.addEventListener('touchend', endDraw)
})

onUnmounted(() => {
    const canvas = canvasRef.value
    if (!canvas) return
    canvas.removeEventListener('mousedown', startDraw)
    canvas.removeEventListener('mousemove', draw)
    canvas.removeEventListener('mouseup', endDraw)
    canvas.removeEventListener('mouseleave', endDraw)
    canvas.removeEventListener('touchstart', startDraw)
    canvas.removeEventListener('touchmove', draw)
    canvas.removeEventListener('touchend', endDraw)
})
</script>

<template>
    <div class="relative rounded-xl border-2 border-dashed border-slate-200 bg-white overflow-hidden select-none touch-none">
        <canvas
            ref="canvasRef"
            width="560"
            height="200"
            class="w-full cursor-crosshair block"
            style="touch-action: none;"
        />
        <button
            type="button"
            class="absolute bottom-2 right-2 rounded-lg px-2.5 py-1 text-[11px] text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors border border-slate-200 bg-white/80"
            @click="clear"
        >Limpar</button>
        <p class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[12px] text-slate-300 pointer-events-none select-none transition-opacity duration-200"
           style="opacity: var(--hint-opacity, 1)"
        >Assine aqui</p>
    </div>
</template>
