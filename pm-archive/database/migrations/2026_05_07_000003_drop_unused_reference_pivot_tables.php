<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'psm_equipment',
        'psm_operating_systems',
        'psm_software_applications',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->count() > 0) {
                continue;
            }

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('psm_equipment')) {
            Schema::create('psm_equipment', function (Blueprint $table) {
                $table->unsignedBigInteger('psm_id');
                $table->unsignedBigInteger('equipment_id');
                $table->primary(['psm_id', 'equipment_id']);
                $table->foreign('psm_id')->references('psm_id')->on('psm')->cascadeOnDelete();
                $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('psm_operating_systems')) {
            Schema::create('psm_operating_systems', function (Blueprint $table) {
                $table->unsignedBigInteger('psm_id');
                $table->unsignedBigInteger('operating_system_id');
                $table->primary(['psm_id', 'operating_system_id']);
                $table->foreign('psm_id')->references('psm_id')->on('psm')->cascadeOnDelete();
                $table->foreign('operating_system_id')->references('id')->on('operating_systems')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('psm_software_applications')) {
            Schema::create('psm_software_applications', function (Blueprint $table) {
                $table->unsignedBigInteger('psm_id');
                $table->unsignedBigInteger('software_application_id');
                $table->primary(['psm_id', 'software_application_id']);
                $table->foreign('psm_id')->references('psm_id')->on('psm')->cascadeOnDelete();
                $table->foreign('software_application_id')->references('id')->on('software_applications')->cascadeOnDelete();
            });
        }
    }
};
