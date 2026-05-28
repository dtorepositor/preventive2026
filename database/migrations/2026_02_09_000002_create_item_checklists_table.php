<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preventive_maintenance_checklist_id')->constrained()->cascadeOnDelete();
            $table->date('maintenance_date')->nullable();
            $table->text('summary_recommendation')->nullable();
            $table->string('checked_by')->nullable();
            $table->string('conforme_by')->nullable();
            $table->timestamps();
        });

        // Task groups (e.g., "System Boot", "Network Settings", "Computer Hardware Settings")
        Schema::create('item_checklist_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_checklist_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('item_no'); // Item #1, #2, #3, etc.
            $table->string('task'); // e.g., "Network Settings", "Computer Hardware Settings"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Individual checklist items under each task (e.g., "Domain Name", "Security Settings")
        Schema::create('item_checklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_checklist_task_id')->constrained()->cascadeOnDelete();
            $table->text('description'); // e.g., "Domain Name", "Security Settings", "Computer Name"
            $table->enum('status', ['ok', 'repair', 'na'])->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_checklist_entries');
        Schema::dropIfExists('item_checklist_tasks');
        Schema::dropIfExists('item_checklists');
    }
};