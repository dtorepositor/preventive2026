<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preventive_maintenance_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('preventive_maintenance_checklists', 'psm_id')) {
                $table->unsignedBigInteger('psm_id')->nullable()->unique()->after('id');
            }
        });

        Schema::table('item_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('item_checklists', 'psm_id')) {
                $table->unsignedBigInteger('psm_id')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('item_checklists', 'psm_id')) {
                $table->dropUnique(['psm_id']);
                $table->dropColumn('psm_id');
            }
        });

        Schema::table('preventive_maintenance_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('preventive_maintenance_checklists', 'psm_id')) {
                $table->dropUnique(['psm_id']);
                $table->dropColumn('psm_id');
            }
        });
    }
};

