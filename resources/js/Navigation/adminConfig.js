import {
    Squares2X2Icon,
    BuildingOffice2Icon,
    UsersIcon,
    GiftIcon,
    TagIcon,
    ArrowDownTrayIcon,
    ClipboardDocumentListIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

// Árvore de navegação declarativa da sidebar do Backoffice — mesma forma
// de dados de Navigation/config.js (a sidebar clínica), só que apontando
// pros módulos administrativos já existentes em AdminLayout.vue (nenhum
// módulo novo, só reorganizados aqui em vez da tab-bar horizontal que
// existia antes). Rotas 'admin.*' já são protegidas pelo middleware
// 'system-admin', independente de current_clinic_id — ver
// EnsureCurrentClinic/SystemAdmin middleware.
export const adminNavigation = [
    {
        section: '',
        items: [
            { label: 'Dashboard', route: 'admin.index', match: '/admin$', icon: Squares2X2Icon },
            { label: 'Clínicas', route: 'admin.clinics', match: '/admin/clinicas', icon: BuildingOffice2Icon },
            { label: 'Usuários', route: 'admin.users', match: '/admin/usuarios', icon: UsersIcon },
            { label: 'Indicações', route: 'admin.referrals', match: '/admin/indicacoes', icon: GiftIcon },
            { label: 'Planos', route: 'admin.plans', match: '/admin/planos', icon: TagIcon },
            { label: 'Exportações', route: 'admin.exports', match: '/admin/exportacoes', icon: ArrowDownTrayIcon },
            { label: 'Logs', route: 'admin.logs', match: '/admin/logs', icon: ClipboardDocumentListIcon },
            { label: 'System Admins', route: 'admin.system-admins', match: '/admin/system-admins', icon: ShieldCheckIcon },
        ],
    },
]
