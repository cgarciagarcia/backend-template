<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private Cache $cache;
    public function __construct()
    {
        /** @var Cache $instance */
        $instance = App::get(Cache::class);
        $this->cache = $instance;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var boolean $teams */
        $teams = config('permission.teams');

        /** @var array{
         *     permissions: string,
         *     roles: string,
         *     model_has_permissions: string,
         *     model_has_roles: string,
         *     role_has_permissions: string,
         * } $tableNames
         */
        $tableNames = config('permission.table_names') ?? [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ];
        /** @var array{
         *     role_pivot_key: string,
         *     permission_pivot_key: string,
         *     team_foreign_key: string,
         *     model_morph_key: string
         * } $columnNames
         */
        $columnNames = config('permission.column_names') ?? [
            'role_pivot_key' => 'rol_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ];

        /** @var string $pivotRole */
        $pivotRole = $columnNames['role_pivot_key'];
        /** @var string $pivotPermission */
        $pivotPermission = $columnNames['permission_pivot_key'];

        $permissionTableName = $tableNames['permissions'];

        Schema::create($permissionTableName, function (Blueprint $table) {
            $table->bigIncrements('id'); // permission id
            $table->string('name');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name'); // For MySQL 8.0 use string('guard_name', 125);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id'); // role id
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name'); // For MySQL 8.0 use string('guard_name', 125);
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create(
            $tableNames['model_has_permissions'],
            function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
                $table->unsignedBigInteger($pivotPermission);

                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index(
                    [$columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_model_id_model_type_index'
                );

                $table->foreign($pivotPermission)
                    ->references('id') // permission id
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');
                if ($teams) {
                    $table->unsignedBigInteger($columnNames['team_foreign_key']);
                    $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                    $table->primary(
                        [
                        $columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'],
                        'model_type',
                        ],
                        'model_has_permissions_permission_model_type_primary'
                    );
                } else {
                    $table->primary(
                        [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                        'model_has_permissions_permission_model_type_primary'
                    );
                }
            }
        );

        Schema::create(
            $tableNames['model_has_roles'],
            function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
                $table->unsignedBigInteger($pivotRole);

                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index(
                    [$columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_model_id_model_type_index'
                );

                $table->foreign($pivotRole)
                    ->references('id') // role id
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');
                if ($teams) {
                    $table->unsignedBigInteger($columnNames['team_foreign_key']);
                    $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                    $table->primary(
                        [
                        $columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type',
                        ],
                        'model_has_roles_role_model_type_primary'
                    );
                } else {
                    $table->primary(
                        [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                        'model_has_roles_role_model_type_primary'
                    );
                }
            }
        );

        Schema::create(
            $tableNames['role_has_permissions'],
            function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);

                $table->foreign($pivotPermission)
                    ->references('id') // permission id
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                $table->foreign($pivotRole)
                    ->references('id') // role id
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
            }
        );

        $cacheStore = config('permission.cache.store');
        $store = is_string($cacheStore) ? $cacheStore : null;
        $configKey = config('permission.cache.key');
        $key = is_string($configKey) ? $configKey : 'spatie.permission.cache';
        $this->cache::store($store != 'default' ? $store : null)->forget($key);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** @var array{
         *     permissions: string,
         *     roles: string,
         *     model_has_permissions: string,
         *     model_has_roles: string,
         *     role_has_permissions: string,
         * } $tableNames
         */
        $tableNames = config('permission.table_names') ?? [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ];

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
