<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependency_report_packages', function (Blueprint $table): void {
            $table->timestamp('audited_at')->nullable();
        });

        Schema::create('dependency_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dependency_report_id')->constrained()->cascadeOnDelete();
            $table->string('manager', 100);
            $table->timestamp('attempted_at');
            $table->timestamp('audited_at')->nullable();
            $table->text('error')->nullable();
            $table->unique(['dependency_report_id', 'manager']);
        });

        Schema::create('dependency_audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dependency_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dependency_report_package_id')->constrained()->cascadeOnDelete();
            $table->string('severity', 20);
            $table->text('message');
            $table->text('url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_audit_findings');
        Schema::dropIfExists('dependency_audits');
        Schema::table('dependency_report_packages', function (Blueprint $table): void {
            $table->dropColumn('audited_at');
        });
    }
};
