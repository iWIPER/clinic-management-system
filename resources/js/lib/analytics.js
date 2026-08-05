// Camada fina sobre Google Analytics (gtag) / Meta Pixel (fbq).
// Não faz nada se os scripts não estiverem carregados (nenhum ID configurado
// ainda) — chamar trackEvent() em qualquer ambiente é sempre seguro.

const GA_EVENT_MAP = {
    landing_aberta:        'view_promotion',
    cadastro_iniciado:     'sign_up_start',
    cadastro_concluido:    'sign_up',
    trial_iniciado:        'trial_start',
    primeira_assinatura:   'purchase',
}

const FB_EVENT_MAP = {
    landing_aberta:        'ViewContent',
    cadastro_iniciado:     'InitiateCheckout',
    cadastro_concluido:    'CompleteRegistration',
    trial_iniciado:        'StartTrial',
    primeira_assinatura:   'Purchase',
}

export function trackEvent(name, params = {}) {
    try {
        if (typeof window.gtag === 'function') {
            window.gtag('event', GA_EVENT_MAP[name] || name, params)
        }
        if (typeof window.fbq === 'function') {
            window.fbq('track', FB_EVENT_MAP[name] || name, params)
        }
    } catch {
        // Nunca deixar uma falha de analytics quebrar a página.
    }
}
