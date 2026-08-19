import {
    CalendarDaysIcon,
    UsersIcon,
    ClipboardDocumentCheckIcon,
    DocumentTextIcon,
    WrenchScrewdriverIcon,
    ArchiveBoxIcon,
    FolderIcon,
    BanknotesIcon,
    UserGroupIcon,
    GiftIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

// Árvore de navegação declarativa da sidebar clínica: cada grupo tem uma
// `section` (label, vazio omite o rótulo), uma lista de `items` com rota
// Ziggy/prefixo de URL/ícone, e opcionalmente `divider: true` — desenha uma
// linha fina acima do grupo em vez de um rótulo, usada só pelo grupo final
// (Equipe/Indicações/Configurações), que é apoio administrativo, não uma
// área de trabalho principal como as demais.
//
// `emphasized` dá um pouco mais de destaque tipográfico aos dois itens mais
// frequentes da navegação (Agenda, Pacientes) — só tamanho de fonte, sem
// mudar peso/cor, que já são uniformes pra todos os itens.
//
// `permission` fica reservado para uma futura regra de acesso por item — hoje
// nenhum item é escondido por cargo (o comportamento atual do sistema não
// esconde nada além do Backoffice, que já é tratado separadamente via
// isSystemAdmin). Quando essa necessidade existir de verdade, o Sidebar.vue
// passa a filtrar por esse campo sem precisar mudar a forma da árvore. O
// backend continua sendo a autoridade final de autorização — a UI nunca
// decide isso sozinha.
export const navigation = [
    {
        section: 'Atendimento',
        items: [
            { label: 'Agenda', route: 'appointments.index', match: '/appointments', icon: CalendarDaysIcon, emphasized: true, permission: null },
            { label: 'Pacientes', route: 'patients.index', match: '/patients', icon: UsersIcon, emphasized: true, permission: null },
            { label: 'Consultas', route: 'consultations.index', match: '/consultations', icon: ClipboardDocumentCheckIcon, permission: null },
            { label: 'Atendimentos', route: 'clinical-records.index', match: '/clinical-records', icon: DocumentTextIcon, permission: null },
        ],
    },
    {
        section: 'Clínica',
        items: [
            { label: 'Procedimentos', route: 'treatments.index', match: '/treatments', icon: WrenchScrewdriverIcon, permission: null },
            { label: 'Estoque', route: 'inventory.index', match: '/inventory', icon: ArchiveBoxIcon, permission: null },
            { label: 'Documentos', route: 'documents.index', match: '/documents', icon: FolderIcon, permission: null },
        ],
    },
    {
        section: 'Gestão',
        items: [
            { label: 'Financeiro', route: 'finance.index', match: '/finance', icon: BanknotesIcon, permission: null },
        ],
    },
    {
        section: '',
        divider: true,
        items: [
            { label: 'Equipe', route: 'team.index', match: '/equipe', icon: UserGroupIcon, permission: null },
            { label: 'Indicações', route: 'referrals.index', match: '/indicacoes', icon: GiftIcon, badge: 'Novidade', permission: null },
            { label: 'Configurações', route: 'clinic-settings.edit', match: '/clinic-settings', icon: Cog6ToothIcon, permission: null },
        ],
    },
]
