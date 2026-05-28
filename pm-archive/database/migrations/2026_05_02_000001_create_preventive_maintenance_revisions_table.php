<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preventive_maintenance_revisions')) {
            return;
        }

        Schema::create('preventive_maintenance_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psm_id');
            $table->string('name', 100)->nullable();
            $table->text('detail')->nullable();
            $table->json('values_snapshot');
            $table->timestamp('original_created_at')->nullable();
            $table->timestamps();

            $table->index(['psm_id', 'created_at']);
            $table->foreign('psm_id')
                ->references('psm_id')
                ->on('psm')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_revisions');
    }
};
