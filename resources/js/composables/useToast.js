import { reactive } from 'vue'

// Singleton module-level — persiste entre navegações Inertia
const state = reactive({ items: [] })
let _nextId = 0

export function useToast() {
    const show = (message, type = 'success', duration = 4500) => {
        const id = ++_nextId
        state.items.push({ id, message, type })
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration)
        }
        return id
    }

    const dismiss = (id) => {
        const idx = state.items.findIndex(t => t.id === id)
        if (idx !== -1) state.items.splice(idx, 1)
    }

    return {
        toasts: state.items,
        show,
        dismiss,
        success: (msg, dur)  => show(msg, 'success', dur),
        error:   (msg, dur)  => show(msg, 'error', dur),
        warning: (msg, dur)  => show(msg, 'warning', dur),
        info:    (msg, dur)  => show(msg, 'info', dur),
    }
}
