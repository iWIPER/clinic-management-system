import { ref } from 'vue';

/** Cache em memória — válido durante a sessão do navegador. */
const sessionCache = new Map();

export function normalizeCep(value) {
    return String(value ?? '').replace(/\D/g, '').slice(0, 8);
}

export function formatCepMask(value) {
    const digits = normalizeCep(value);
    if (digits.length <= 5) return digits;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

export function isCompleteCep(value) {
    return normalizeCep(value).length === 8;
}

/**
 * Consulta CEP via ViaCEP com cache de sessão.
 *
 * @returns {Promise<{logradouro:string,bairro:string,cidade:string,estado:string}|null>}
 */
export async function fetchCepAddress(cep) {
    const normalized = normalizeCep(cep);

    if (normalized.length !== 8) {
        return null;
    }

    if (sessionCache.has(normalized)) {
        const cached = sessionCache.get(normalized);
        if (cached.error) {
            throw cached.error;
        }
        return cached.data;
    }

    const response = await fetch(`https://viacep.com.br/ws/${normalized}/json/`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        const error = { type: 'unavailable' };
        throw error;
    }

    const data = await response.json();

    if (data.erro) {
        const error = { type: 'not_found' };
        sessionCache.set(normalized, { error });
        throw error;
    }

    const result = {
        logradouro: data.logradouro ?? '',
        bairro: data.bairro ?? '',
        cidade: data.localidade ?? '',
        estado: data.uf ?? '',
    };

    sessionCache.set(normalized, { data: result });

    return result;
}

export function useCepLookup() {
    const loading = ref(false);
    const success = ref(false);
    const errorType = ref(null);
    const statusMessage = ref('');

    let successTimer = null;

    const clearSuccessState = () => {
        if (successTimer) {
            clearTimeout(successTimer);
            successTimer = null;
        }
        success.value = false;
    };

    const resetStatus = () => {
        clearSuccessState();
        errorType.value = null;
        statusMessage.value = '';
    };

    const showSuccess = () => {
        clearSuccessState();
        success.value = true;
        statusMessage.value = '✓ Endereço encontrado.';
        successTimer = setTimeout(() => {
            success.value = false;
            if (statusMessage.value === '✓ Endereço encontrado.') {
                statusMessage.value = '';
            }
        }, 2000);
    };

    const lookup = async (cep) => {
        const normalized = normalizeCep(cep);

        if (normalized.length !== 8) {
            resetStatus();
            return null;
        }

        if (loading.value) {
            return null;
        }

        const wasCached = sessionCache.has(normalized);

        if (!wasCached) {
            loading.value = true;
            errorType.value = null;
            statusMessage.value = 'Consultar CEP...';
        }

        try {
            const address = await fetchCepAddress(normalized);

            if (!wasCached) {
                showSuccess();
            }

            return address;
        } catch (err) {
            if (err?.type === 'not_found') {
                errorType.value = 'not_found';
                statusMessage.value = 'CEP não encontrado. Verifique o número informado.';
            } else {
                errorType.value = 'unavailable';
                statusMessage.value = 'Não foi possível consultar o CEP neste momento.';
            }

            return null;
        } finally {
            loading.value = false;
        }
    };

    const onCepInput = (value) => formatCepMask(value);

    return {
        loading,
        success,
        errorType,
        statusMessage,
        lookup,
        onCepInput,
        resetStatus,
    };
}