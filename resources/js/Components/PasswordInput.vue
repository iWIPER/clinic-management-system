<template>
    <div class="relative">
        <input
            :id="id"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :type="visible ? 'text' : 'password'"
            :autocomplete="autocomplete"
            :required="required"
            class="w-full border border-gray-300 rounded-md pl-3 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <button
            type="button"
            @click="visible = !visible"
            :aria-label="visible ? 'Ocultar senha' : 'Mostrar senha'"
            :aria-pressed="visible"
            :title="visible ? 'Ocultar senha' : 'Mostrar senha'"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:text-blue-600"
            tabindex="0"
        >
            <EyeSlashIcon v-if="visible" class="h-5 w-5" aria-hidden="true" />
            <EyeIcon v-else class="h-5 w-5" aria-hidden="true" />
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: undefined },
    autocomplete: { type: String, default: 'current-password' },
    required: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const visible = ref(false)
</script>
