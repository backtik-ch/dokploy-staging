<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('github_token')->nullable()->after('github_repository');
            $table->json('linked_repositories')->nullable()->after('github_token');
        });

        Schema::table('stagings', function (Blueprint $table) {
            $table->json('selected_branches')->nullable()->after('branch');
        });
    }

    public function down(): void
    {
        Schema::table('stagings', function (Blueprint $table) {
            $table->dropColumn('selected_branches');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['github_token', 'linked_repositories']);
        });
    }
};
