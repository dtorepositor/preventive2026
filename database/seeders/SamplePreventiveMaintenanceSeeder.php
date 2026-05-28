<?php

namespace Database\Seeders;

use App\Models\Psm;
use App\Models\PsmValue;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SamplePreventiveMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $templateId = 1; // Main template ID

        // Sample PC
        $pcSubmission = Psm::create([
            'name' => 'Desktop-001',
            'detail' => 'Sample PC for demonstration',
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);

        $this->addValues($pcSubmission->psm_id, [
            'checklist_type' => 'pc',
            'user_operator' => 'John Doe',
            'office_college' => 'Information Technology',
            'department' => 'Infrastructure',
            'date_acquired' => '2023-06-15',
            'checklist_date' => now()->format('Y-m-d'),
            'pc_name' => 'Desktop-001',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'ip_address' => '192.168.1.100',
        ]);

        // Sample Server
        $serverSubmission = Psm::create([
            'name' => 'Server-Web-01',
            'detail' => 'Web server for demonstration',
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);

        $this->addValues($serverSubmission->psm_id, [
            'checklist_type' => 'server',
            'user_operator' => 'Jane Smith',
            'office_college' => 'Infrastructure',
            'department' => 'Systems',
            'date_acquired' => '2022-03-20',
            'checklist_date' => now()->format('Y-m-d'),
            'pc_name' => 'Server-Web-01',
            'mac_address' => '00:1A:2B:3C:4D:5F',
            'ip_address' => '192.168.100.50',
        ]);

        // Sample IP Phone
        $phoneSubmission = Psm::create([
            'name' => 'IP-Phone-302',
            'detail' => 'VoIP phone in office',
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);

        $this->addValues($phoneSubmission->psm_id, [
            'checklist_type' => 'ip_phone',
            'user_operator' => 'Robert Johnson',
            'office_college' => 'Communications',
            'department' => 'Telecommunications',
            'date_acquired' => '2023-01-10',
            'checklist_date' => now()->format('Y-m-d'),
            'brand_name' => 'Cisco',
            'model_name' => 'CP-7841',
            'serial_number' => 'FCH2410XXXX',
            'mac_address' => '00:25:86:XX:XX:XX',
            'office_located' => 'Building A, Room 302',
            'ip_address_tagged' => '192.168.2.30',
            'vlan' => 'VLAN 20',
            'telephone_number' => '+63-88-123456 ext. 302',
        ]);

        // Sample Network Device
        $networkSubmission = Psm::create([
            'name' => 'Managed Switch-Floor1',
            'detail' => 'Network switch for floor 1',
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);

        $this->addValues($networkSubmission->psm_id, [
            'checklist_type' => 'network_device',
            'user_operator' => 'Michael Brown',
            'office_college' => 'Infrastructure',
            'department' => 'Network Operations',
            'date_acquired' => '2022-11-05',
            'checklist_date' => now()->format('Y-m-d'),
            'network_device_category_type' => 'Managed POE Switch',
            'network_device_product_name' => 'Cisco Catalyst',
            'network_device_model_name' => 'C2960X-48TS-L',
            'network_device_serial' => 'FCW2222XXXX',
            'mac_address' => '00:26:98:XX:XX:XX',
            'network_device_office_location' => 'Building A, Network Room 1',
            'ip_address' => '192.168.100.10',
            'network_device_vlan' => 'VLAN 100 (Management)',
        ]);
    }

    private function addValues(int $psmId, array $values): void
    {
        $template = Psm::with('variables')->find(1);

        foreach ($template->variables as $var) {
            if (isset($values[$var->name])) {
                PsmValue::updateOrCreate([
                    'psm_id' => $psmId,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => (string) $values[$var->name],
                    'status' => null,
                ]);
            }
        }
    }
}
