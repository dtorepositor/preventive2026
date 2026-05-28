<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_maintenance_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('user_operator')->nullable();
            $table->string('office_college')->nullable();
            $table->string('department')->nullable();
            $table->date('date_acquired')->nullable();
            $table->date('checklist_date')->nullable();
            $table->string('pc_name')->nullable();

            // Equipment installed (checkboxes)
            $table->boolean('equipment_cpu')->default(false);
            $table->boolean('equipment_keyboard')->default(false);
            $table->boolean('equipment_monitor')->default(false);
            $table->boolean('equipment_mouse')->default(false);
            $table->boolean('equipment_printer')->default(false);
            $table->boolean('equipment_ups')->default(false);
            $table->boolean('equipment_avr')->default(false);
            $table->boolean('equipment_others')->default(false);
            $table->string('equipment_others_specify')->nullable();

            // Operating system
            $table->boolean('os_windows_7')->default(false);
            $table->boolean('os_windows_8')->default(false);
            $table->boolean('os_windows_10')->default(false);
            $table->boolean('os_windows_11')->default(false);
            $table->boolean('os_others')->default(false);
            $table->string('os_others_specify')->nullable();

            // Software
            $table->boolean('software_enrollment')->default(false);
            $table->boolean('software_media_player')->default(false);
            $table->boolean('software_adobe_reader')->default(false);
            $table->boolean('software_antivirus')->default(false);
            $table->boolean('software_word_processor')->default(false);
            $table->boolean('software_browser')->default(false);
            $table->boolean('software_others')->default(false);
            $table->string('software_others_specify')->nullable();

            // Desktop/Laptop specifications
            $table->string('processor')->nullable();
            $table->string('motherboard')->nullable();
            $table->string('memory')->nullable();
            $table->string('graphics_card')->nullable();
            $table->string('hard_disk')->nullable();
            $table->string('optical_drives')->nullable();
            $table->string('monitor')->nullable();
            $table->string('casing')->nullable();
            $table->string('power_supply_watts')->nullable();
            $table->string('keyboard')->nullable();
            $table->string('mouse')->nullable();
            $table->string('avr_watts')->nullable();
            $table->string('ups')->nullable();
            $table->string('printer')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_checklists');
    }
};
