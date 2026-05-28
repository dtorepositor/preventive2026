<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psm')) {
            Schema::create('psm', function (Blueprint $table) {
                $table->id('psm_id');
                $table->string('name', 100);
                $table->text('detail')->nullable();
                $table->tinyInteger('enabled')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->enum('type', ['template', 'submission'])->default('submission');
                $table->unsignedBigInteger('template_psm_id')->nullable();
            });

            // No FK on template_psm_id (self-reference) to avoid MySQL errno 150 during create
        } elseif (! Schema::hasColumn('psm', 'type')) {
            Schema::table('psm', function (Blueprint $table) {
                $table->enum('type', ['template', 'submission'])->default('submission')->after('created_at');
                $table->unsignedBigInteger('template_psm_id')->nullable()->after('type');
            });
        }

        if (! Schema::hasTable('psm_variable')) {
        Schema::create('psm_variable', function (Blueprint $table) {
            $table->id('psm_var_id');
            $table->unsignedBigInteger('psm_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->tinyInteger('enabled')->default(1);
            $table->string('input_type', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('psm_id')->references('psm_id')->on('psm')->cascadeOnDelete()->cascadeOnUpdate();
        });
        }

        if (! Schema::hasTable('psm_value')) {
        Schema::create('psm_value', function (Blueprint $table) {
            $table->id('psm_val_id');
            $table->unsignedBigInteger('psm_id');
            $table->unsignedBigInteger('psm_var_id');
            $table->text('value')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('psm_id')->references('psm_id')->on('psm')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('psm_var_id')->references('psm_var_id')->on('psm_variable')->cascadeOnDelete()->cascadeOnUpdate();
        });
        }

        if (! Schema::hasTable('psm_detail')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('psm_value');
        Schema::dropIfExists('psm_variable');
        Schema::dropIfExists('psm_detail');
        Schema::dropIfExists('psm');
    }
};
