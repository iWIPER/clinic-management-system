<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProfileEditModal from '@/Components/Profile/ProfileEditModal.vue'
import PasswordChangeModal from '@/Components/Profile/PasswordChangeModal.vue'
import {
    displayValue,
    fmtDate,
    fmtDateTime,
    fmtRelative,
    statusLabel,
    statusClasses,
} from '@/composables/useProfileFormatters'
import { maskCpf, maskPhone } from '@/composables/useInputMasks'

const props = defineProps({
    profile: Object,
    mustVerifyEmail: Boolean,
    status: String,
})

const editModalOpen     = ref(false)
const passwordModalOpen = ref(false)

const openEditModal = () => { editModalOpen.value = true }
const closeEditModal = () => { editModalOpen.value = false }

const removePhoto = () => {
    router.delete(route('profile.photo.remove'), { preserveScroll: true })
}

const formatCpf = (value) => value ? maskCpf(value) : '—'
const formatPhone = (value) => value ? maskPhone(value) : '—'
const formatCro = (p) => {
    if (!p.cro) return '—'
    return p.cro_uf ? `CRO/${p.cro_uf} ${p.cro}` : p.cro
}

const statValue = (value) => (value === null || value === undefined ? '—' : value)

const personalFields = [
    { label: 'Nome completo',      value: () => displayValue(props.profile.personal.name) },
    { label: 'Email',              value: () => displayValue(props.profile.personal.email) },
    { label: 'Telefone',           value: () => formatPhone(props.profile.personal.phone) },
    { label: 'CPF',                value: () => formatCpf(props.profile.personal.cpf) },
    { label: 'Data de nascimento', value: () => props.profile.personal.birth_date ? fmtDate(props.profile.personal.birth_date) : '—' },
    { label: 'Gênero',             value: () => displayValue(props.profile.personal.gender_label) },
    { label: 'CRO',                value: () => formatCro(props.profile.personal) },
    { label: 'UF do CRO',          value: () => displayValue(props.profile.personal.cro_uf) },
    { label: 'Especialidade',      value: () => displayValue(props.profile.personal.specialty) },
    { label: 'Cargo na clínica',   value: () => displayValue(props.profile.personal.job_title) },
    { label: 'Status',             value: () => statusLabel(props.profile.personal.status), badge: true },
]

const historyItems = [
    { label: 'Conta criada',       value: () => fmtDateTime(props.profile.history.created_at) },
    { label: 'Última alteração',   value: () => fmtDateTime(props.profile.history.updated_at) },
    { label: 'Último login',       value: () => fmtDateTime(props.profile.history.last_login_at) },
    { label: 'Conta ativa há',     value: () => `${props.profile.history.active_days} dias` },
    { label: 'Perfil atualizado',  value: () => fmtRelative(props.profile.history.profile_updated_at) },
]

const statItems = [
    { label: 'Consultas realizadas',     value: () => statValue(props.profile.statistics.consultations) },
    { label: 'Pacientes atendidos',      value: () => statValue(props.profile.statistics.patients) },
    { label: 'Procedimentos registrados', value: () => statValue(props.profile.statistics.procedures) },
    { label: 'Tempo utilizando o sistema', value: () => props.profile.statistics.usage_days ? `${props.profile.statistics.usage_days} dias` : '—' },
    { label: 'Documentos emitidos',      value: () => statValue(props.profile.statistics.documents) },
    { label: 'Última atividade',         value: () => props.profile.statistics.last_activity ? fmtDateTime(props.profile.statistics.last_activity) : '—' },
]

const pref = props.profile.preferences
</script>

<template>
<AppLayout>
  <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6">

    <!-- Cabeçalho -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center gap-6">
        <div class="w-24 h-24 rounded-full border-2 border-slate-100 bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center shadow-sm">
          <img v-if="profile.header.avatar_url"
               :src="profile.header.avatar_url"
               :alt="profile.header.name"
               class="w-full h-full object-cover" />
          <span v-else class="text-2xl font-semibold text-emerald-600">{{ profile.header.initials }}</span>
        </div>

        <div class="flex-1 min-w-0 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ profile.header.name }}</h1>
              <p class="text-sm text-slate-500 mt-1">Central do Usuário</p>
            </div>
            <button type="button" @click="openEditModal"
                    class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              Editar Perfil
            </button>
          </div>

          <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-slate-400">Cargo</dt>
              <dd class="font-medium text-slate-800 mt-0.5 truncate">{{ displayValue(profile.header.job_title) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400">Clínica</dt>
              <dd class="font-medium text-slate-800 mt-0.5 truncate">{{ displayValue(profile.header.clinic_name) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400">Especialidade</dt>
              <dd class="font-medium text-slate-800 mt-0.5 truncate">{{ displayValue(profile.header.specialty) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400">CRO</dt>
              <dd class="font-medium text-slate-800 mt-0.5">{{ displayValue(profile.header.cro) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400">Status</dt>
              <dd class="mt-0.5">
                <span class="inline-flex text-xs px-2 py-0.5 rounded-full font-medium border"
                      :class="statusClasses(profile.header.status)">
                  {{ statusLabel(profile.header.status) }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400">Último acesso</dt>
              <dd class="font-medium text-slate-800 mt-0.5">{{ fmtDateTime(profile.header.last_login_at) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <p v-if="status" class="mt-4 text-sm text-emerald-600">{{ status }}</p>
      <div v-if="mustVerifyEmail && profile.must_verify_email"
           class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
        Seu email não foi verificado.
        <a href="/email/verification-notification" class="underline font-medium">Reenviar verificação</a>
      </div>
    </div>

    <!-- Grid 2 colunas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

      <!-- Coluna esquerda -->
      <div class="space-y-6">

        <!-- Card 1 — Dados Pessoais -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
          <h2 class="text-sm font-medium text-slate-700 mb-5">Dados Pessoais</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
            <div v-for="field in personalFields" :key="field.label">
              <dt class="text-xs text-slate-400 mb-0.5">{{ field.label }}</dt>
              <dd v-if="field.badge" class="mt-0.5">
                <span class="inline-flex text-xs px-2 py-0.5 rounded-full font-medium border"
                      :class="statusClasses(profile.personal.status)">
                  {{ field.value() }}
                </span>
              </dd>
              <dd v-else class="text-sm font-medium text-slate-800 mt-0.5">{{ field.value() }}</dd>
            </div>
          </dl>
        </div>

        <!-- Card 2 — Segurança -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
          <h2 class="text-sm font-medium text-slate-700 mb-5">Segurança</h2>

          <div class="space-y-4">
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Email utilizado para login</p>
              <p class="text-sm font-medium text-slate-800">{{ profile.personal.email }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Senha</p>
              <p class="text-sm font-medium text-slate-800 tracking-widest">••••••••</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 mt-5">
            <button type="button" @click="passwordModalOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
              Alterar senha
            </button>
            <button type="button" disabled
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-400 cursor-not-allowed"
                    title="Em breve">
              Sessões ativas
            </button>
          </div>

          <!-- Espaço futuro: 2FA -->
          <div class="mt-6 pt-5 border-t border-slate-100">
            <div class="flex items-center justify-between opacity-50">
              <div>
                <p class="text-xs font-medium text-slate-600">Autenticação em dois fatores</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Disponível em breve</p>
              </div>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-medium">Em breve</span>
            </div>
          </div>

          <!-- Expansões futuras -->
          <div class="mt-4 space-y-2 opacity-40">
            <p class="text-[10px] text-slate-400 uppercase tracking-wide font-medium">Em desenvolvimento</p>
            <div class="flex flex-wrap gap-2">
              <span class="text-[10px] px-2 py-1 rounded-md bg-slate-50 border border-slate-100 text-slate-500">Chaves de API</span>
              <span class="text-[10px] px-2 py-1 rounded-md bg-slate-50 border border-slate-100 text-slate-500">Dispositivos</span>
              <span class="text-[10px] px-2 py-1 rounded-md bg-slate-50 border border-slate-100 text-slate-500">Logs de acesso</span>
            </div>
          </div>
        </div>

        <!-- Card 6 — Preferências -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
          <h2 class="text-sm font-medium text-slate-700 mb-5">Preferências</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Idioma</dt>
              <dd class="font-medium text-slate-800">{{ pref.locale === 'pt-BR' ? 'Português (Brasil)' : pref.locale }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Tema</dt>
              <dd class="font-medium text-slate-400">Claro <span class="text-[10px] text-slate-300">(futuro)</span></dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Formato de data</dt>
              <dd class="font-medium text-slate-800">{{ pref.date_format }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Formato monetário</dt>
              <dd class="font-medium text-slate-800">{{ pref.currency_format === 'BRL' ? 'Real (R$)' : pref.currency_format }}</dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-xs text-slate-400 mb-0.5">Fuso horário</dt>
              <dd class="font-medium text-slate-800">{{ pref.timezone?.replace('_', ' ') }}</dd>
            </div>
            <div class="sm:col-span-2 pt-2 border-t border-slate-100">
              <dt class="text-xs text-slate-400 mb-2">Receber notificações</dt>
              <dd class="flex flex-wrap gap-3">
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                  <span class="w-2 h-2 rounded-full" :class="pref.notifications_email ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                  Email
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                  <span class="w-2 h-2 rounded-full" :class="pref.notifications_system ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                  Sistema
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                  <span class="w-2 h-2 rounded-full" :class="pref.notifications_whatsapp ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                  WhatsApp
                </span>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Coluna direita -->
      <div class="space-y-6">

        <!-- Card 3 — Histórico da Conta -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
          <h3 class="text-sm font-medium text-slate-700 mb-3">Histórico da Conta</h3>
          <dl class="space-y-3 text-xs">
            <div v-for="item in historyItems" :key="item.label">
              <dt class="text-slate-400 mb-0.5">{{ item.label }}</dt>
              <dd class="font-medium text-slate-800">{{ item.value() }}</dd>
            </div>
          </dl>
          <p class="text-[10px] text-slate-400 mt-3 leading-relaxed">
            As datas são registradas automaticamente pelo sistema.
          </p>
        </div>

        <!-- Card 4 — Permissões -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
          <h2 class="text-sm font-medium text-slate-700 mb-5">Permissões</h2>
          <dl class="space-y-4 text-sm">
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Função</dt>
              <dd class="font-medium text-slate-800">{{ displayValue(profile.permissions.role_label) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Permissões herdadas</dt>
              <dd class="font-medium text-slate-800">{{ profile.permissions.permissions_count }} permissões</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Clínicas vinculadas</dt>
              <dd class="font-medium text-slate-800">{{ profile.permissions.clinics_count }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-400 mb-0.5">Clínica principal</dt>
              <dd class="font-medium text-slate-800">{{ displayValue(profile.permissions.primary_clinic) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Card 5 — Estatísticas -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
          <h2 class="text-sm font-medium text-slate-700 mb-5">Estatísticas</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
            <div v-for="item in statItems" :key="item.label">
              <dt class="text-xs text-slate-400 mb-0.5">{{ item.label }}</dt>
              <dd class="text-sm font-semibold text-slate-800">{{ item.value() }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <ProfileEditModal
    :open="editModalOpen"
    :profile="profile"
    :job-titles="profile.job_titles"
    :can-edit-job-title="profile.permissions.can_edit_job_title"
    @close="closeEditModal"
    @photo-removed="removePhoto"
  />

  <PasswordChangeModal
    :open="passwordModalOpen"
    @close="passwordModalOpen = false"
  />
</AppLayout>
</template>