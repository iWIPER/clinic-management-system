<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    clinic: Object,
    logoUrl: String,
})

const form = useForm({
    trade_name: props.clinic.trade_name || '',
    slogan: props.clinic.slogan || '',
    logo: null,
})

const previewUrl = ref(props.logoUrl)

const onLogoChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    form.logo = file
    previewUrl.value = URL.createObjectURL(file)
}

const submit = () => {
    form.post(route('clinic-settings.update'), {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
<AppLayout>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-semibold mb-2">Configurações da Clínica</h1>
    <p class="text-sm text-slate-500 mb-6">Personalize a identidade visual usada nos PDFs de atendimento.</p>

    <form @submit.prevent="submit" class="bg-white rounded-2xl border p-6 space-y-5">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nome fantasia</label>
        <input v-model="form.trade_name" type="text"
               class="w-full border rounded-lg px-3 py-2 text-sm"
               placeholder="Ex: Clínica Sorriso Perfeito" />
        <p v-if="form.errors.trade_name" class="text-red-500 text-xs mt-1">{{ form.errors.trade_name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Slogan</label>
        <input v-model="form.slogan" type="text"
               class="w-full border rounded-lg px-3 py-2 text-sm"
               placeholder="Ex: Cuidando do seu sorriso com excelência" />
        <p v-if="form.errors.slogan" class="text-red-500 text-xs mt-1">{{ form.errors.slogan }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Logo da clínica</label>
        <div class="flex items-start gap-4">
          <div v-if="previewUrl"
               class="w-24 h-24 rounded-xl border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
            <img :src="previewUrl" alt="Logo" class="max-w-full max-h-full object-contain" />
          </div>
          <div class="flex-1">
            <input type="file" accept="image/*" @change="onLogoChange"
                   class="text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-medium hover:file:bg-emerald-100" />
            <p class="text-xs text-slate-400 mt-1">PNG ou JPG, máx. 2 MB. Aparece no cabeçalho do PDF.</p>
            <p v-if="form.errors.logo" class="text-red-500 text-xs mt-1">{{ form.errors.logo }}</p>
          </div>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit" :disabled="form.processing"
                class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-5 py-2.5 rounded-lg text-sm font-medium">
          Salvar configurações
        </button>
      </div>
    </form>
  </div>
</AppLayout>
</template>