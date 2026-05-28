<?php

namespace App\Http\Controllers;

use App\Models\Psm;
use App\Models\PsmValue;
use App\Models\ItemChecklistItem;
use App\Models\PsmVariable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemChecklistController extends Controller
{
    private const ITEM_TEMPLATE_PSM_ID = 2;

    public function create(Psm $preventive_maintenance)
    {
        $this->syncItemTemplateVariables();
        $this->ensurePmSubmission($preventive_maintenance);

        $template = Psm::with('variables')->find(self::ITEM_TEMPLATE_PSM_ID);
        if (! $template) {
            abort(404, 'Item Checklist template not found. Run: php artisan db:seed --class=PsmTemplateSeeder');
        }

        $itemChecklist = new Psm([
            'name' => 'Item Checklist',
            'type' => 'submission',
            'template_psm_id' => self::ITEM_TEMPLATE_PSM_ID,
        ]);

        $entries = $this->mapItemEntries($this->enabledItemEntries());

        return view('item-checklist.form', [
            'preventiveMaintenance' => $preventive_maintenance,
            'itemChecklist' => $itemChecklist,
            'entries' => $entries,
            'valueMap' => [],
            'isEdit' => false,
        ]);
    }

    public function store(Request $request, Psm $preventive_maintenance)
    {
        $this->syncItemTemplateVariables();
        $this->ensurePmSubmission($preventive_maintenance);

        $currentDate = now(config('app.timezone'))->toDateString();
        $request->merge(['maintenance_date' => $currentDate]);
        $this->validateItemChecklistRequest($request);

        $submission = Psm::create([
            'name' => 'Item Checklist - ' . ($preventive_maintenance->name ?: $preventive_maintenance->psm_id),
            'detail' => null,
            'enabled' => 1,
            'type' => 'submission',
            'template_psm_id' => self::ITEM_TEMPLATE_PSM_ID,
        ]);

        $template = Psm::with('variables')->find(self::ITEM_TEMPLATE_PSM_ID);
        $varByName = $template->variables->keyBy('name');

        $this->setValue($submission->psm_id, $varByName, 'parent_psm_id', (string) $preventive_maintenance->psm_id);
        $this->setValue($submission->psm_id, $varByName, 'maintenance_date', $currentDate);
        $this->setValue($submission->psm_id, $varByName, 'summary_recommendation', $request->input('summary_recommendation'));
        $this->setValue($submission->psm_id, $varByName, 'checked_by', $request->input('checked_by'));
        $this->setValue($submission->psm_id, $varByName, 'conforme_by', $request->input('conforme_by'));

        $dbEntries = $this->enabledItemEntries();

        foreach ($dbEntries as $i => $entry) {
            $status = $request->input("entries.{$i}.status");
            $var = $varByName->get('item_' . $i);
            if ($var && $status) {
                PsmValue::updateOrCreate([
                    'psm_id' => $submission->psm_id,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => $status,
                    'status' => $status,
                ]);
            }
        }

        // Item checklist data is stored in PSM/PSM_VALUE only.

        return redirect()->route('preventive-maintenance.show', $preventive_maintenance)
            ->with('success', 'Item Checklist saved successfully.');
    }

    public function edit(Psm $item_checklist)
    {
        $this->syncItemTemplateVariables();
        $this->ensureItemChecklistSubmission($item_checklist);
        $item_checklist->load('values.variable');

        $valueMap = $item_checklist->getValueMap();
        $parentId = $valueMap['parent_psm_id'] ?? null;
        $preventiveMaintenance = $parentId ? Psm::find($parentId) : null;
        if (! $preventiveMaintenance) {
            abort(404, 'Parent checklist not found.');
        }

        $entries = $this->mapItemEntries($this->enabledItemEntries(), $valueMap);

        return view('item-checklist.form', [
            'preventiveMaintenance' => $preventiveMaintenance,
            'itemChecklist' => $item_checklist,
            'entries' => $entries,
            'valueMap' => $valueMap,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Psm $item_checklist)
    {
        $this->syncItemTemplateVariables();
        $this->ensureItemChecklistSubmission($item_checklist);
        $item_checklist->load('values.variable');

        $this->validateItemChecklistRequest($request);

        $valueMap = $item_checklist->getValueMap();
        $parentId = $valueMap['parent_psm_id'] ?? null;
        $preventiveMaintenance = $parentId ? Psm::find($parentId) : null;

        $template = Psm::with('variables')->find(self::ITEM_TEMPLATE_PSM_ID);
        $templateVarByName = $template->variables->keyBy('name');

        $this->updateOrCreateValue($item_checklist->psm_id, $item_checklist->values, $templateVarByName, 'maintenance_date', $request->input('maintenance_date'));
        $this->updateOrCreateValue($item_checklist->psm_id, $item_checklist->values, $templateVarByName, 'summary_recommendation', $request->input('summary_recommendation'));
        $this->updateOrCreateValue($item_checklist->psm_id, $item_checklist->values, $templateVarByName, 'checked_by', $request->input('checked_by'));
        $this->updateOrCreateValue($item_checklist->psm_id, $item_checklist->values, $templateVarByName, 'conforme_by', $request->input('conforme_by'));

        $dbEntries = $this->enabledItemEntries();

        foreach ($dbEntries as $i => $entry) {
            $status = $request->input("entries.{$i}.status");
            $var = $templateVarByName->get('item_' . $i);
            if (! $var) {
                continue;
            }
            $existing = $item_checklist->values->first(fn ($v) => $v->psm_var_id === $var->psm_var_id);
            if ($existing) {
                $existing->update(['value' => $status ?? '', 'status' => $status]);
            } elseif ($status) {
                PsmValue::updateOrCreate([
                    'psm_id' => $item_checklist->psm_id,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => $status,
                    'status' => $status,
                ]);
            }
        }

        // Item checklist data is stored in PSM/PSM_VALUE only.

        return redirect()->route('preventive-maintenance.show', $preventiveMaintenance)
            ->with('success', 'Item Checklist updated successfully.');
    }

    private function ensurePmSubmission(Psm $psm): void
    {
        if ($psm->type !== 'submission' || (int) $psm->template_psm_id !== 1) {
            abort(404);
        }
    }

    private function ensureItemChecklistSubmission(Psm $psm): void
    {
        if ($psm->type !== 'submission' || (int) $psm->template_psm_id !== self::ITEM_TEMPLATE_PSM_ID) {
            abort(404);
        }
    }

    private function setValue(int $psmId, $varByName, string $name, ?string $value): void
    {
        $var = $varByName->get($name);
        if ($var && $value !== null && $value !== '') {
            PsmValue::updateOrCreate([
                'psm_id' => $psmId,
                'psm_var_id' => $var->psm_var_id,
            ], [
                'value' => $value,
                'status' => null,
            ]);
        }
    }

    private function updateOrCreateValue(int $psmId, $values, $templateVarByName, string $name, ?string $value): void
    {
        $var = $templateVarByName->get($name);
        if (! $var) {
            return;
        }
        $existing = $values->first(fn ($v) => $v->variable?->name === $name);
        if ($existing) {
            $existing->update(['value' => $value ?? '']);
        } elseif ($value !== null && $value !== '') {
            PsmValue::updateOrCreate([
                'psm_id' => $psmId,
                'psm_var_id' => $var->psm_var_id,
            ], [
                'value' => $value,
                'status' => null,
            ]);
        }
    }

    /**
     * Ensure template variables exist for common fields and all enabled item rows,
     * so newly added tasks/descriptions get stored and rendered.
     */
    private function syncItemTemplateVariables(): void
    {
        $templateId = self::ITEM_TEMPLATE_PSM_ID;

        $baseVars = [
            'parent_psm_id', 'maintenance_date', 'summary_recommendation', 'checked_by', 'conforme_by',
        ];

        $items = $this->enabledItemEntries();

        $itemVars = $items->map(fn ($item, $i) => 'item_' . $i)->all();
        $allVars = array_unique(array_merge($baseVars, $itemVars));

        foreach ($allVars as $name) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templateId, 'name' => $name],
                ['description' => null, 'enabled' => true, 'input_type' => 'text']
            );
        }
    }

    private function validateItemChecklistRequest(Request $request): void
    {
        $request->validate([
            'maintenance_date' => ['nullable', 'date'],
            'summary_recommendation' => ['nullable', 'string'],
            'checked_by' => ['nullable', 'string', 'max:255'],
            'conforme_by' => ['nullable', 'string', 'max:255'],
            'entries' => ['required', 'array'],
            'entries.*.status' => ['nullable', Rule::in(['ok', 'repair', 'na'])],
        ]);
    }

    private function enabledItemEntries()
    {
        return ItemChecklistItem::where('enabled', true)
            ->orderBy('item_no')
            ->orderBy('sort_order')
            ->get()
            ->values();
    }

    private function mapItemEntries($items, array $valueMap = [])
    {
        return $items->map(fn ($e, $i) => [
            'item_no' => $e->item_no,
            'task' => $e->task,
            'description' => $e->description,
            'status' => $valueMap['item_' . $i] ?? null,
            'sort_order' => $e->sort_order,
            'var_name' => 'item_' . $i,
        ]);
    }
}
