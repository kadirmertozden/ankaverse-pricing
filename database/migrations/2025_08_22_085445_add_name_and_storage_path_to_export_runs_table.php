<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('export_runs', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('export_runs', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('export_runs', function (Blueprint $table) {
            if (Schema::hasColumn('export_runs', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('export_runs', 'storage_path')) {
                $table->dropColumn('storage_path');
            }
        });
    }
};
