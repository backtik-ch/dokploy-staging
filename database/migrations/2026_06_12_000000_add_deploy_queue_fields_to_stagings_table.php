<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stagings', function (Blueprint $table) {
            $table->string('deploy_status')->default('deployed')->after('environment');
            $table->json('missing_images')->nullable()->after('deploy_status');
            $table->timestamp('deploy_requested_at')->nullable()->after('missing_images');
            $table->timestamp('deploy_checked_at')->nullable()->after('deploy_requested_at');
            $table->timestamp('deploy_started_at')->nullable()->after('deploy_checked_at');
            $table->text('deploy_error')->nullable()->after('deploy_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('stagings', function (Blueprint $table) {
            $table->dropColumn([
                'deploy_status',
                'missing_images',
                'deploy_requested_at',
                'deploy_checked_at',
                'deploy_started_at',
                'deploy_error',
            ]);
        });
    }
};
