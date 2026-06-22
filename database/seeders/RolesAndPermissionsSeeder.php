<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Clinic;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissões granulares (exemplos)
        $permissions = [
            'view_patients',
            'create_patients',
            'edit_patients',
            'delete_patients',

            'view_appointments',
            'create_appointments',
            'manage_schedule',

            'perform_consultations',
            'view_medical_records',
            'edit_medical_records',

            'manage_inventory',
            'view_financial',
            'manage_financial',

            'manage_team',
            'manage_clinic_settings',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Papéis por clínica (usando teams)
        $roles = ['owner', 'admin', 'professional', 'staff'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Owner terá todas (atribuídas manualmente no onboarding)
    }
}
