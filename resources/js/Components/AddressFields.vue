<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { useCepLookup, isCompleteCep } from '@/composables/useCepLookup';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    /** Objeto reativo do formulário (ex.: useForm). */
    model: {
        type: Object,
        required: true,
    },
    /** Mapeamento dos nomes dos campos no model. */
    fields: {
        type: Object,
        default: () => ({
            cep: 'cep',
            logradouro: 'logradouro',
            numero: 'numero',
            complemento: 'complemento',
            bairro: 'bairro',
            cidade: 'cidade',
            estado: 'estado',
        }),
    },
    title: {
        type: String,
        default: 'Endereço',
    },
    showTitle: {
        type: Boolean,
        default: true,
    },
    inputClass: {
        type: String,
        default: 'w-full border rounded-lg p-2.5',
    },
});

const numeroRef = ref(null);
const { loading, success, errorType, statusMessage, lookup, onCepInput } = useCepLookup();

const f = computed(() => props.fields);

const statusClass = computed(() => {
    if (loading.value) return 'text-slate-500';
    if (success.value) return 'text-emerald-600';
    if (errorType.value === 'not_found') return 'text-amber-700';
    if (errorType.value === 'unavailable') return 'text-slate-500';
    return 'text-slate-500';
});

const applyAddress = (address) => {
    if (!address) return;

    props.model[f.value.logradouro] = address.logradouro;
    props.model[f.value.bairro] = address.bairro;
    props.model[f.value.cidade] = address.cidade;
    props.model[f.value.estado] = address.estado;
};

const focusNumero = () => {
    nextTick(() => numeroRef.value?.focus());
};

const handleCepLookup = async () => {
    const cep = props.model[f.value.cep];
    if (!isCompleteCep(cep)) return;

    const address = await lookup(cep);
    if (address) {
        applyAddress(address);
        focusNumero();
    }
};

const setNumeroRef = (el) => {
    numeroRef.value = el;
};

onMounted(() => {
    const cepKey = f.value.cep;
    const current = props.model[cepKey];
    if (current && !String(current).includes('-')) {
        props.model[cepKey] = onCepInput(current);
    }
});

const onCepInputEvent = (event) => {
    const masked = onCepInput(event.target.value);
    props.model[f.value.cep] = masked;

    if (isCompleteCep(masked)) {
        handleCepLookup();
    }
};

const onCepBlur = () => {
    handleCepLookup();
};
</script>

<template>
    <div>
        <h3 v-if="showTitle" class="font-medium text-slate-700 mb-3">{{ title }}</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- CEP -->
            <div class="md:col-span-1">
                <label class="block text-sm mb-1">CEP</label>
                <div class="relative">
                    <input
                        :value="model[f.cep]"
                        type="text"
                        inputmode="numeric"
                        maxlength="9"
                        placeholder="00000-000"
                        :class="[inputClass, 'pr-9']"
                        @input="onCepInputEvent"
                        @blur="onCepBlur"
                    />
                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center justify-center w-5 h-5">
                        <svg v-if="loading"
                             class="w-4 h-4 animate-spin text-slate-400"
                             fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <svg v-else-if="success"
                             class="w-4 h-4 text-emerald-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                </div>
                <p v-if="statusMessage" class="mt-1 text-xs" :class="statusClass">
                    {{ statusMessage }}
                </p>
                <InputError :message="model.errors?.[f.cep]" />
            </div>

            <!-- Logradouro -->
            <div class="md:col-span-3">
                <label class="block text-sm mb-1">Logradouro</label>
                <input v-model="model[f.logradouro]" type="text" :class="inputClass" />
                <InputError :message="model.errors?.[f.logradouro]" />
            </div>

            <!-- Número -->
            <div>
                <label class="block text-sm mb-1">Número</label>
                <input
                    :ref="setNumeroRef"
                    v-model="model[f.numero]"
                    type="text"
                    :class="inputClass"
                />
                <InputError :message="model.errors?.[f.numero]" />
            </div>

            <!-- Complemento -->
            <div>
                <label class="block text-sm mb-1">Complemento</label>
                <input v-model="model[f.complemento]" type="text" :class="inputClass" />
                <InputError :message="model.errors?.[f.complemento]" />
            </div>

            <!-- Bairro -->
            <div>
                <label class="block text-sm mb-1">Bairro</label>
                <input v-model="model[f.bairro]" type="text" :class="inputClass" />
                <InputError :message="model.errors?.[f.bairro]" />
            </div>

            <!-- Cidade -->
            <div>
                <label class="block text-sm mb-1">Cidade</label>
                <input v-model="model[f.cidade]" type="text" :class="inputClass" />
                <InputError :message="model.errors?.[f.cidade]" />
            </div>

            <!-- UF -->
            <div>
                <label class="block text-sm mb-1">UF</label>
                <input v-model="model[f.estado]" type="text" maxlength="2" :class="inputClass" />
                <InputError :message="model.errors?.[f.estado]" />
            </div>
        </div>
    </div>
</template>