import { reactive, watch } from 'vue'

const KEY = 'clinicflow:odontogram:zoom'

export const ODONTOGRAM_ZOOM_MIN  = 0.7
export const ODONTOGRAM_ZOOM_MAX  = 1.5
export const ODONTOGRAM_ZOOM_STEP = 0.1
const DEFAULT_ZOOM = 1.0

const clamp = (v) => Math.round(Math.max(ODONTOGRAM_ZOOM_MIN, Math.min(ODONTOGRAM_ZOOM_MAX, v)) * 100) / 100

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(KEY)
        if (raw !== null) {
            const v = JSON.parse(raw)
            if (typeof v === 'number' && !Number.isNaN(v)) return clamp(v)
        }
    } catch {}
    return DEFAULT_ZOOM
}

const state = reactive({ zoom: loadFromStorage() })

watch(() => state.zoom, (v) => {
    try { localStorage.setItem(KEY, JSON.stringify(v)) } catch {}
})

export function useOdontogramZoom() {
    return state
}
