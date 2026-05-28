<?php

namespace Database\Seeders;

use App\Data\ItemChecklistTemplate;
use App\Models\Psm;
use App\Models\PsmVariable;
use Illuminate\Database\Seeder;

class PsmTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $pmTemplate = Psm::firstOrCreate(
            ['psm_id' => 1],
            [
                'name' => 'Preventive Maintenance Checklist',
                'detail' => 'Office of Digital Transformation - CMU',
                'enabled' => 1,
                'type' => 'template',
                'template_psm_id' => null,
            ]
        );
        $this->seedPreventiveMaintenanceVariables($pmTemplate->psm_id);

        $itemTemplate = Psm::firstOrCreate(
            ['psm_id' => 2],
            [
                'name' => 'Item Checklist',
                'detail' => 'ICT Equipment maintenance item checklist',
                'enabled' => 1,
                'type' => 'template',
                'template_psm_id' => null,
            ]
        );
        $this->seedItemChecklistVariables($itemTemplate->psm_id);
    }

    private function seedPreventiveMaintenanceVariables(int $psmId): void
    {
        $vars = [
            ['name' => 'user_operator', 'description' => 'User/Operator', 'input_type' => 'text'],
            ['name' => 'college_office_id', 'description' => 'College/Office ID', 'input_type' => 'hidden'],
            ['name' => 'office_college', 'description' => 'Office/College', 'input_type' => 'text'],
            ['name' => 'department_id', 'description' => 'Department ID', 'input_type' => 'hidden'],
            ['name' => 'department', 'description' => 'Department', 'input_type' => 'text'],
            ['name' => 'date_acquired', 'description' => 'Date Acquired', 'input_type' => 'date'],
            ['name' => 'checklist_date', 'description' => 'Date (Checklist)', 'input_type' => 'date'],
            ['name' => 'checklist_type', 'description' => 'Checklist Type', 'input_type' => 'select'],
            ['name' => 'pc_name', 'description' => 'PC Name', 'input_type' => 'text'],
            ['name' => 'equipment_cpu', 'description' => 'CPU', 'input_type' => 'checkbox'],
            ['name' => 'equipment_keyboard', 'description' => 'Keyboard', 'input_type' => 'checkbox'],
            ['name' => 'equipment_monitor', 'description' => 'Monitor', 'input_type' => 'checkbox'],
            ['name' => 'equipment_mouse', 'description' => 'Mouse', 'input_type' => 'checkbox'],
            ['name' => 'equipment_printer', 'description' => 'Printer', 'input_type' => 'checkbox'],
            ['name' => 'equipment_ups', 'description' => 'UPS', 'input_type' => 'checkbox'],
            ['name' => 'equipment_avr', 'description' => 'AVR', 'input_type' => 'checkbox'],
            ['name' => 'equipment_others', 'description' => 'Others', 'input_type' => 'checkbox'],
            ['name' => 'equipment_others_specify', 'description' => 'Others (Specify)', 'input_type' => 'text'],
            ['name' => 'os_windows_7', 'description' => 'Windows 7', 'input_type' => 'checkbox'],
            ['name' => 'os_windows_8', 'description' => 'Windows 8', 'input_type' => 'checkbox'],
            ['name' => 'os_windows_10', 'description' => 'Windows 10', 'input_type' => 'checkbox'],
            ['name' => 'os_others', 'description' => 'OS Others', 'input_type' => 'checkbox'],
            ['name' => 'os_others_specify', 'description' => 'OS Others (Specify)', 'input_type' => 'text'],
            ['name' => 'software_enrollment', 'description' => 'Enrollment System', 'input_type' => 'checkbox'],
            ['name' => 'software_media_player', 'description' => 'Media Player', 'input_type' => 'checkbox'],
            ['name' => 'software_adobe_reader', 'description' => 'Adobe Reader', 'input_type' => 'checkbox'],
            ['name' => 'software_antivirus', 'description' => 'Anti-Virus', 'input_type' => 'checkbox'],
            ['name' => 'software_word_processor', 'description' => 'Word Processor', 'input_type' => 'checkbox'],
            ['name' => 'software_browser', 'description' => 'Browser', 'input_type' => 'checkbox'],
            ['name' => 'software_others', 'description' => 'Software Others', 'input_type' => 'checkbox'],
            ['name' => 'software_others_specify', 'description' => 'Software Others (Specify)', 'input_type' => 'text'],
            ['name' => 'processor', 'description' => 'Processor', 'input_type' => 'text'],
            ['name' => 'motherboard', 'description' => 'Motherboard', 'input_type' => 'text'],
            ['name' => 'memory', 'description' => 'Memory', 'input_type' => 'text'],
            ['name' => 'graphics_card', 'description' => 'Graphics Card', 'input_type' => 'text'],
            ['name' => 'hard_disk', 'description' => 'Hard Disk', 'input_type' => 'text'],
            ['name' => 'optical_drives', 'description' => 'Optical Drives', 'input_type' => 'text'],
            ['name' => 'monitor', 'description' => 'Monitor', 'input_type' => 'text'],
            ['name' => 'casing', 'description' => 'Casing', 'input_type' => 'text'],
            ['name' => 'power_supply_watts', 'description' => 'Power Supply (watts)', 'input_type' => 'text'],
            ['name' => 'keyboard', 'description' => 'Keyboard', 'input_type' => 'text'],
            ['name' => 'mouse', 'description' => 'Mouse', 'input_type' => 'text'],
            ['name' => 'avr_watts', 'description' => 'AVR (watts)', 'input_type' => 'text'],
            ['name' => 'ups', 'description' => 'UPS', 'input_type' => 'text'],
            ['name' => 'printer', 'description' => 'Printer', 'input_type' => 'text'],
            ['name' => 'mac_address', 'description' => 'MAC Address', 'input_type' => 'text'],
            ['name' => 'ip_address', 'description' => 'IP Address', 'input_type' => 'text'],
            // Network Device Fields
            ['name' => 'network_device_category_type', 'description' => 'Network Device Category Type', 'input_type' => 'select'],
            ['name' => 'network_device_product_name', 'description' => 'Network Device Product Name', 'input_type' => 'text'],
            ['name' => 'network_device_model_name', 'description' => 'Network Device Model Name', 'input_type' => 'text'],
            ['name' => 'network_device_serial', 'description' => 'Network Device Serial Number', 'input_type' => 'text'],
            ['name' => 'network_device_office_location', 'description' => 'Network Device Office Location', 'input_type' => 'text'],
            ['name' => 'network_device_vlan', 'description' => 'Network Device VLAN', 'input_type' => 'text'],
        ];

        foreach ($vars as $v) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $psmId, 'name' => $v['name']],
                array_merge($v, ['enabled' => 1])
            );
        }
    }

    private function seedItemChecklistVariables(int $psmId): void
    {
        $vars = [
            ['name' => 'parent_psm_id', 'description' => 'Parent PM submission', 'input_type' => 'hidden'],
            ['name' => 'maintenance_date', 'description' => 'Maintenance Date', 'input_type' => 'date'],
            ['name' => 'maintenance_month', 'description' => 'Maintenance Month', 'input_type' => 'month'],
            ['name' => 'summary_recommendation', 'description' => 'Summary/Recommendation', 'input_type' => 'textarea'],
            ['name' => 'checked_by', 'description' => 'Checked by', 'input_type' => 'text'],
            ['name' => 'conforme_by', 'description' => 'Conforme', 'input_type' => 'text'],
            ['name' => 'noted_by', 'description' => 'Noted by', 'input_type' => 'text'],
        ];
        foreach ($vars as $v) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $psmId, 'name' => $v['name']],
                array_merge($v, ['enabled' => 1])
            );
        }

        foreach (ItemChecklistTemplate::defaultEntries() as $i => $entry) {
            $name = 'item_' . $i;
            PsmVariable::firstOrCreate(
                ['psm_id' => $psmId, 'name' => $name],
                [
                    'description' => $entry['task'] . ': ' . $entry['description'],
                    'enabled' => 1,
                    'input_type' => 'radio',
                ]
            );
        }
    }
}
