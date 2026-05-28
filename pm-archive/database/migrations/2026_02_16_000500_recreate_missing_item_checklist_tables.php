<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate item_checklists if it is missing (guarded by hasTable).
        if (! Schema::hasTable('item_checklists')) {
            Schema::create('item_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('preventive_maintenance_checklist_id')->constrained()->cascadeOnDelete();
                $table->date('maintenance_date')->nullable();
                $table->text('summary_recommendation')->nullable();
                $table->string('checked_by')->nullable();
                $table->string('conforme_by')->nullable();
                $table->timestamps();
            });
        }

        // Recreate task groups if missing.
        if (! Schema::hasTable('item_checklist_tasks')) {
            Schema::create('item_checklist_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_checklist_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('item_no');
                $table->string('task');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Recreate individual entries if missing.
        if (! Schema::hasTable('item_checklist_entries')) {
            Schema::create('item_checklist_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_checklist_task_id')->constrained()->cascadeOnDelete();
                $table->text('description');
                $table->enum('status', ['ok', 'repair', 'na'])->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_checklist_entries');
        Schema::dropIfExists('item_checklist_tasks');
        Schema::dropIfExists('item_checklists');
    }
};
