<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('psm_value')) {
            Schema::table('psm_value', function (Blueprint $table) {
                // Add a composite unique index to prevent duplicate values for the same psm + variable
                $table->unique(['psm_id', 'psm_var_id'], 'psm_value_psm_var_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('psm_value')) {
            Schema::table('psm_value', function (Blueprint $table) {
                $table->dropUnique('psm_value_psm_var_unique');
            });
        }
    }
};
