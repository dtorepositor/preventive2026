<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psm_detail')) {
            return;
        }

        if (DB::table('psm_detail')->count() > 0) {
            return;
        }

        Schema::drop('psm_detail');
    }

    public function down(): void
    {
        if (Schema::hasTable('psm_detail')) {
            return;
        }

        Schema::create('psm_detail', function (Blueprint $table) {
            $table->id('psm_detail_id');
            $table->string('name', 100);
            $table->text('details')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('enabled')->default(1);
            $table->string('input_type', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
