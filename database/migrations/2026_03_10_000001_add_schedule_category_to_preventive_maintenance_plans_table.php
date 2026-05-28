<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preventive_maintenance_plans', function (Blueprint $table) {
            $table->string('schedule_category')
                ->default('monthly')
                ->after('year');
        });

        DB::table('preventive_maintenance_plans')
            ->whereNull('schedule_category')
            ->update([
                'schedule_category' => 'monthly',
            ]);
    }

    public function down(): void
    {
        Schema::table('preventive_maintenance_plans', function (Blueprint $table) {
            $table->dropColumn('schedule_category');
        });
    }
};