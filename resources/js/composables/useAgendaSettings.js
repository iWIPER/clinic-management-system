import { reactive, watch } from 'vue'

const KEY = 'clinicflow:agenda:settings'

const DEFAULTS = {
    // Visualização
    viewMode:            'week',   // 'week' | 'day'
    showSaturday:        false,
    showSunday:          false,
    hideCancelled:       false,

    // Zoom
    zoomLevel:           1.0,
    ctrlScrollZoom:      true,     // true = CTRL+Scroll | false = Scroll simples

    // Linha do horário atual
    showNowLine:         true,

    // Grades
    showSecondaryGrid:   true,     // linhas de meia hora

    // Horário de almoço
    showLunchBand:       false,
    lunchStart:          '12:00',
    lunchEnd:            '13:00',

    // Consultas passadas
    dimPastAppointments: false,

    // Pacientes aguardando
    showWaiting:         true,

    // Modo compacto (reduz padding interno dos cards)
    compactMode:         false,
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(KEY)
        if (raw) return { ...DEFAULTS, ...JSON.parse(raw) }
    } catch {}
    return { ...DEFAULTS }
}

const settings = reactive(loadFromStorage())

watch(settings, () => {
    try { localStorage.setItem(KEY, JSON.stringify({ ...settings })) } catch {}
}, { deep: true })

export function useAgendaSettings() {
    return settings
}
