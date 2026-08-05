<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    clinic: Object,
    settings: Object,
    templates: { type: Array, default: () => [] },
})

const form = useForm({
    phone: props.clinic.phone || '',
    email: props.clinic.email || '',
    website: props.clinic.website || '',
    address_street: props.clinic.address_street || '',
    address_number: props.clinic.address_number || '',
    address_complement: props.clinic.address_complement || '',
    address_neighborhood: props.clinic.address_neighborhood || '',
    address_city: props.clinic.address_city || '',
    address_state: props.clinic.address_state || '',
    address_zipcode: props.clinic.address_zipcode || '',
    default_signature_expiration_hours: props.settings.default_signature_expiration_hours || 72,
    footer_show_qrcode: props.settings.footer_show_qrcode ?? true,
    footer_show_hash: props.settings.footer_show_hash ?? true,
    footer_custom_text: props.settings.footer_custom_text || '',
})

const submit = () => {
    form.put(route('clinic-settings.documents.update'), { preserveScroll: true })
}

const setDefault = (template) => {
    router.post(route('document-templates.set-default', template.id), {}, { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 py-8">
            <div class="mb-6">
                <Link :href="route('documents.index')" class="text-[11px] text-slate-400 hover:text-teal-600 transition-colors">← Documentos</Link>
                <h1 class="text-xl font-bold text-slate-900 mt-1">Configurações de Documentos</h1>
                <p class="text-sm text-slate-500 mt-1">Dados exibidos no rodapé dos PDFs e comportamento padrão de assinatura — sem precisar mexer em código.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-900 mb-4">Dados de contato (rodapé do PDF)</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Telefone</label>
                            <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">E-mail</label>
                            <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Site</label>
                            <input v-model="form.website" type="text" placeholder="https://" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.website" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-900 mb-4">Endereço</h2>
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Logradouro</label>
                            <input v-model="form.address_street" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_street" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Número</label>
                            <input v-model="form.address_number" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_number" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Complemento</label>
                            <input v-model="form.address_complement" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_complement" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Bairro</label>
                            <input v-model="form.address_neighborhood" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_neighborhood" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Cidade</label>
                            <input v-model="form.address_city" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_city" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">UF</label>
                            <input v-model="form.address_state" type="text" maxlength="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400 uppercase" />
                            <InputError :message="form.errors.address_state" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">CEP</label>
                            <input v-model="form.address_zipcode" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.address_zipcode" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-900 mb-4">Comportamento padrão</h2>
                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Expiração padrão do link de assinatura (horas)</label>
                            <input v-model.number="form.default_signature_expiration_hours" type="number" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                            <InputError :message="form.errors.default_signature_expiration_hours" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4 mb-4">
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.footer_show_qrcode" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Mostrar QR Code no rodapé
                        </label>
                        <label class="flex items-center gap-2 text-[13px] text-slate-700">
                            <input v-model="form.footer_show_hash" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-400" /> Mostrar hash de verificação
                        </label>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Texto adicional no rodapé (opcional)</label>
                        <input v-model="form.footer_custom_text" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-400" />
                        <InputError :message="form.errors.footer_custom_text" />
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50 hover:bg-teal-700 transition-colors shadow-sm">Salvar configurações</button>
            </form>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 mt-6">
                <h2 class="text-sm font-bold text-slate-900 mb-4">Modelos ativos</h2>
                <div class="space-y-2">
                    <div v-for="t in templates" :key="t.id" class="flex items-center justify-between text-[13px] py-2 border-b border-slate-50 last:border-0">
                        <div>
                            <span class="font-medium text-slate-800">{{ t.name }}</span>
                            <span class="text-slate-400 ml-2">{{ t.category }}</span>
                            <span v-if="t.is_default" class="ml-2 text-[9px] font-semibold text-teal-700 bg-teal-50 border border-teal-100 px-1.5 py-0.5 rounded">Padrão</span>
                        </div>
                        <button v-if="!t.is_default" @click="setDefault(t)" class="text-[11px] font-semibold text-teal-700 hover:text-teal-800">Definir padrão</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
