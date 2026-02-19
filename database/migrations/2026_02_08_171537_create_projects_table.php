<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Dokploy::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('app_name');
            $table->string('dokploy_project_id');
            $table->string('server_id')->nullable();
            $table->string('github_id');
            $table->string('github_owner');
            $table->string('github_repository');
            $table->string('compose_name_file');
            $table->string('domain_name');
            $table->text('extra_sub_domains');

            $table->text('environment_staging');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
