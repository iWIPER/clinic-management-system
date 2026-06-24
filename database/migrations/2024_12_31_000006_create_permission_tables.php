<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id');

            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }

            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index(
                [$columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_model_id_model_type_index'
            );

            $table->foreign($columnNames['permission_pivot_key'])
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {

                // ALTERADO: removido nullable()
                $table->unsignedBigInteger($columnNames['team_foreign_key']);

                $table->index(
                    $columnNames['team_foreign_key'],
                    'model_has_permissions_team_foreign_key_index'
                );

                $table->primary(
                    [
                        $columnNames['team_foreign_key'],
                        $columnNames['permission_pivot_key'],
                        $columnNames['model_morph_key'],
                        'model_type'
                    ],
                    'model_has_permissions_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [
                        $columnNames['permission_pivot_key'],
                        $columnNames['model_morph_key'],
                        'model_type'
                    ],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->unsignedBigInteger($columnNames['role_pivot_key']);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index(
                [$columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_model_id_model_type_index'
            );

            $table->foreign($columnNames['role_pivot_key'])
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            if ($teams) {

                // ALTERADO: removido nullable()
                $table->unsignedBigInteger($columnNames['team_foreign_key']);

                $table->index(
                    $columnNames['team_foreign_key'],
                    'model_has_roles_team_foreign_key_index'
                );

                $table->primary(
                    [
                        $columnNames['team_foreign_key'],
                        $columnNames['role_pivot_key'],
                        $columnNames['model_morph_key'],
                        'model_type'
                    ],
                    'model_has_roles_role_model_type_primary'
                );
            } else {
                $table->primary(
                    [
                        $columnNames['role_pivot_key'],
                        $columnNames['model_morph_key'],
                        'model_type'
                    ],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);
            $table->unsignedBigInteger($columnNames['role_pivot_key']);

            $table->foreign($columnNames['permission_pivot_key'])
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($columnNames['role_pivot_key'])
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary(
                [
                    $columnNames['permission_pivot_key'],
                    $columnNames['role_pivot_key']
                ],
                'role_has_permissions_permission_id_role_id_primary'
            );
        });

        app('cache')
            ->store(
                config('permission.cache.store') != 'default'
                    ? config('permission.cache.store')
                    : null
            )
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
