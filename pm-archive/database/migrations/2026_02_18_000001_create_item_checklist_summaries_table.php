<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_checklist_summaries')) {
            Schema::create('item_checklist_summaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('psm_id'); // references item checklist submission (item checklist)
                $table->text('summary_recommendation')->nullable();
                $table->tinyInteger('enabled')->default(1);
                $table->timestamps();

                // Foreign keys sometimes fail if legacy tables use non-InnoDB engines; index instead
                $table->index('psm_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_checklist_summaries');
    }
};
