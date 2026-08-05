<script setup>
import InputError from '@/Components/InputError.vue'
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    open: Boolean,
})

const emit = defineEmits(['close'])

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        form.reset()
        form.clearErrors()
    }
})

const submit = () => {
    form.patch(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            emit('close')
        },
    })
}

const close = () => emit('close')
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open"
           class="fixed inset-0 z-50 flex items-center justify-center p-4"
           @click.self="close">

        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="close" />

        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Alterar senha</h2>
              <p class="text-xs text-slate-500 mt-0.5">Use uma senha forte e exclusiva</p>
            </div>
            <button @click="close" type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <form @submit.prevent="submit" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Senha atual</label>
              <input v-model="form.current_password" type="password" autocomplete="current-password"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
              <InputError :message="form.errors.current_password" />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Nova senha</label>
              <input v-model="form.password" type="password" autocomplete="new-password"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
              <InputError :message="form.errors.password" />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar nova senha</label>
              <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
              <button type="button" @click="close"
                      class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 transition-colors">
                Cancelar
              </button>
              <button type="submit" :disabled="form.processing"
                      class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 transition-colors">
                {{ form.processing ? 'Salvando…' : 'Alterar senha' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from,
.modal-leave-to     { opacity: 0; }
</style>