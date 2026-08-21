import { BuildingOffice2Icon, UsersIcon } from '@heroicons/vue/24/outline'

// Atalhos fixos da ilha "Atalhos" do Backoffice — ao contrário da versão
// clínica (STANDARD_QUICK_ACTIONS + até 2 personalizáveis, ver
// Navigation/quickActions.js), aqui são exatamente 2, fixos, não
// personalizáveis: os módulos administrativos mais usados.
export const ADMIN_QUICK_ACTIONS = [
    { key: 'admin.clinics', label: 'Clínicas', route: 'admin.clinics', icon: BuildingOffice2Icon },
    { key: 'admin.users', label: 'Usuários', route: 'admin.users', icon: UsersIcon },
]
