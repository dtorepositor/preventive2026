<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_checklist_display')) {
            Schema::create('item_checklist_display', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('psm_id'); // item checklist psm id
                $table->unsignedBigInteger('parent_psm_id')->nullable();
                $table->unsignedBigInteger('preventive_maintenance_checklist_id')->nullable();
                $table->date('maintenance_date')->nullable();
                $table->text('summary_recommendation')->nullable();
                $table->string('checked_by', 255)->nullable();
                $table->string('conforme_by', 255)->nullable();

                $table->integer('item_no')->nullable();
                $table->string('task', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 50)->nullable();
                $table->integer('sort_order')->default(0);

                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['psm_id']);
                $table->index(['parent_psm_id']);
            });

            // Backfill existing data from item_checklists + item_checklist_tasks + item_checklist_entries
            $rows = DB::table('item_checklists')
                ->leftJoin('item_checklist_tasks', 'item_checklists.id', '=', 'item_checklist_tasks.item_checklist_id')
                ->leftJoin('item_checklist_entries', 'item_checklist_tasks.id', '=', 'item_checklist_entries.item_checklist_task_id')
                ->select(
                    'item_checklists.psm_id as psm_id',
                    DB::raw('NULL as parent_psm_id'),
                    'item_checklists.preventive_maintenance_checklist_id',
                    'item_checklists.maintenance_date',
                    'item_checklists.summary_recommendation',
                    'item_checklists.checked_by',
                    'item_checklists.conforme_by',
                    'item_checklist_tasks.item_no',
                    'item_checklist_tasks.task',
                    'item_checklist_entries.description',
                    'item_checklist_entries.status',
                    'item_checklist_entries.sort_order',
                    'item_checklists.created_at'
                )
                ->get();

            $inserts = [];
            foreach ($rows as $r) {
                $inserts[] = [
                    'psm_id' => $r->psm_id,
                    'parent_psm_id' => $r->parent_psm_id,
                    'preventive_maintenance_checklist_id' => $r->preventive_maintenance_checklist_id,
                    'maintenance_date' => $r->maintenance_date,
                    'summary_recommendation' => $r->summary_recommendation,
                    'checked_by' => $r->checked_by,
                    'conforme_by' => $r->conforme_by,
                    'item_no' => $r->item_no,
                    'task' => $r->task,
                    'description' => $r->description,
                    'status' => $r->status,
                    'sort_order' => $r->sort_order,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->created_at,
                ];
            }

            if (! empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('item_checklist_display')->insert($chunk);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_checklist_display');
    }
};
