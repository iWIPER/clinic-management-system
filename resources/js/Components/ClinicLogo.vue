<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    clinic:   { type: Object, default: null },
    imgClass: { type: String, default: 'h-full w-full object-contain' },
})

const page = usePage()

const activeClinic = computed(() => props.clinic ?? page.props.currentClinic)
const src          = computed(() => activeClinic.value?.logo_url ?? '/images/brand/cliniflow-default.png')
const errored      = ref(false)

// Reseta o erro quando o logo muda (ex: após upload bem-sucedido)
watch(src, () => { errored.value = false })

const effectiveSrc = computed(() =>
    errored.value ? '/images/brand/cliniflow-default.png' : src.value
)
</script>

<template>
    <img
        :src="effectiveSrc"
        :alt="activeClinic?.name || 'ClinicFlow'"
        :class="imgClass"
        @error="errored = true"
    />
</template>
