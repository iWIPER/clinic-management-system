const CATEGORY_META = {
    'QUEIXA PRINCIPAL': {
        icon: '💬',
        description: 'Motivo da consulta e sintomas relatados pelo paciente.',
    },
    GERAL: {
        icon: '❤️',
        description: 'Informações gerais de saúde e bem-estar.',
    },
    'DOENÇAS SISTÊMICAS': {
        icon: '🩺',
        description: 'Condições médicas, medicações e fatores de risco.',
    },
    HISTÓRICO: {
        icon: '📋',
        description: 'Antecedentes e histórico de tratamentos.',
    },
    HÁBITOS: {
        icon: '🪥',
        description: 'Hábitos de higiene e estilo de vida.',
    },
    ODONTOLÓGICO: {
        icon: '🦷',
        description: 'Histórico e condições odontológicas.',
    },
    'EXAME CLÍNICO': {
        icon: '🔍',
        description: 'Achados e observações do exame clínico.',
    },
    COVID: {
        icon: '😷',
        description: 'Questionário relacionado à COVID-19.',
    },
    GESTAÇÃO: {
        icon: '🤰',
        description: 'Informações sobre gestação e amamentação.',
    },
    ESTÉTICA: {
        icon: '✨',
        description: 'Cuidados estéticos e harmonização facial.',
    },
    ORTODONTIA: {
        icon: '📐',
        description: 'Avaliação ortodôntica e oclusão.',
    },
    PEDIATRIA: {
        icon: '👶',
        description: 'Informações específicas para pacientes infantis.',
    },
}

export const CATEGORY_ORDER = Object.keys(CATEGORY_META)

export function categoryMeta(name) {
    const key = (name || 'GERAL').toUpperCase()
    return CATEGORY_META[key] || {
        icon: '📄',
        description: 'Perguntas desta seção da anamnese.',
    }
}

export function categorySlug(name) {
    return (name || 'geral')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '')
}

export function sortCategories(categories) {
    return [...categories].sort((a, b) => {
        if (a.sort_order != null && b.sort_order != null) {
            return a.sort_order - b.sort_order
        }
        const order = CATEGORY_ORDER
        const ai = order.indexOf(a.name?.toUpperCase?.() ?? '')
        const bi = order.indexOf(b.name?.toUpperCase?.() ?? '')
        return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi)
    })
}

export function groupQuestionsByCategory(questions) {
    const groups = {}
    for (const q of questions) {
        const cat = q.category || 'GERAL'
        if (!groups[cat]) groups[cat] = []
        groups[cat].push(q)
    }

    return sortCategories(
        Object.entries(groups).map(([name, items]) => ({ name, questions: items }))
    )
}