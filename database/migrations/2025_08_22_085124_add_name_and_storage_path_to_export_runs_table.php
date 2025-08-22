<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        // Eski path değerleri fiziksel yolu içeriyorsa, storage_path'e taşı ve path'i public URL yap
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $runs = DB::table('export_runs')->get(['id','path','publish_token']);
        foreach ($runs as $r) {
            if ($r->path && str_starts_with($r->path, 'exports/')) {
                DB::table('export_runs')->where('id', $r->id)->update([
                    'storage_path' => $r->path,
                    'path'         => $base . '/' . $r->publish_token,
                ]);
            } elseif (empty($r->path) && !empty($r->publish_token)) {
                DB::table('export_runs')->where('id', $r->id)->update([
                    'path' => $base . '/' . $r->publish_token,
                ]);
            }
        }
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
