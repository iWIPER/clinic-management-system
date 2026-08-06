<script setup>
import NavbarDropdown from '@/Components/Navbar/NavbarDropdown.vue'
import NavbarDropdownItem from '@/Components/Navbar/NavbarDropdownItem.vue'

const props = defineProps({
    payment: { type: Object, required: true },
})

const emit = defineEmits(['edit', 'cancel', 'delete', 'plan', 'receipt'])

// Mesma trava em todo lugar: nada pode ter sido recebido ainda — ver
// PatientPaymentController::update()/cancel()/destroy().
const untouched = () => Number(props.payment.amount_paid) === 0
const hasReceipt = () => ['parcial', 'pago'].includes(props.payment.status)
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
        <NavbarDropdownItem v-if="untouched()" as="button" @click="emit('edit'); close()">Editar</NavbarDropdownItem>
        <NavbarDropdownItem v-if="untouched() && payment.status === 'pendente'" as="button" @click="emit('plan'); close()">Criar plano de pagamento</NavbarDropdownItem>
        <NavbarDropdownItem v-if="hasReceipt()" as="button" @click="emit('receipt'); close()">Emitir comprovante</NavbarDropdownItem>
        <NavbarDropdownItem v-if="untouched() && payment.status !== 'cancelado'" as="button" @click="emit('cancel'); close()">Cancelar parcela</NavbarDropdownItem>
        <NavbarDropdownItem v-if="untouched() && payment.status === 'pendente'" as="button" danger @click="emit('delete'); close()">Excluir</NavbarDropdownItem>
    </template>
</NavbarDropdown>
</template>
