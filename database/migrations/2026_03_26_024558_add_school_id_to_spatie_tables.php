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
        $teamForeignKey = $columnNames['team_foreign_key'];

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamForeignKey) {
            if (!Schema::hasColumn($table->getTable(), $teamForeignKey)) {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after('id');
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
                
                $table->dropUnique('roles_name_guard_name_unique');
                $table->unique([$teamForeignKey, 'name', 'guard_name'], 'roles_team_name_guard_unique');
            }
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey) {
            if (!Schema::hasColumn($table->getTable(), $teamForeignKey)) {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after($columnNames['model_morph_key']);
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');

                // Using a unique index instead of primary key to support nullable school_id if needed
                $table->dropPrimary(['model_has_permissions_permission_model_type_primary']);
                $table->unique([$columnNames['permission_pivot_key'], $columnNames['model_morph_key'], 'model_type', $teamForeignKey], 'model_has_permissions_unique');
            }
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey) {
            if (!Schema::hasColumn($table->getTable(), $teamForeignKey)) {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after($columnNames['model_morph_key']);
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');

                // Using a unique index instead of primary key to support nullable school_id if needed
                $table->dropPrimary(['model_has_roles_role_model_type_primary']);
                $table->unique([$columnNames['role_pivot_key'], $columnNames['model_morph_key'], 'model_type', $teamForeignKey], 'model_has_roles_unique');
            }
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key'];

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamForeignKey) {
            $table->dropUnique('roles_team_name_guard_unique');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            $table->dropColumn($teamForeignKey);
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            $table->primary([$columnNames['permission_pivot_key'], $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            $table->dropColumn($teamForeignKey);
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
            $table->primary([$columnNames['role_pivot_key'], $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
            $table->dropColumn($teamForeignKey);
        });
    }
};
