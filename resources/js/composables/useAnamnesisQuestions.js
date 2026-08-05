const METADATA_PATTERNS = [
    /^(TYPE|ALERT|CATEGORY|MODEL|SHOW_ON_PATIENT_CARD)\s*:/i,
    /^-?\s*Alerta\s*:/i,
    /^Pergunta\s+(Sim|Somente)/i,
    /^(Sem alerta|Com alerta)/i,
    /^(YES_NO_UNKNOWN_TEXT|YES_NO_UNKNOWN|YES_NO_TEXT|YES_NO|TEXT)$/i,
]

export function isRenderableQuestion(question) {
    const text = (question?.text || '').trim()
    if (!text) return false
    return !METADATA_PATTERNS.some(p => p.test(text))
}

export function filterRenderableQuestions(questions) {
    return (questions || []).filter(isRenderableQuestion)
}