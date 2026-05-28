<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psm') || Schema::hasColumn('psm', 'is_locked')) {
            return;
        }

        Schema::table('psm', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('created_by')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('psm') || ! Schema::hasColumn('psm', 'is_locked')) {
            return;
        }

        Schema::table('psm', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
