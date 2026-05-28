<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('college_offices')) {
            Schema::create('college_offices', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('college_office_id')
                    ->constrained('college_offices')
                    ->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['college_office_id', 'name'], 'departments_office_name_unique');
            });
        }

        if (Schema::hasTable('preventive_maintenance_checklists')) {
            $needsCollegeOfficeId = ! Schema::hasColumn('preventive_maintenance_checklists', 'college_office_id');
            $needsDepartmentId = ! Schema::hasColumn('preventive_maintenance_checklists', 'department_id');

            Schema::table('preventive_maintenance_checklists', function (Blueprint $table) use ($needsCollegeOfficeId, $needsDepartmentId) {
                if ($needsCollegeOfficeId) {
                    $table->foreignId('college_office_id')
                        ->nullable()
                        ->constrained('college_offices')
                        ->nullOnDelete();
                }

                if ($needsDepartmentId) {
                    $table->foreignId('department_id')
                        ->nullable()
                        ->constrained('departments')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('preventive_maintenance_checklists')) {
            $hasDepartmentId = Schema::hasColumn('preventive_maintenance_checklists', 'department_id');
            $hasCollegeOfficeId = Schema::hasColumn('preventive_maintenance_checklists', 'college_office_id');

            Schema::table('preventive_maintenance_checklists', function (Blueprint $table) use ($hasDepartmentId, $hasCollegeOfficeId) {
                if ($hasDepartmentId) {
                    $table->dropConstrainedForeignId('department_id');
                }

                if ($hasCollegeOfficeId) {
                    $table->dropConstrainedForeignId('college_office_id');
                }
            });
        }

        Schema::dropIfExists('departments');
        Schema::dropIfExists('college_offices');
    }
};
