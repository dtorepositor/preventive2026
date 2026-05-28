<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Equipment;
use App\Models\OperatingSystem;
use App\Models\PreventiveMaintenanceRevision;
use App\Models\Psm;
use App\Models\PsmValue;
use App\Models\PsmVariable;
use App\Models\SoftwareApplication;
use App\Models\SpecificationField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreventiveMaintenanceController extends Controller
{
    private const TEMPLATE_PSM_ID = 1;

    private function operatingSystemsQuery()
    {
        return OperatingSystem::where('enabled', true)
            ->whereNotIn('name', ['Windows 11', 'Windows 11 Pro 64-bit'])
            ->orderBy('sort_order');
    }

    public function index()
    {
        $templateId = $this->getTemplateId();
        $checklists = Psm::where('template_psm_id', $templateId)
            ->with('values.variable')
            ->latest('psm_id')
            ->paginate(20);

        return view('preventive-maintenance.index', compact('checklists'));
    }

    private function getTemplateId(): int
    {
        $template = Psm::where('type', 'template')
            ->where('name', 'Preventive Maintenance Checklist')
            ->first();

        return $template ? (int) $template->psm_id : self::TEMPLATE_PSM_ID;
    }

    public function create()
    {
        $this->syncTemplateVariables();
        $templateId = $this->getTemplateId();
        $template = Psm::with('variables')->find($templateId);
        if (! $template) {
            abort(404, 'Preventive Maintenance template not found. Run: php artisan db:seed --class=PsmTemplateSeeder');
        }

        $submission = new Psm([
            'name' => '',
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);
        $valueMap = [];

        return view('preventive-maintenance.form', [
            'checklist' => $this->toChecklistData($submission, $valueMap),
            'submission' => null,
            'isEdit' => false,
            ...$this->referenceData(),
        ]);
    }

    public function store(Request $request)
    {
        $this->syncTemplateVariables();
        $currentDate = now(config('app.timezone'))->toDateString();
        $request->merge([
            'checklist_date' => $currentDate,
            'date_acquired' => $currentDate,
        ]);
        $validated = $this->validateChecklist($request);
        $templateId = $this->getTemplateId();
        
        // Determine the submission name based on checklist type
        $name = 'Untitled';
        if ($validated['checklist_type'] === 'network_device' && !empty($validated['network_device_product_name'])) {
            $name = $validated['network_device_product_name'];
        } elseif ($validated['checklist_type'] === 'wifi' && !empty($validated['wifi_product_name'])) {
            $name = $validated['wifi_product_name'];
        } elseif ($validated['checklist_type'] === 'ups' && !empty($validated['ups_brand_name'])) {
            $name = trim($validated['ups_brand_name'] . ' ' . ($validated['ups_model_name'] ?? ''));
        } elseif ($validated['checklist_type'] === 'cctv' && !empty($validated['cctv_product_name'])) {
            $name = $validated['cctv_product_name'];
        } elseif (!empty($validated['pc_name'])) {
            $name = $validated['pc_name'];
        }
        
        $submission = Psm::create([
            'name' => $name,
            'detail' => null,
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => $templateId,
        ]);

        $template = Psm::with('variables')->find($templateId);
        foreach ($template->variables as $var) {
            $value = $this->getRequestValueForVar($var->name, $validated);
            if ($value !== null && $value !== '') {
                PsmValue::updateOrCreate([
                    'psm_id' => $submission->psm_id,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => $value,
                    'status' => null,
                ]);
            }
        }

        $submission->persistPreventiveMaintenanceIdentifier($validated['checklist_type'] ?? null);

        return redirect()->route('preventive-maintenance.show', $submission)
            ->with('success', 'Preventive Maintenance Checklist saved successfully.');
    }

    public function show(Psm $preventive_maintenance)
    {
        $this->syncTemplateVariables();
        $this->ensurePmSubmission($preventive_maintenance);
        $preventive_maintenance->load('values.variable');
        $valueMap = $preventive_maintenance->getValueMap();

        $itemChecklists = Psm::with('values.variable')
            ->where('type', 'submission')
            ->where('template_psm_id', 2)
            ->whereHas('values', fn ($q) => $q->where('value', (string) $preventive_maintenance->psm_id)->whereHas('variable', fn ($v) => $v->where('name', 'parent_psm_id')))
            ->get()
            ->sortByDesc(function ($item) {
                $valueMap = $item->getValueMap();
                return $valueMap['maintenance_date'] ?? $item->psm_id;
            })
            ->values();

        return view('preventive-maintenance.show', [
            'checklist' => $this->toChecklistData($preventive_maintenance, $valueMap),
            'submission' => $preventive_maintenance,
            'itemChecklists' => $itemChecklists,
            ...$this->referenceData(),
        ]);
    }

    public function edit(Psm $preventive_maintenance)
    {
        $this->syncTemplateVariables();
        $this->ensurePmSubmission($preventive_maintenance);
        $preventive_maintenance->load('values.variable');
        $valueMap = $preventive_maintenance->getValueMap();

        return view('preventive-maintenance.form', [
            'checklist' => $this->toChecklistData($preventive_maintenance, $valueMap),
            'submission' => $preventive_maintenance,
            'isEdit' => true,
            ...$this->referenceData(),
        ]);
    }

    private function referenceData(): array
    {
        return [
            'equipment' => Equipment::where('enabled', true)->orderBy('sort_order')->get(),
            'operatingSystems' => $this->operatingSystemsQuery()->get(),
            'softwareApplications' => SoftwareApplication::where('enabled', true)->orderBy('sort_order')->get(),
            'specificationFields' => SpecificationField::where('enabled', true)->orderBy('sort_order')->get(),
        ];
    }

    public function update(Request $request, Psm $preventive_maintenance)
    {
        $this->ensurePmSubmission($preventive_maintenance);
        $this->syncTemplateVariables();
        $validated = $this->validateChecklist($request);

        // Determine the submission name based on checklist type
        $name = 'Untitled';
        if ($validated['checklist_type'] === 'network_device' && !empty($validated['network_device_product_name'])) {
            $name = $validated['network_device_product_name'];
        } elseif ($validated['checklist_type'] === 'wifi' && !empty($validated['wifi_product_name'])) {
            $name = $validated['wifi_product_name'];
        } elseif ($validated['checklist_type'] === 'ups' && !empty($validated['ups_brand_name'])) {
            $name = trim($validated['ups_brand_name'] . ' ' . ($validated['ups_model_name'] ?? ''));
        } elseif ($validated['checklist_type'] === 'cctv' && !empty($validated['cctv_product_name'])) {
            $name = $validated['cctv_product_name'];
        } elseif (!empty($validated['pc_name'])) {
            $name = $validated['pc_name'];
        }

        $this->snapshotPreventiveMaintenance($preventive_maintenance);

        $preventive_maintenance->update(['name' => $name]);

        $template = Psm::with('variables')->find($this->getTemplateId());
        foreach ($template->variables as $var) {
            $value = $this->getRequestValueForVar($var->name, $validated);
            $existing = PsmValue::where('psm_id', $preventive_maintenance->psm_id)->where('psm_var_id', $var->psm_var_id)->first();
            if ($existing) {
                $existing->update(['value' => $value ?? '']);
            } elseif ($value !== null) {
                PsmValue::updateOrCreate([
                    'psm_id' => $preventive_maintenance->psm_id,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => $value,
                    'status' => null,
                ]);
            }
        }

        $preventive_maintenance->persistPreventiveMaintenanceIdentifier($validated['checklist_type'] ?? null);

        return redirect()->route('preventive-maintenance.show', $preventive_maintenance)
            ->with('success', 'Checklist updated successfully.');
    }

    public function destroy(Psm $preventive_maintenance)
    {
        $this->ensurePmSubmission($preventive_maintenance);
        $pmId = $preventive_maintenance->psm_id;

        Psm::where('type', 'submission')->where('template_psm_id', 2)
            ->whereHas('values', fn ($q) => $q->where('value', (string) $pmId)->whereHas('variable', fn ($v) => $v->where('name', 'parent_psm_id')))
            ->each(fn (Psm $p) => $p->delete());

        $preventive_maintenance->delete();

        return redirect()->route('preventive-maintenance.index')
            ->with('success', 'Checklist deleted.');
    }

    private function ensurePmSubmission(Psm $psm): void
    {
        if ($psm->type !== 'submission' || (int) $psm->template_psm_id !== $this->getTemplateId()) {
            abort(404);
        }
    }

    private function toChecklistData(Psm $submission, array $valueMap): object
    {
        $get = fn (string $key) => $valueMap[$key] ?? $submission->getValueByVarName($key) ?? null;
        $getDate = fn (string $key) => $get($key) ? Carbon::parse($get($key)) : null;

        $data = [
            'user_operator' => $get('user_operator'),
            'office_college' => $get('office_college'),
            'department' => $get('department'),
            'date_acquired' => $getDate('date_acquired'),
            'checklist_date' => $getDate('checklist_date'),
            'checklist_type' => $get('checklist_type'),
            'identifier' => $submission->preventiveMaintenanceIdentifier($get('checklist_type')),
            'pc_name' => $get('pc_name'),
            'mac_address' => $get('mac_address'),
            'ip_address' => $get('ip_address'),
            'equipment_others' => (bool) $get('equipment_others'),
            'equipment_others_specify' => $get('equipment_others_specify'),
            'os_others' => (bool) $get('os_others'),
            'os_others_specify' => $get('os_others_specify'),
            'software_others' => (bool) $get('software_others'),
            'software_others_specify' => $get('software_others_specify'),
            // Network Device fields
            'network_device_category_type' => $get('network_device_category_type'),
            'network_device_product_name' => $get('network_device_product_name'),
            'network_device_model_name' => $get('network_device_model_name'),
            'network_device_serial' => $get('network_device_serial'),
            'network_device_mac_address' => $get('network_device_mac_address'),
            'network_device_office_location' => $get('network_device_office_location'),
            'network_device_ip_address' => $get('network_device_ip_address'),
            'network_device_vlan' => $get('network_device_vlan'),
            // WiFi fields
            'wifi_category_type' => $get('wifi_category_type'),
            'wifi_product_name' => $get('wifi_product_name'),
            'wifi_model_name' => $get('wifi_model_name'),
            'wifi_serial' => $get('wifi_serial'),
            'wifi_mac_address' => $get('wifi_mac_address'),
            'wifi_office_location' => $get('wifi_office_location'),
            'wifi_ip_address' => $get('wifi_ip_address'),
            'wifi_vlan' => $get('wifi_vlan'),
            'wifi_name' => $get('wifi_name'),
            'wifi_password' => $get('wifi_password'),
            'wifi_channel_supported' => $get('wifi_channel_supported'),
            // UPS fields
            'ups_category' => $get('ups_category'),
            'ups_brand_name' => $get('ups_brand_name'),
            'ups_model_name' => $get('ups_model_name'),
            'ups_mac_address' => $get('ups_mac_address'),
            'ups_serial' => $get('ups_serial'),
            'ups_total_power_capacity' => $get('ups_total_power_capacity'),
            // CCTV fields
            'cctv_category_type' => $get('cctv_category_type'),
            'cctv_product_name' => $get('cctv_product_name'),
            'cctv_model_name' => $get('cctv_model_name'),
            'cctv_serial' => $get('cctv_serial'),
            'cctv_mac_address' => $get('cctv_mac_address'),
            'cctv_office_location' => $get('cctv_office_location'),
            'cctv_ip_address' => $get('cctv_ip_address'),
            'cctv_vlan' => $get('cctv_vlan'),
        ];

        // Add dynamic equipment fields
        $equipment = Equipment::where('enabled', true)->get();
        foreach ($equipment as $item) {
            $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name));
            $data[$fieldName] = (bool) $get($fieldName);
        }

        // Add dynamic OS fields
        $os = $this->operatingSystemsQuery()->get();
        foreach ($os as $item) {
            $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $data[$fieldName] = (bool) $get($fieldName);
        }

        // Add dynamic software fields
        $software = SoftwareApplication::where('enabled', true)->get();
        foreach ($software as $item) {
            $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $data[$fieldName] = (bool) $get($fieldName);
        }

        // Add specification fields
        $specFields = SpecificationField::where('enabled', true)->get();
        foreach ($specFields as $field) {
            $data[$field->name] = $get($field->name);
        }

        return (object) $data;
    }

    /**
     * Ensure the template PSM has variables for every dynamic checkbox/text field so newly added
     * equipment/OS/software/spec entries persist and show up in views.
     */
    private function syncTemplateVariables(): void
    {
        $templateId = $this->getTemplateId();

        // Base variable names used in the form
        $baseVars = [
            'user_operator', 'office_college', 'department', 'date_acquired', 'checklist_date', 'pc_name',
            'equipment_others', 'equipment_others_specify',
            'os_others', 'os_others_specify',
            'software_others', 'software_others_specify',
            'checklist_type', 'mac_address', 'ip_address',
            // Network Device fields
            'network_device_category_type', 'network_device_product_name', 'network_device_model_name',
            'network_device_serial', 'network_device_mac_address', 'network_device_office_location',
            'network_device_ip_address', 'network_device_vlan',
            // WiFi fields
            'wifi_category_type', 'wifi_product_name', 'wifi_model_name',
            'wifi_serial', 'wifi_mac_address', 'wifi_office_location',
            'wifi_ip_address', 'wifi_vlan', 'wifi_name', 'wifi_password',
            'wifi_channel_supported',
            // UPS fields
            'ups_category', 'ups_brand_name', 'ups_model_name',
            'ups_mac_address', 'ups_serial', 'ups_total_power_capacity',
            // CCTV fields
            'cctv_category_type', 'cctv_product_name', 'cctv_model_name',
            'cctv_serial', 'cctv_mac_address', 'cctv_office_location',
            'cctv_ip_address', 'cctv_vlan',
        ];

        // Dynamic checkbox fields
        $equipment = Equipment::where('enabled', true)->get()->map(fn ($e) => 'equipment_' . strtolower(str_replace(' ', '_', $e->name)))->all();
        $operatingSystems = $this->operatingSystemsQuery()->get()->map(fn ($o) => 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $o->name)))->all();
        $software = SoftwareApplication::where('enabled', true)->get()->map(fn ($s) => 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $s->name)))->all();

        // Dynamic specification text fields
        $specs = SpecificationField::where('enabled', true)->get()->map(fn ($s) => $s->name)->all();

        $allVars = array_unique(array_merge($baseVars, $equipment, $operatingSystems, $software, $specs));

        foreach ($allVars as $name) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templateId, 'name' => $name],
                ['description' => null, 'enabled' => true, 'input_type' => 'text']
            );
        }
    }

    private function snapshotPreventiveMaintenance(Psm $checklist): void
    {
        $checklist->loadMissing('values.variable');

        PreventiveMaintenanceRevision::create([
            'psm_id' => $checklist->psm_id,
            'name' => $checklist->name,
            'detail' => $checklist->detail,
            'values_snapshot' => $checklist->getValueMap(),
            'original_created_at' => $checklist->created_at,
        ]);
    }

    private function getRequestValueForVar(string $name, array $validated): ?string
    {
        if (array_key_exists($name, $validated)) {
            $v = $validated[$name];
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d');
            }
            if ($v === null || $v === '' || $v === 0 || $v === false) {
                return null;
            }
            return (string) $v;
        }
        return null;
    }

    private function validateChecklist(Request $request): array
    {
        $rules = [
            'checklist_type' => ['nullable', 'string', 'in:pc,server,ip_phone,network_device,wifi,ups,cctv'],
            'checklist_date' => ['nullable', 'date'],
            'user_operator' => ['nullable', 'string', 'max:255'],
            'office_college' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'date_acquired' => ['nullable', 'date'],
            'pc_name' => ['nullable', 'string', 'max:255'],
            'equipment_others_specify' => ['nullable', 'string', 'max:255'],
            'os_others_specify' => ['nullable', 'string', 'max:255'],
            'software_others_specify' => ['nullable', 'string', 'max:255'],
            // Network Device fields
            'network_device_category_type' => ['nullable', 'string', 'max:255'],
            'network_device_product_name' => ['nullable', 'string', 'max:255'],
            'network_device_model_name' => ['nullable', 'string', 'max:255'],
            'network_device_serial' => ['nullable', 'string', 'max:255'],
            'network_device_mac_address' => ['nullable', 'string', 'max:255'],
            'network_device_office_location' => ['nullable', 'string', 'max:255'],
            'network_device_ip_address' => ['nullable', 'string', 'max:255'],
            'network_device_vlan' => ['nullable', 'string', 'max:255'],
            // WiFi fields
            'wifi_category_type' => ['nullable', 'string', 'max:255'],
            'wifi_product_name' => ['nullable', 'string', 'max:255'],
            'wifi_model_name' => ['nullable', 'string', 'max:255'],
            'wifi_serial' => ['nullable', 'string', 'max:255'],
            'wifi_mac_address' => ['nullable', 'string', 'max:255'],
            'wifi_office_location' => ['nullable', 'string', 'max:255'],
            'wifi_ip_address' => ['nullable', 'string', 'max:255'],
            'wifi_vlan' => ['nullable', 'string', 'max:255'],
            'wifi_name' => ['nullable', 'string', 'max:255'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
            'wifi_channel_supported' => ['nullable', 'string', 'max:255'],
            // UPS fields
            'ups_category' => ['nullable', 'string', 'max:255'],
            'ups_brand_name' => ['nullable', 'string', 'max:255'],
            'ups_model_name' => ['nullable', 'string', 'max:255'],
            'ups_mac_address' => ['nullable', 'string', 'max:255'],
            'ups_serial' => ['nullable', 'string', 'max:255'],
            'ups_total_power_capacity' => ['nullable', 'string', 'max:255'],
            // CCTV fields
            'cctv_category_type' => ['nullable', 'string', 'max:255'],
            'cctv_product_name' => ['nullable', 'string', 'max:255'],
            'cctv_model_name' => ['nullable', 'string', 'max:255'],
            'cctv_serial' => ['nullable', 'string', 'max:255'],
            'cctv_mac_address' => ['nullable', 'string', 'max:255'],
            'cctv_office_location' => ['nullable', 'string', 'max:255'],
            'cctv_ip_address' => ['nullable', 'string', 'max:255'],
            'cctv_vlan' => ['nullable', 'string', 'max:255'],
        ];

        // Add specification fields dynamically
        $specFields = SpecificationField::where('enabled', true)->get();
        foreach ($specFields as $field) {
            $rules[$field->name] = ['nullable', 'string', 'max:500'];
        }

        // Add equipment fields dynamically
        $equipment = Equipment::where('enabled', true)->get();
        foreach ($equipment as $item) {
            $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name));
            $rules[$fieldName] = ['nullable', Rule::in(['0', '1', 0, 1, true, false])];
        }

        // Add operating system fields dynamically
        $os = $this->operatingSystemsQuery()->get();
        foreach ($os as $item) {
            $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $rules[$fieldName] = ['nullable', Rule::in(['0', '1', 0, 1, true, false])];
        }
        $rules['os_others'] =  ['nullable', Rule::in(['0', '1', 0, 1, true, false])];

        // Add software fields dynamically
        $software = SoftwareApplication::where('enabled', true)->get();
        foreach ($software as $item) {
            $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $rules[$fieldName] = ['nullable', Rule::in(['0', '1', 0, 1, true, false])];
        }
        $rules['software_others'] = ['nullable', Rule::in(['0', '1', 0, 1, true, false])];

        $validated = $request->validate($rules);

        // Convert boolean strings for all equipment, OS, and software fields
        foreach ($equipment as $item) {
            $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name));
            $validated[$fieldName] = (bool) ($request->input($fieldName) ?? false);
        }
        foreach ($os as $item) {
            $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $validated[$fieldName] = (bool) ($request->input($fieldName) ?? false);
        }
        $validated['os_others'] = (bool) ($request->input('os_others') ?? false);

        foreach ($software as $item) {
            $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $validated[$fieldName] = (bool) ($request->input($fieldName) ?? false);
        }
        $validated['software_others'] = (bool) ($request->input('software_others') ?? false);

        return $validated;
    }
}
