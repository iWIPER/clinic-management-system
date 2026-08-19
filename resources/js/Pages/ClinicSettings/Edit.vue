<script setup>
import InputError from '@/Components/InputError.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/Navigation/PageHeader.vue'
import ClinicLogo from '@/Components/ClinicLogo.vue'
import SettingsTabs from '@/Components/ClinicSettings/SettingsTabs.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
    clinic:       Object,
    logoUrl:      String,
    defaultLogos: Array,
})

const page = usePage()

// ── Formulário principal (campos de texto + upload) ────────────────────────
const form = useForm({
    trade_name: props.clinic.trade_name || '',
    slogan:     props.clinic.slogan || '',
    logo:       null,
})

const previewUrl = ref(props.logoUrl)

// Atualiza preview quando Inertia recarrega os props (ex: após redirect)
watch(() => props.logoUrl, (val) => { previewUrl.value = val })

const onLogoChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    form.logo = file
    previewUrl.value = URL.createObjectURL(file)
}

const submit = () => {
    form.post(route('clinic-settings.update'), {
        forceFormData:  true,
        preserveScroll: true,
    })
}

const hasCustomLogo = computed(() => props.clinic.logo_type === 'custom' && props.clinic.logo_path)

// ── Modal de logos padrão ─────────────────────────────────────────────────
const modalOpen       = ref(false)
const selectedDefault = ref(props.clinic.default_logo ?? null)

const defaultLogoForm = useForm({
    logo_type:    'default',
    default_logo: props.clinic.default_logo ?? '',
})

const openModal = () => {
    selectedDefault.value = props.clinic.default_logo ?? null
    modalOpen.value = true
}

const closeModal = () => { modalOpen.value = false }

const selectDefault = (filename) => { selectedDefault.value = filename }

const saveDefaultLogo = () => {
    defaultLogoForm.default_logo = selectedDefault.value ?? ''
    defaultLogoForm.post(route('clinic-settings.update'), {
        forceFormData:  true,
        preserveScroll: true,
        onSuccess: ()  => { modalOpen.value = false },
    })
}

// ── Remover logo personalizado ─────────────────────────────────────────────
const showRemoveConfirm = ref(false)
const removeForm        = useForm({})

const removeLogo = () => {
    removeForm.delete(route('clinic-settings.logo.remove'), {
        preserveScroll: true,
        onSuccess: ()  => { showRemoveConfirm.value = false },
    })
}
</script>

<template>
<AppLayout>
    <template #pageHeader>
        <PageHeader title="Configurações da Clínica" description="Gerencie os dados, recursos e áreas da sua clínica." />
    </template>

    <SettingsTabs active="general" />

    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Geral</h2>
        <p class="text-sm text-slate-500 mt-1">Personalize a identidade visual usada nos PDFs de atendimento.</p>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl bg-white rounded-2xl border shadow-sm p-6 space-y-5">

      <!-- Nome fantasia -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nome fantasia</label>
        <input v-model="form.trade_name" type="text"
               class="w-full border rounded-lg px-3 py-2 text-sm"
               placeholder="Ex: Clínica Sorriso Perfeito" />
        <InputError :message="form.errors.trade_name" />
      </div>

      <!-- Slogan -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Slogan</label>
        <input v-model="form.slogan" type="text"
               class="w-full border rounded-lg px-3 py-2 text-sm"
               placeholder="Ex: Cuidando do seu sorriso com excelência" />
        <InputError :message="form.errors.slogan" />
      </div>

      <!-- Logo -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Logo da clínica</label>

        <div class="flex items-start gap-4">
          <!-- Preview -->
          <div class="w-24 h-24 rounded-xl border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
            <img v-if="previewUrl"
                 :src="previewUrl"
                 alt="Logo"
                 class="max-w-full max-h-full object-contain"
                 @error="previewUrl = '/images/brand/wildental-default.png'" />
            <ClinicLogo v-else img-class="max-w-full max-h-full object-contain p-1" />
          </div>

          <!-- Ações de upload -->
          <div class="flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Upload personalizado -->
              <label class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 font-medium hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Enviar arquivo
                <input type="file" accept="image/*" @change="onLogoChange" class="sr-only" />
              </label>

              <!-- Escolher logo padrão -->
              <button type="button" @click="openModal"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 font-medium hover:bg-emerald-100 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Logos padrão
              </button>
            </div>

            <p class="text-xs text-slate-400">PNG ou JPG, máx. 2 MB. Aparece na navbar e nos PDFs.</p>
            <InputError :message="form.errors.logo" />
          </div>
        </div>
      </div>

      <!-- Botões de ação -->
      <div class="pt-2 flex items-center gap-3 flex-wrap">
        <button type="submit" :disabled="form.processing"
                class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
          Salvar configurações
        </button>

        <!-- Remover logo personalizado -->
        <template v-if="hasCustomLogo">
          <template v-if="!showRemoveConfirm">
            <button type="button" @click="showRemoveConfirm = true"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
              Remover logo
            </button>
          </template>
          <template v-else>
            <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2">
              <span class="text-xs text-red-700">Deseja remover o logotipo personalizado?</span>
              <button type="button" @click="removeLogo" :disabled="removeForm.processing"
                      class="text-xs font-semibold text-red-700 hover:text-red-900 underline disabled:opacity-50">
                Confirmar
              </button>
              <button type="button" @click="showRemoveConfirm = false"
                      class="text-xs text-slate-500 hover:text-slate-700">
                Cancelar
              </button>
            </div>
          </template>
        </template>
      </div>
    </form>

  <!-- ── Modal de logos padrão ──────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modalOpen"
           class="fixed inset-0 z-50 flex items-center justify-center p-4"
           @click.self="closeModal">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal" />

        <!-- Painel -->
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

          <!-- Header -->
          <div class="flex items-center justify-between border-b px-6 py-4">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Escolher logo padrão</h2>
              <p class="text-xs text-slate-500 mt-0.5">Selecione um dos logotipos do Wildental</p>
            </div>
            <button @click="closeModal" type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Grid 2×3 -->
          <div class="p-6 grid grid-cols-2 gap-3">
            <button
              v-for="logo in defaultLogos"
              :key="logo.filename"
              type="button"
              @click="selectDefault(logo.filename)"
              class="relative group rounded-xl border-2 p-4 flex flex-col items-center gap-3 transition-all duration-150 cursor-pointer"
              :class="selectedDefault === logo.filename
                ? 'border-emerald-500 bg-emerald-50 shadow-md'
                : 'border-slate-200 bg-white hover:border-emerald-300 hover:shadow-sm'"
            >
              <!-- Check badge -->
              <div v-if="selectedDefault === logo.filename"
                   class="absolute top-2 right-2 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500">
                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
              </div>

              <!-- Logo image -->
              <div class="h-20 w-20 flex items-center justify-center">
                <img :src="logo.url" :alt="logo.label"
                     class="max-h-full max-w-full object-contain"
                     loading="lazy" />
              </div>

              <!-- Label -->
              <span class="text-xs font-medium text-slate-600">{{ logo.label }}</span>
            </button>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 border-t px-6 py-4">
            <button type="button" @click="closeModal"
                    class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 transition-colors">
              Cancelar
            </button>
            <button type="button"
                    :disabled="!selectedDefault || defaultLogoForm.processing"
                    @click="saveDefaultLogo"
                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 transition-colors">
              {{ defaultLogoForm.processing ? 'Salvando…' : 'Salvar logo' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</AppLayout>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from,
.modal-leave-to     { opacity: 0; }
</style>
