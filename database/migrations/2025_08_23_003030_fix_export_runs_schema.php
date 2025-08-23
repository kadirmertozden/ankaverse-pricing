<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('export_runs', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('publish_token');
            }
        });

        // export_profile_id'yi nullable yap (MySQL)
        try {
            DB::statement('ALTER TABLE export_runs MODIFY export_profile_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Doktrin yoksa veya tip farklıysa alternatif:
            try {
                DB::statement('ALTER TABLE export_runs MODIFY export_profile_id bigint unsigned NULL');
            } catch (\Throwable $e2) {
                // doktrin gerekebilir: composer require doctrine/dbal
            }
        }
    }

    public function down(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            if (Schema::hasColumn('export_runs', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        try {
            DB::statement('ALTER TABLE export_runs MODIFY export_profile_id BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
        }
    }
};
