<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role'       => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles'                 => 'roles',
        'permissions'           => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles'       => 'model_has_roles',
        'role_has_permissions'  => 'role_has_permissions',
    ],

    // Feature de Teams ativada: cada "team" = uma Clínica
    'teams'            => true,
    'team_foreign_key' => 'team_id',

    'column_names' => [
        'role_pivot_key'       => 'role_id',
        'permission_pivot_key' => 'permission_id',
        'model_morph_key'      => 'model_id',
        'team_foreign_key'     => 'team_id',
    ],

    'register_permission_check_method' => true,
    'register_octane_reset_listener'   => false,
    'events_enabled'                   => false,
    'testing'                          => false,

    'defaults' => [
        'guard' => 'web',
    ],

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key'             => 'spatie.permission.cache',
        'store'           => 'default',
    ],
];
