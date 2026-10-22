<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependency_reports', function (Blueprint $table): void {
            $table->dropColumn('production_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dependency_reports', function (Blueprint $table): void {
            $table->timestamp('production_confirmed_at')->nullable();
        });
    }
};
