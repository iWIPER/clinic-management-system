import {
    UserPlusIcon,
    CalendarDaysIcon,
    WrenchScrewdriverIcon,
    ArchiveBoxIcon,
    DocumentDuplicateIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline'

// As duas ações padrão do menu "Ações" da TopIsland — sempre presentes,
// não removíveis. Mesmo padrão do que já existia em Pages/Dashboard.vue
// ("Ações rápidas": Cadastrar Novo Paciente / Novo Agendamento), só que
// acessível globalmente em vez de só na Dashboard.
export const STANDARD_QUICK_ACTIONS = [
    { key: 'patients.create', label: 'Cadastrar paciente', route: 'patients.create', icon: UserPlusIcon },
    { key: 'appointments.create', label: 'Novo agendamento', route: 'appointments.create', icon: CalendarDaysIcon },
]

// Candidatas a ação personalizada — precisa bater 1:1 com
// UserProfileService::ALLOWED_QUICK_ACTIONS no backend (a validação da
// whitelist é sempre do servidor; esta lista só decide o que MOSTRAR).
// Critério: fluxo de criação autônomo, navegável direto por link, sem
// depender de estado de outra tela (por isso convite de equipe — que é um
// modal interno de Team/Index.vue — não entra aqui).
export const CUSTOMIZABLE_QUICK_ACTIONS = [
    { key: 'treatments.create', label: 'Novo procedimento', route: 'treatments.create', icon: WrenchScrewdriverIcon },
    { key: 'inventory.create', label: 'Novo item de estoque', route: 'inventory.create', icon: ArchiveBoxIcon },
    { key: 'document-templates.create', label: 'Novo modelo de documento', route: 'document-templates.create', icon: DocumentDuplicateIcon },
    { key: 'anamnesis-templates.create', label: 'Novo modelo de anamnese', route: 'anamnesis-templates.create', icon: ClipboardDocumentListIcon },
]

export const MAX_CUSTOM_QUICK_ACTIONS = 2

export function findQuickAction(key) {
    return CUSTOMIZABLE_QUICK_ACTIONS.find((action) => action.key === key) ?? null
}
