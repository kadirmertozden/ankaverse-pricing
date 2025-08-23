<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            // daha önce hataya sebep olan kolonları garanti altına al
            if (!Schema::hasColumn('export_runs', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('publish_token');
            }
            if (Schema::hasColumn('export_runs', 'export_profile_id')) {
                $table->unsignedBigInteger('export_profile_id')->nullable(true)->change();
            }

            // senkronizasyon için yeni sütunlar
            if (!Schema::hasColumn('export_runs', 'source_url')) {
                $table->text('source_url')->nullable()->after('storage_path');
            }
            if (!Schema::hasColumn('export_runs', 'auto_sync')) {
                $table->boolean('auto_sync')->default(false)->after('source_url');
            }
            if (!Schema::hasColumn('export_runs', 'sync_interval_minutes')) {
                $table->unsignedInteger('sync_interval_minutes')->default(30)->after('auto_sync');
            }
            if (!Schema::hasColumn('export_runs', 'last_synced_at')) {
                $table->dateTime('last_synced_at')->nullable()->after('sync_interval_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            if (Schema::hasColumn('export_runs', 'source_url')) $table->dropColumn('source_url');
            if (Schema::hasColumn('export_runs', 'auto_sync')) $table->dropColumn('auto_sync');
            if (Schema::hasColumn('export_runs', 'sync_interval_minutes')) $table->dropColumn('sync_interval_minutes');
            if (Schema::hasColumn('export_runs', 'last_synced_at')) $table->dropColumn('last_synced_at');
        });
    }
};
