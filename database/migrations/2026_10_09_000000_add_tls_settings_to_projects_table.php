<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('certificate_type')->default('letsencrypt')->after('domain_name');
            $table->string('custom_cert_resolver')->default('infomaniak')->after('certificate_type');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['certificate_type', 'custom_cert_resolver']);
        });
    }
};
