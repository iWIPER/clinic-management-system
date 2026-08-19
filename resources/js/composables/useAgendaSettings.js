import { reactive, watch } from 'vue'

const KEY = 'wildental:agenda:settings'

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

    // Modo compacto (reduz padding interno dos cards)
    compactMode:         false,
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(KEY)
        if (raw) return { ...DEFAULTS, ...JSON.parse(raw) }
    } catch {}
    // Primeira visita (nada salvo ainda) — medido na R5: abaixo de 640px a
    // Semana não cabe com qualidade mesmo com o painel lateral recolhido
    // (só ~3 de 7 colunas visíveis), enquanto Dia cabe perfeitamente sem
    // rolagem horizontal nenhuma. Só define o PADRÃO inicial — o usuário
    // pode trocar pra Semana a qualquer momento (o toggle continua
    // acessível em qualquer largura) e a escolha fica salva a partir daí.
    const initialViewMode = (typeof window !== 'undefined' && window.innerWidth < 640) ? 'day' : 'week'
    return { ...DEFAULTS, viewMode: initialViewMode }
}

const settings = reactive(loadFromStorage())

watch(settings, () => {
    try { localStorage.setItem(KEY, JSON.stringify({ ...settings })) } catch {}
}, { deep: true })

export function useAgendaSettings() {
    return settings
}
