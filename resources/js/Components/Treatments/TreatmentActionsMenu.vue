<script setup>
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'

const props = defineProps({
    treatment: { type: Object, required: true },
})

const emit = defineEmits(['edit', 'cost', 'finalize', 'delete', 'view', 'duplicate', 'history'])

const finalized = () => props.treatment.status === 'concluido'
</script>

<template>
<NavbarDropdown align="right" width="w-48">
    <template #trigger>
        <button type="button" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
            <svg class="w-4.5 h-4.5" width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 6a2 2 0 100-4 2 2 0 000 4zM10 12a2 2 0 100-4 2 2 0 000 4zM10 18a2 2 0 100-4 2 2 0 000 4z" />
            </svg>
        </button>
    </template>

    <template #default="{ close }">
        <template v-if="!finalized()">
            <NavbarDropdownItem as="button" @click="emit('edit'); close()">Editar</NavbarDropdownItem>
            <NavbarDropdownItem as="button" @click="emit('cost'); close()">Alterar custo</NavbarDropdownItem>
            <NavbarDropdownItem as="button" @click="emit('finalize'); close()">Finalizar tratamento</NavbarDropdownItem>
            <NavbarDropdownItem as="button" danger @click="emit('delete'); close()">Excluir</NavbarDropdownItem>
        </template>
        <template v-else>
            <NavbarDropdownItem as="button" @click="emit('view'); close()">Visualizar</NavbarDropdownItem>
            <NavbarDropdownItem as="button" @click="emit('duplicate'); close()">Duplicar tratamento</NavbarDropdownItem>
            <NavbarDropdownItem as="button" @click="emit('history'); close()">Histórico</NavbarDropdownItem>
        </template>
    </template>
</NavbarDropdown>
</template>
