<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\OperatingSystem;
use App\Models\SoftwareApplication;
use App\Models\SpecificationField;
use App\Models\SpecificationCategory;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSpecificationCategories();
        $this->seedEquipment();
        $this->seedOperatingSystems();
        $this->seedSoftwareApplications();
        $this->seedSpecificationFields();
    }

    private function seedSpecificationCategories(): void
    {
        $categories = [
            ['name' => 'equipment_installed', 'label' => 'Equipment Installed', 'category_type' => 'equipment', 'sort_order' => 1],
            ['name' => 'operating_system_installed', 'label' => 'Operating System Installed', 'category_type' => 'operating_system', 'sort_order' => 2],
            ['name' => 'software_applications_installed', 'label' => 'Software/Applications Installed', 'category_type' => 'software', 'sort_order' => 3],
            ['name' => 'desktop_laptop_specifications', 'label' => 'Desktop/Laptop Specifications', 'category_type' => 'specification', 'sort_order' => 4],
        ];

        foreach ($categories as $item) {
            SpecificationCategory::firstOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['enabled' => true])
            );
        }
    }

    private function seedEquipment(): void
    {
        $equipment = [
            ['name' => 'CPU', 'sort_order' => 1],
            ['name' => 'KEYBOARD', 'sort_order' => 2],
            ['name' => 'MONITOR', 'sort_order' => 3],
            ['name' => 'MOUSE', 'sort_order' => 4],
            ['name' => 'PRINTER', 'sort_order' => 5],
            ['name' => 'UPS', 'sort_order' => 6],
            ['name' => 'AVR', 'sort_order' => 7],
        ];

        foreach ($equipment as $item) {
            Equipment::firstOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['enabled' => true])
            );
        }
    }

    private function seedOperatingSystems(): void
    {
        $systems = [
            ['name' => 'Windows 7', 'sort_order' => 1],
            ['name' => 'Windows 8', 'sort_order' => 2],
            ['name' => 'Windows 10', 'sort_order' => 3],
        ];

        foreach ($systems as $item) {
            OperatingSystem::updateOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['enabled' => true])
            );
        }

        OperatingSystem::whereIn('name', ['Windows 11', 'Windows 11 Pro 64-bit'])
            ->update(['enabled' => false]);
    }

    private function seedSoftwareApplications(): void
    {
        $software = [
            ['name' => 'Enrollment System', 'sort_order' => 1],
            ['name' => 'Media Player', 'sort_order' => 2],
            ['name' => 'Adobe Reader', 'sort_order' => 3],
            ['name' => 'Anti-Virus', 'sort_order' => 4],
            ['name' => 'Word Processor', 'sort_order' => 5],
            ['name' => 'Browser', 'sort_order' => 6],
        ];

        foreach ($software as $item) {
            SoftwareApplication::firstOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['enabled' => true])
            );
        }
    }

    private function seedSpecificationFields(): void
    {
        $fields = [
            ['name' => 'processor', 'label' => 'Processor', 'sort_order' => 1],
            ['name' => 'motherboard', 'label' => 'Motherboard', 'sort_order' => 2],
            ['name' => 'memory', 'label' => 'Memory', 'sort_order' => 3],
            ['name' => 'graphics_card', 'label' => 'Graphics Card', 'sort_order' => 4],
            ['name' => 'hard_disk', 'label' => 'Hard Disk', 'sort_order' => 5],
            ['name' => 'optical_drives', 'label' => 'Optical Drives', 'placeholder' => 'e.g. No optical disk drives detected', 'sort_order' => 6],
            ['name' => 'monitor', 'label' => 'Monitor', 'sort_order' => 7],
            ['name' => 'casing', 'label' => 'Casing', 'sort_order' => 8],
            ['name' => 'power_supply_watts', 'label' => 'Power Supply (watts)', 'sort_order' => 9],
            ['name' => 'keyboard', 'label' => 'Keyboard', 'sort_order' => 10],
            ['name' => 'mouse', 'label' => 'Mouse', 'sort_order' => 11],
            ['name' => 'avr_watts', 'label' => 'AVR (watts)', 'sort_order' => 12],
            ['name' => 'ups', 'label' => 'UPS', 'sort_order' => 13],
            ['name' => 'printer', 'label' => 'Printer', 'sort_order' => 14],
            ['name' => 'mac_address', 'label' => 'MAC Address', 'sort_order' => 15],
            ['name' => 'ip_address', 'label' => 'IP Address', 'sort_order' => 16],
        ];

        foreach ($fields as $item) {
            SpecificationField::firstOrCreate(
                ['name' => $item['name']],
                array_merge($item, ['enabled' => true, 'input_type' => 'text'])
            );
        }
    }
}
