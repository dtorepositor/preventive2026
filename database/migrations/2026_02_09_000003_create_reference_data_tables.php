<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Equipment types table
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Operating Systems table
        Schema::create('operating_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Software/Applications table
        Schema::create('software_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Desktop/Laptop Specification Fields table
        Schema::create('specification_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('input_type')->default('text');
            $table->string('placeholder')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Many-to-many junction tables
        Schema::create('psm_equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psm_id');
            $table->unsignedBigInteger('equipment_id');
            $table->timestamps();
            
            $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            $table->unique(['psm_id', 'equipment_id']);
        });

        Schema::create('psm_operating_systems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psm_id');
            $table->unsignedBigInteger('operating_system_id');
            $table->timestamps();
            
            $table->foreign('operating_system_id')->references('id')->on('operating_systems')->cascadeOnDelete();
            $table->unique(['psm_id', 'operating_system_id']);
        });

        Schema::create('psm_software_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psm_id');
            $table->unsignedBigInteger('software_application_id');
            $table->timestamps();
            
            $table->foreign('software_application_id')->references('id')->on('software_applications')->cascadeOnDelete();
            $table->unique(['psm_id', 'software_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psm_software_applications');
        Schema::dropIfExists('psm_operating_systems');
        Schema::dropIfExists('psm_equipment');
        Schema::dropIfExists('specification_fields');
        Schema::dropIfExists('software_applications');
        Schema::dropIfExists('operating_systems');
        Schema::dropIfExists('equipment');
    }
};
