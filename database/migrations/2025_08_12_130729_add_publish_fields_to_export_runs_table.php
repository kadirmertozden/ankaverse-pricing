<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('export_runs', function (Blueprint $t) {
            $t->string('publish_token', 64)->unique()->nullable()->after('path');
            $t->boolean('is_public')->default(false)->after('publish_token');
            $t->timestamp('published_at')->nullable()->after('is_public');
        });
    }

    public function down(): void {
        Schema::table('export_runs', function (Blueprint $t) {
            $t->dropColumn(['publish_token','is_public','published_at']);
        });
    }
};
