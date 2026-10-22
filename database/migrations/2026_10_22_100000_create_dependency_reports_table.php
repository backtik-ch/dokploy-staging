<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->string('branch');
            $table->string('commit_sha', 40);
            $table->string('image');
            $table->string('digest', 71);
            $table->string('platform', 100);
            $table->string('language_version');
            $table->timestamp('built_at');
            $table->timestamp('production_confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'image', 'digest', 'platform'], 'dependency_reports_image_unique');
            $table->index(['project_id', 'built_at']);
        });

        Schema::create('dependency_report_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependency_report_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('manager', 100)->nullable();
            $table->string('path', 1024)->nullable();
            $table->string('required')->nullable();
            $table->string('locked')->nullable();
            $table->string('installed')->nullable();
            $table->string('locked_reference')->nullable();
            $table->string('installed_reference')->nullable();
            $table->boolean('lock_observed')->default(false);
            $table->boolean('installation_observed')->default(false);
            $table->boolean('dev')->default(false);
            $table->boolean('direct')->default(true);
            $table->index(['dependency_report_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_report_packages');
        Schema::dropIfExists('dependency_reports');
    }
};
