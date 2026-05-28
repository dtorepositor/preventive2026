<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('preventive_maintenance_plan_schedules');
        Schema::dropIfExists('preventive_maintenance_plans');

        Schema::create('preventive_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });

        Schema::create('preventive_maintenance_plan_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('preventive_maintenance_plan_id');
            $table->unsignedTinyInteger('month');
            $table->string('office_name');
            $table->timestamps();

            $table->unique(
                ['preventive_maintenance_plan_id', 'month', 'office_name'],
                'pm_plan_sched_unique'
            );

            $table->foreign('preventive_maintenance_plan_id', 'pm_plan_sched_plan_fk')
                ->references('id')
                ->on('preventive_maintenance_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_plan_schedules');
        Schema::dropIfExists('preventive_maintenance_plans');
    }
};
