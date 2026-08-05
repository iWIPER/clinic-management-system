<script setup>
import InputError from '@/Components/InputError.vue'
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { maskCpf, maskPhone, maskCro, isValidCpf, isValidCro } from '@/composables/useInputMasks'
import { compressImage } from '@/composables/useImageCompression'

const props = defineProps({
    open: Boolean,
    profile: Object,
    jobTitles: { type: Array, default: () => [] },
    canEditJobTitle: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'photo-removed'])

const BRAZILIAN_STATES = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
    'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
    'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
]

const previewUrl = ref(props.profile?.header?.avatar_url ?? null)
const photoInput = ref(null)
const clientErrors = ref({})

const form = useForm({
    name:        props.profile?.personal?.name ?? '',
    email:       props.profile?.personal?.email ?? '',
    phone:       props.profile?.personal?.phone ?? '',
    cpf:         props.profile?.personal?.cpf ?? '',
    birth_date:  props.profile?.personal?.birth_date ?? '',
    gender:      props.profile?.personal?.gender ?? '',
    cro:         props.profile?.personal?.cro ?? '',
    cro_uf:      props.profile?.personal?.cro_uf ?? '',
    specialty:   props.profile?.personal?.specialty ?? '',
    job_title:   props.profile?.personal?.job_title ?? '',
    photo:       null,
})

watch(() => props.open, (isOpen) => {
    if (!isOpen) return
    form.clearErrors()
    clientErrors.value = {}
    form.name        = props.profile?.personal?.name ?? ''
    form.email       = props.profile?.personal?.email ?? ''
    form.phone       = props.profile?.personal?.phone ?? ''
    form.cpf         = props.profile?.personal?.cpf ?? ''
    form.birth_date  = props.profile?.personal?.birth_date ?? ''
    form.gender      = props.profile?.personal?.gender ?? ''
    form.cro         = props.profile?.personal?.cro ?? ''
    form.cro_uf      = props.profile?.personal?.cro_uf ?? ''
    form.specialty   = props.profile?.personal?.specialty ?? ''
    form.job_title   = props.profile?.personal?.job_title ?? ''
    form.photo       = null
    previewUrl.value = props.profile?.header?.avatar_url ?? null
})

watch(() => props.profile?.header?.avatar_url, (val) => {
    if (!form.photo) previewUrl.value = val ?? null
})

const onPhoneInput = (e) => { form.phone = maskPhone(e.target.value) }
const onCpfInput   = (e) => { form.cpf = maskCpf(e.target.value) }
const onCroInput   = (e) => { form.cro = maskCro(e.target.value) }

const validateClient = () => {
    const errors = {}
    if (form.cpf && !isValidCpf(form.cpf)) errors.cpf = 'CPF inválido.'
    if (form.cro && !isValidCro(form.cro)) errors.cro = 'CRO deve ter entre 4 e 6 dígitos.'
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'Informe um e-mail válido.'
    }
    clientErrors.value = errors
    return Object.keys(errors).length === 0
}

const onPhotoChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const compressed = await compressImage(file)
    form.photo = compressed
    previewUrl.value = URL.createObjectURL(compressed)
}

const removePhotoPreview = () => {
    form.photo = null
    previewUrl.value = null
    if (photoInput.value) photoInput.value.value = ''
}

const removeStoredPhoto = () => {
    emit('photo-removed')
}

const submit = () => {
    if (!validateClient()) return

    // PHP só faz parsing de corpo multipart/form-data em requisições POST —
    // um PATCH/PUT nativo com multipart chega com $_POST/$_FILES vazios
    // (limitação do SAPI, não é bug do Laravel). Como este form sempre pode
    // enviar arquivo (foto), força-se POST real + _method=patch para o
    // Laravel rotear como PATCH, mas o corpo ser interpretado corretamente.
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
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

        <div class="relative w-full max-w-2xl max-h-[90vh] rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col">

          <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Editar Perfil</h2>
              <p class="text-xs text-slate-500 mt-0.5">Atualize seus dados pessoais e profissionais</p>
            </div>
            <button @click="close" type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <form @submit.prevent="submit" class="overflow-y-auto flex-1">
            <div class="p-6 space-y-6">

              <!-- Foto -->
              <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full border-2 border-slate-100 bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center">
                  <img v-if="previewUrl" :src="previewUrl" alt="Foto" class="w-full h-full object-cover" />
                  <span v-else class="text-lg font-semibold text-emerald-600">{{ profile?.header?.initials }}</span>
                </div>
                <div class="space-y-2">
                  <div class="flex flex-wrap gap-2">
                    <label class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 font-medium hover:bg-slate-50 transition-colors">
                      Alterar foto
                      <input ref="photoInput" type="file" accept="image/*" @change="onPhotoChange" class="sr-only" />
                    </label>
                    <button v-if="previewUrl" type="button" @click="removePhotoPreview"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                      Remover prévia
                    </button>
                    <button v-if="profile?.header?.avatar_url && !form.photo" type="button" @click="removeStoredPhoto"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                      Remover foto
                    </button>
                  </div>
                  <p class="text-xs text-slate-400">PNG ou JPG. Compressão automática ao enviar.</p>
                  <InputError :message="form.errors.photo" />
                </div>
              </div>

              <!-- Grid de campos -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-slate-700 mb-1">Nome completo</label>
                  <input v-model="form.name" type="text" placeholder="Ex: Dr. Ana Paula Silva"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.name" />
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                  <input v-model="form.email" type="email" placeholder="seu@email.com"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.email || clientErrors.email" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Telefone</label>
                  <input :value="form.phone" @input="onPhoneInput" type="tel" placeholder="(11) 99999-9999"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.phone" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">CPF</label>
                  <input :value="form.cpf" @input="onCpfInput" type="text" placeholder="000.000.000-00"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.cpf || clientErrors.cpf" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Data de nascimento</label>
                  <input v-model="form.birth_date" type="date"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.birth_date" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Gênero</label>
                  <select v-model="form.gender"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400">
                    <option value="">Selecione</option>
                    <option value="masculino">Masculino</option>
                    <option value="feminino">Feminino</option>
                    <option value="outro">Outro</option>
                    <option value="prefiro_nao_informar">Prefiro não informar</option>
                  </select>
                  <InputError :message="form.errors.gender" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">CRO</label>
                  <input :value="form.cro" @input="onCroInput" type="text" placeholder="Ex: 12345"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.cro || clientErrors.cro" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">UF do CRO</label>
                  <select v-model="form.cro_uf"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400">
                    <option value="">UF</option>
                    <option v-for="uf in BRAZILIAN_STATES" :key="uf" :value="uf">{{ uf }}</option>
                  </select>
                  <InputError :message="form.errors.cro_uf" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Especialidade</label>
                  <input v-model="form.specialty" type="text" placeholder="Ex: Ortodontia"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" />
                  <InputError :message="form.errors.specialty" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Cargo na clínica</label>
                  <select v-if="canEditJobTitle" v-model="form.job_title"
                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400">
                    <option value="">Selecione</option>
                    <option v-for="title in jobTitles" :key="title" :value="title">{{ title }}</option>
                  </select>
                  <template v-else>
                    <input :value="form.job_title || 'Não definido'" type="text" disabled
                           class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-500 cursor-not-allowed" />
                    <p class="text-xs text-slate-400 mt-1">Somente proprietário ou administrador pode alterar o cargo.</p>
                  </template>
                  <InputError :message="form.errors.job_title" />
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t px-6 py-4 shrink-0">
              <button type="button" @click="close"
                      class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 transition-colors">
                Cancelar
              </button>
              <button type="submit" :disabled="form.processing"
                      class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 transition-colors">
                {{ form.processing ? 'Salvando…' : 'Salvar alterações' }}
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