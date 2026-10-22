<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokploys', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('maintenance_yml_file')->nullable();
            $table->boolean('maintenance_enabled')->nullable();
            $table->timestamp('maintenance_checked_at')->nullable();
            $table->text('maintenance_error')->nullable();

            foreach (['dokploy_project_id', 'github_id', 'github_owner', 'github_repository', 'compose_name_file', 'domain_name'] as $column) {
                $table->string($column)->nullable()->change();
            }

            $table->text('extra_sub_domains')->nullable()->change();
            $table->text('environment_staging')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Nullable staging fields remain nullable so maintenance-only projects are preserved.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['maintenance_yml_file', 'maintenance_enabled', 'maintenance_checked_at', 'maintenance_error']);
        });

        Schema::table('dokploys', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
