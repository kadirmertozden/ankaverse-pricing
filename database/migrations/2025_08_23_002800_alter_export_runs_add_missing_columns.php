<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // storage_path
        if (! Schema::hasColumn('export_runs', 'storage_path')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->string('storage_path', 512)->nullable()->after('publish_token');
            });
        }

        // product_count
        if (! Schema::hasColumn('export_runs', 'product_count')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->unsignedInteger('product_count')->default(0)->after('storage_path');
            });
        }

        // is_active (ToggleColumn için gerekli)
        if (! Schema::hasColumn('export_runs', 'is_active')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('product_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('export_runs', 'is_active')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn('export_runs', 'product_count')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->dropColumn('product_count');
            });
        }

        if (Schema::hasColumn('export_runs', 'storage_path')) {
            Schema::table('export_runs', function (Blueprint $table) {
                $table->dropColumn('storage_path');
            });
        }
    }
};
