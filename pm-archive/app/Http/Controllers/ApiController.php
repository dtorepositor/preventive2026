<?php

namespace App\Http\Controllers;

use App\Data\ItemChecklistTemplate;
use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade;
use Dompdf\Dompdf;
use App\Models\CollegeOffice;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\OperatingSystem;
use App\Models\SoftwareApplication;
use App\Models\SpecificationField;
use App\Models\SpecificationCategory;
use App\Models\ItemChecklistItem;
use App\Models\PreventiveMaintenanceRevision;
use App\Models\Psm;
use App\Models\PsmValue;
use App\Models\PsmVariable;
use App\Models\User;
use App\Models\IpPhoneItemChecklistItem;
use App\Models\NetworkDeviceItemChecklistItem;
use App\Models\ServerItemChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Tcpdf\Fpdi;

class ApiController extends Controller
{
    private const VIEW_PDF_TTL_SECONDS = 300;
    private const VIEW_PDF_CACHE_SECONDS = 300;
    private const DIRECTOR_NAME = 'Carlo Matin A. Sarausa';
    private const PREVENTIVE_MAINTENANCE_PHOTO_FIELD = 'maintenance_photo';
    private const REPORT_UNTAGGED_OFFICE_NAME = 'No College/Office Tagged';
    private const ITEM_CHECKLIST_COMMISSION_STATUS_DEFAULT = 'active';
    private const ITEM_CHECKLIST_COMMISSION_STATUSES = [
        'active',
        'for_repair',
        'under_maintenance',
        'defective',
        'replaced',
        'decommissioned',
        'na',
    ];

    private function pdfCacheControlHeader(bool $inline): string
    {
        return $inline
            ? 'no-cache, no-store, must-revalidate'
            : 'private, max-age=' . self::VIEW_PDF_CACHE_SECONDS;
    }

    private function currentAppDate(): string
    {
        return now(config('app.timezone'))->toDateString();
    }

    private function currentAppMonth(): string
    {
        return now(config('app.timezone'))->format('Y-m');
    }

    private function currentUserIsEncoder(): bool
    {
        return request()->user()?->isEncoder() === true;
    }

    private function abortUnlessCanAccessPsm(Psm $psm): void
    {
        $user = request()->user();

        if (! $user) {
            abort(401);
        }

        if ($user->isEncoder() && (int) $psm->created_by !== (int) $user->id) {
            abort(403, 'You can only access records you encoded.');
        }
    }

    private function abortIfPsmLocked(Psm $psm): void
    {
        if ($psm->is_locked) {
            abort(423, 'This record is locked. Unlock it before editing or deleting.');
        }
    }

    private function setPsmLockState(int $id, int $templatePsmId, bool $locked)
    {
        $psm = Psm::findOrFail($id);

        if ((int) $psm->template_psm_id !== $templatePsmId) {
            abort(404);
        }

        $psm->forceFill(['is_locked' => $locked])->save();

        return response()->json([
            'psm_id' => $psm->psm_id,
            'is_locked' => (bool) $psm->is_locked,
        ]);
    }

    private function parentPsmIdFromItemChecklist(Psm $itemChecklist, ?array $valueMap = null): ?int
    {
        $resolvedValueMap = $valueMap ?? $this->buildPsmValueMap($itemChecklist, true);
        $parentPsmId = $resolvedValueMap['parent_psm_id'] ?? null;

        return $parentPsmId ? (int) $parentPsmId : null;
    }

    private function defaultNotedByForChecklistType(?string $checklistType): string
    {
        return $this->normalizeChecklistType($checklistType) === 'pc' ? '' : self::DIRECTOR_NAME;
    }

    private function resolveItemChecklistNotedBy(array $valueMap, ?string $checklistType): string
    {
        $notedBy = trim((string) ($valueMap['noted_by'] ?? ''));

        return $notedBy !== '' ? $notedBy : $this->defaultNotedByForChecklistType($checklistType);
    }

    private function normalizeItemChecklistCommissionStatus($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return self::ITEM_CHECKLIST_COMMISSION_STATUS_DEFAULT;
        }

        $normalized = str_replace([' ', '-'], '_', $normalized);

        $aliases = [
            'forrepair' => 'for_repair',
            'undermaintenance' => 'under_maintenance',
            'n/a' => 'na',
            'n_a' => 'na',
        ];

        $normalized = $aliases[$normalized] ?? $normalized;

        return in_array($normalized, self::ITEM_CHECKLIST_COMMISSION_STATUSES, true)
            ? $normalized
            : self::ITEM_CHECKLIST_COMMISSION_STATUS_DEFAULT;
    }

    private function itemChecklistCommissionStatusLabel($value): string
    {
        return match ($this->normalizeItemChecklistCommissionStatus($value)) {
            'for_repair' => 'For Repair',
            'under_maintenance' => 'Under Maintenance',
            'defective' => 'Defective',
            'replaced' => 'Replaced',
            'decommissioned' => 'Decommissioned',
            'na' => 'N/A',
            default => 'Active',
        };
    }

    private function appTimeFromDate($date): ?string
    {
        return $date ? $date->timezone(config('app.timezone'))->format('h:i A') : null;
    }

    private function appDateFromDate($date): ?string
    {
        return $date ? $date->timezone(config('app.timezone'))->toDateString() : null;
    }

    private function itemChecklistIdentifier(Psm $itemChecklist, ?Psm $preventiveMaintenance = null, ?array $valueMap = null, ?array $pmValueMap = null): string
    {
        $itemChecklist->loadMissing(['values.variable']);

        if (! empty($itemChecklist->identifier)) {
            return (string) $itemChecklist->identifier;
        }

        $resolvedValueMap = $valueMap ?? $this->buildPsmValueMap($itemChecklist, true);
        $parentPsmId = (int) ($resolvedValueMap['parent_psm_id'] ?? $preventiveMaintenance?->psm_id ?? 0);

        if (! $preventiveMaintenance && $parentPsmId > 0) {
            $preventiveMaintenance = Psm::with(['values.variable'])->find($parentPsmId);
        }

        $preventiveMaintenance?->loadMissing(['values.variable']);
        $resolvedPmValueMap = $pmValueMap ?? ($preventiveMaintenance ? $this->buildPsmValueMap($preventiveMaintenance, true) : []);
        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance, $resolvedPmValueMap);
        $parentIdentifier = $preventiveMaintenance
            ? $preventiveMaintenance->preventiveMaintenanceIdentifier($checklistType)
            : sprintf('PM%s-%04d', Psm::preventiveMaintenanceCategoryCode($checklistType), $parentPsmId);

        $maintenanceDate = $resolvedValueMap['maintenance_date'] ?? $this->appDateFromDate($itemChecklist->created_at) ?? $this->currentAppDate();
        $dateCode = date('Ymd', strtotime($maintenanceDate));
        $sequence = $this->itemChecklistSequenceForDate($itemChecklist, $parentPsmId, $maintenanceDate);
        $identifierPrefix = sprintf('%s-%s', $parentIdentifier, $dateCode);
        $identifier = $this->availableItemChecklistIdentifier(
            $identifierPrefix,
            $sequence,
            (int) $itemChecklist->psm_id
        );

        if (Schema::hasColumn($itemChecklist->getTable(), 'identifier') && $itemChecklist->identifier !== $identifier) {
            for ($attempt = 0; $attempt < 20; $attempt++) {
                try {
                    Psm::where('psm_id', $itemChecklist->psm_id)->update(['identifier' => $identifier]);
                    $itemChecklist->identifier = $identifier;
                    break;
                } catch (UniqueConstraintViolationException $exception) {
                    $sequence++;
                    $identifier = $this->availableItemChecklistIdentifier(
                        $identifierPrefix,
                        $sequence,
                        (int) $itemChecklist->psm_id
                    );
                }
            }
        }

        return $identifier;
    }

    private function availableItemChecklistIdentifier(string $identifierPrefix, int $startingSequence, int $itemChecklistId): string
    {
        $sequence = max(1, $startingSequence);

        do {
            $identifier = sprintf('%s%03d', $identifierPrefix, $sequence);
            $exists = Psm::where('identifier', $identifier)
                ->where('psm_id', '<>', $itemChecklistId)
                ->exists();
            $sequence++;
        } while ($exists);

        return $identifier;
    }

    private function itemChecklistSequenceForDate(Psm $itemChecklist, int $parentPsmId, string $maintenanceDate): int
    {
        if ($parentPsmId <= 0) {
            return max(1, (int) $itemChecklist->psm_id);
        }

        $parentVarId = PsmVariable::where('psm_id', 2)
            ->where('name', 'parent_psm_id')
            ->value('psm_var_id');

        if (! $parentVarId) {
            return 1;
        }

        $itemChecklistIds = PsmValue::where('psm_var_id', $parentVarId)
            ->where('value', (string) $parentPsmId)
            ->pluck('psm_id');

        $siblings = Psm::whereIn('psm_id', $itemChecklistIds)
            ->with(['values.variable'])
            ->get()
            ->filter(function (Psm $sibling) use ($maintenanceDate) {
                $siblingValueMap = $sibling->getValueMap();
                $siblingDate = $siblingValueMap['maintenance_date'] ?? $this->appDateFromDate($sibling->created_at);

                return $siblingDate === $maintenanceDate;
            })
            ->sortBy(fn (Psm $sibling) => sprintf(
                '%020d-%020d',
                $sibling->created_at ? $sibling->created_at->getTimestamp() : 0,
                (int) $sibling->psm_id
            ))
            ->values();

        $index = $siblings->search(fn (Psm $sibling) => (int) $sibling->psm_id === (int) $itemChecklist->psm_id);

        return $index === false ? $siblings->count() + 1 : $index + 1;
    }

    private function snapshotPreventiveMaintenance(Psm $checklist): void
    {
        if ((int) $checklist->template_psm_id !== 1 || $checklist->type !== 'submission') {
            return;
        }

        $checklist->loadMissing('values.variable');

        PreventiveMaintenanceRevision::create([
            'psm_id' => $checklist->psm_id,
            'name' => $checklist->name,
            'detail' => $checklist->detail,
            'values_snapshot' => $this->buildPsmValueMap($checklist),
            'original_created_at' => $checklist->created_at,
        ]);
    }

    private function referenceItemFieldName(string $prefix, string $name): string
    {
        return $prefix . '_' . strtolower(str_replace([' ', '.', '-'], '_', $name));
    }

    private function normalizeOthersSelections(array $payload): array
    {
        $pairs = [
            'equipment_others' => 'equipment_others_specify',
            'os_others' => 'os_others_specify',
            'software_others' => 'software_others_specify',
        ];

        foreach ($pairs as $checkboxField => $textField) {
            $textValue = trim((string) ($payload[$textField] ?? ''));
            if ($textValue !== '') {
                $payload[$checkboxField] = $payload[$checkboxField] ?? '1';
            }
        }

        return $payload;
    }

    private function preventiveMaintenancePhotoValidationRules(Request $request): array
    {
        $field = self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD;

        if ($request->hasFile($field) && is_array($request->file($field))) {
            return [
                $field => 'nullable|array|max:10',
                "{$field}.*" => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            ];
        }

        return [
            $field => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    private function preventiveMaintenancePhotoPaths($storedValue): array
    {
        if (is_array($storedValue)) {
            $rawPaths = $storedValue;
        } else {
            $storedValue = trim((string) ($storedValue ?? ''));
            if ($storedValue === '') {
                return [];
            }

            $decoded = json_decode($storedValue, true);
            $rawPaths = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : [$storedValue];
        }

        $paths = [];
        foreach ($rawPaths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);
            if ($path === '' || ! str_starts_with($path, 'preventive-maintenance/photos/')) {
                continue;
            }

            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function encodePreventiveMaintenancePhotoPaths(array $paths): ?string
    {
        $paths = $this->preventiveMaintenancePhotoPaths($paths);

        return $paths === [] ? null : json_encode($paths, JSON_UNESCAPED_SLASHES);
    }

    private function deletePreventiveMaintenancePhotos(array $paths): void
    {
        $paths = $this->preventiveMaintenancePhotoPaths($paths);
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function keptPreventiveMaintenancePhotoPaths(Request $request, array $oldPaths): array
    {
        if (! $request->has('maintenance_photo_keep_list_present')) {
            return $oldPaths;
        }

        $requestedPaths = $request->input('maintenance_existing_photos', []);
        if ($requestedPaths === null || $requestedPaths === '') {
            $requestedPaths = [];
        } elseif (! is_array($requestedPaths)) {
            $requestedPaths = [$requestedPaths];
        }

        $requestedPaths = $this->preventiveMaintenancePhotoPaths($requestedPaths);

        return array_values(array_filter(
            $requestedPaths,
            fn (string $path) => in_array($path, $oldPaths, true)
        ));
    }

    private function storePreventiveMaintenancePhotos(Request $request, array $oldPaths = []): array
    {
        if (! $request->hasFile(self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD)) {
            return $oldPaths;
        }

        $files = $request->file(self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD);
        if (! is_array($files)) {
            $files = [$files];
        }

        $paths = $oldPaths;
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $paths[] = $file->store('preventive-maintenance/photos', 'public');
            }
        }

        return $paths;
    }

    private function preventiveMaintenancePhotoUrl(int $checklistId, ?string $path): ?string
    {
        return $path ? $this->appUrl("/api/preventive-maintenance/{$checklistId}/photo") : null;
    }

    private function preventiveMaintenancePhotoUrls(int $checklistId, array $paths): array
    {
        $urls = [];
        foreach ($this->preventiveMaintenancePhotoPaths($paths) as $index => $path) {
            $urls[] = $this->appUrl("/api/preventive-maintenance/{$checklistId}/photos/{$index}");
        }

        return $urls;
    }

    private function operatingSystemsQuery()
    {
        return OperatingSystem::where('enabled', true)
            ->whereNotIn('name', ['Windows 11', 'Windows 11 Pro 64-bit'])
            ->orderBy('sort_order');
    }

    public function referenceData()
    {
        return response()->json([
            'categories' => SpecificationCategory::where('enabled', true)->orderBy('sort_order')->get(),
            ...$this->pmReferenceData(),
        ]);
    }

    public function preventiveMaintenanceReport(Request $request)
    {
        $checklistTypeKeys = ['pc', 'server', 'ip_phone', 'network_device', 'wifi', 'ups', 'cctv'];
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'college_office_id' => ['nullable', 'integer', 'exists:college_offices,id'],
            'checklist_type' => ['nullable', Rule::in($checklistTypeKeys)],
        ]);

        $from = isset($validated['from']) ? substr((string) $validated['from'], 0, 10) : null;
        $to = isset($validated['to']) ? substr((string) $validated['to'], 0, 10) : null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $selectedCollegeOfficeId = isset($validated['college_office_id']) ? (int) $validated['college_office_id'] : null;
        $selectedChecklistType = ! empty($validated['checklist_type'])
            ? $this->normalizeChecklistType($validated['checklist_type'])
            : null;

        $collegeOffices = CollegeOffice::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $officeBuckets = [];
        $departmentBuckets = [];
        $categoryBuckets = [];
        $monthlyTrend = [];
        $selectedPmIds = [];
        $pmMetaById = [];
        $taggedPreventiveMaintenance = 0;
        $coveredOfficeIds = [];

        foreach ($checklistTypeKeys as $type) {
            $categoryBuckets[$type] = [
                'key' => $type,
                'label' => $this->checklistTypeLabel($type),
                'count' => 0,
                'percentage' => 0,
            ];
        }

        $preventiveMaintenanceRecords = Psm::query()
            ->where('type', 'submission')
            ->where('template_psm_id', 1)
            ->with(['values.variable'])
            ->orderBy('created_at')
            ->get();

        foreach ($preventiveMaintenanceRecords as $record) {
            $valueMap = $this->buildPsmValueMap($record, true);
            $checklistDate = substr((string) ($valueMap['checklist_date'] ?? $this->appDateFromDate($record->created_at) ?? ''), 0, 10);
            $checklistDate = $checklistDate !== '' ? $checklistDate : $this->appDateFromDate($record->created_at);
            $checklistType = $this->normalizeChecklistType($valueMap['checklist_type'] ?? null);
            $organizationSelection = $this->organizationSelectionFromValueMap($valueMap);
            $collegeOfficeId = $organizationSelection['college_office_id'] ? (int) $organizationSelection['college_office_id'] : null;

            if ($from && $checklistDate && $checklistDate < $from) {
                continue;
            }

            if ($to && $checklistDate && $checklistDate > $to) {
                continue;
            }

            if ($selectedChecklistType && $checklistType !== $selectedChecklistType) {
                continue;
            }

            if ($selectedCollegeOfficeId && $collegeOfficeId !== $selectedCollegeOfficeId) {
                continue;
            }

            $selectedPmIds[] = (int) $record->psm_id;
            $officeName = $collegeOfficeId
                ? trim((string) ($organizationSelection['office_college'] ?? ''))
                : self::REPORT_UNTAGGED_OFFICE_NAME;
            $officeKey = $collegeOfficeId ? 'office_' . $collegeOfficeId : 'untagged';

            if ($collegeOfficeId) {
                $taggedPreventiveMaintenance++;
                $coveredOfficeIds[$collegeOfficeId] = true;
            }

            if (! isset($officeBuckets[$officeKey])) {
                $officeBuckets[$officeKey] = [
                    'key' => $officeKey,
                    'college_office_id' => $collegeOfficeId,
                    'name' => $officeName !== '' ? $officeName : self::REPORT_UNTAGGED_OFFICE_NAME,
                    'count' => 0,
                    'percentage' => 0,
                    'item_checklists' => 0,
                    'attention_item_checklists' => 0,
                ];
            }

            $officeBuckets[$officeKey]['count']++;
            $categoryBuckets[$checklistType]['count']++;

            $departmentName = trim((string) ($organizationSelection['department'] ?? ''));
            if ($departmentName !== '') {
                $departmentKey = $officeKey . '|' . $departmentName;
                if (! isset($departmentBuckets[$departmentKey])) {
                    $departmentBuckets[$departmentKey] = [
                        'key' => $departmentKey,
                        'college_office_id' => $collegeOfficeId,
                        'college_office' => $officeBuckets[$officeKey]['name'],
                        'department' => $departmentName,
                        'count' => 0,
                        'percentage' => 0,
                    ];
                }

                $departmentBuckets[$departmentKey]['count']++;
            }

            if ($checklistDate) {
                $monthKey = substr($checklistDate, 0, 7);
                if (! isset($monthlyTrend[$monthKey])) {
                    $monthlyTrend[$monthKey] = [
                        'month' => $monthKey,
                        'label' => date('M Y', strtotime($monthKey . '-01')),
                        'preventive_maintenance' => 0,
                        'item_checklists' => 0,
                    ];
                }

                $monthlyTrend[$monthKey]['preventive_maintenance']++;
            }

            $pmMetaById[(int) $record->psm_id] = [
                'office_key' => $officeKey,
                'checklist_type' => $checklistType,
                'checklist_date' => $checklistDate,
            ];
        }

        $totalPreventiveMaintenance = count($selectedPmIds);
        $commissionBuckets = [];
        $itemStatusCounts = [
            'ok' => 0,
            'repair' => 0,
            'na' => 0,
            'blank' => 0,
        ];
        $entryCountsByType = [];
        $totalItemChecklists = 0;
        $attentionItemChecklists = 0;

        foreach (self::ITEM_CHECKLIST_COMMISSION_STATUSES as $status) {
            $commissionBuckets[$status] = [
                'key' => $status,
                'label' => $this->itemChecklistCommissionStatusLabel($status),
                'count' => 0,
                'percentage' => 0,
            ];
        }

        $parentVarId = PsmVariable::query()
            ->where('psm_id', 2)
            ->where('name', 'parent_psm_id')
            ->value('psm_var_id');

        if ($parentVarId && $selectedPmIds) {
            $parentLinks = PsmValue::query()
                ->where('psm_var_id', $parentVarId)
                ->whereIn('value', array_map('strval', $selectedPmIds))
                ->get(['psm_id', 'value']);

            $parentByItemChecklistId = $parentLinks
                ->mapWithKeys(fn ($link) => [(int) $link->psm_id => (int) $link->value])
                ->all();

            $itemChecklists = Psm::query()
                ->whereIn('psm_id', array_keys($parentByItemChecklistId))
                ->with(['values.variable'])
                ->get();

            foreach ($itemChecklists as $itemChecklist) {
                $itemValueMap = $this->buildPsmValueMap($itemChecklist, true);
                $parentPsmId = (int) ($itemValueMap['parent_psm_id'] ?? $parentByItemChecklistId[(int) $itemChecklist->psm_id] ?? 0);
                $pmMeta = $pmMetaById[$parentPsmId] ?? null;

                if (! $pmMeta) {
                    continue;
                }

                $totalItemChecklists++;
                $officeKey = $pmMeta['office_key'];
                if (isset($officeBuckets[$officeKey])) {
                    $officeBuckets[$officeKey]['item_checklists']++;
                }

                $commissionStatus = $this->normalizeItemChecklistCommissionStatus($itemValueMap['commission_status'] ?? null);
                $commissionBuckets[$commissionStatus]['count']++;

                if (in_array($commissionStatus, ['for_repair', 'under_maintenance', 'defective', 'replaced', 'decommissioned'], true)) {
                    $attentionItemChecklists++;
                    if (isset($officeBuckets[$officeKey])) {
                        $officeBuckets[$officeKey]['attention_item_checklists']++;
                    }
                }

                $checklistType = $pmMeta['checklist_type'] ?? 'pc';
                if (! isset($entryCountsByType[$checklistType])) {
                    $entryCountsByType[$checklistType] = $this->enabledItemChecklistEntries($checklistType)->count();
                }

                $filledEntryCount = 0;
                foreach ($itemValueMap as $key => $value) {
                    if (! preg_match('/^item_\d+$/', (string) $key)) {
                        continue;
                    }

                    $filledEntryCount++;
                    $status = strtolower(trim((string) $value));
                    $status = str_replace([' ', '-'], '_', $status);
                    if (in_array($status, ['n/a', 'n_a', '?'], true)) {
                        $status = 'na';
                    }

                    if (! array_key_exists($status, $itemStatusCounts)) {
                        $status = 'blank';
                    }

                    $itemStatusCounts[$status]++;
                }

                $itemStatusCounts['blank'] += max(0, $entryCountsByType[$checklistType] - $filledEntryCount);

                $itemMonth = substr((string) ($itemValueMap['maintenance_month'] ?? ''), 0, 7);
                if ($itemMonth === '') {
                    $itemMonth = substr((string) ($itemValueMap['maintenance_date'] ?? ''), 0, 7);
                }

                if ($itemMonth !== '') {
                    if (! isset($monthlyTrend[$itemMonth])) {
                        $monthlyTrend[$itemMonth] = [
                            'month' => $itemMonth,
                            'label' => date('M Y', strtotime($itemMonth . '-01')),
                            'preventive_maintenance' => 0,
                            'item_checklists' => 0,
                        ];
                    }

                    $monthlyTrend[$itemMonth]['item_checklists']++;
                }
            }
        }

        $officeRows = array_values($officeBuckets);
        foreach ($officeRows as &$officeRow) {
            $officeRow['percentage'] = $this->reportPercentage($officeRow['count'], $totalPreventiveMaintenance);
            $officeRow['attention_percentage'] = $this->reportPercentage($officeRow['attention_item_checklists'], $officeRow['item_checklists']);
        }
        unset($officeRow);

        usort($officeRows, function ($a, $b) {
            $countCompare = $b['count'] <=> $a['count'];
            return $countCompare !== 0 ? $countCompare : strcmp($a['name'], $b['name']);
        });

        $departmentRows = array_values($departmentBuckets);
        foreach ($departmentRows as &$departmentRow) {
            $departmentRow['percentage'] = $this->reportPercentage($departmentRow['count'], $totalPreventiveMaintenance);
        }
        unset($departmentRow);

        usort($departmentRows, function ($a, $b) {
            $countCompare = $b['count'] <=> $a['count'];
            return $countCompare !== 0 ? $countCompare : strcmp($a['department'], $b['department']);
        });

        $categoryRows = array_values($categoryBuckets);
        foreach ($categoryRows as &$categoryRow) {
            $categoryRow['percentage'] = $this->reportPercentage($categoryRow['count'], $totalPreventiveMaintenance);
        }
        unset($categoryRow);

        $commissionRows = array_values($commissionBuckets);
        foreach ($commissionRows as &$commissionRow) {
            $commissionRow['percentage'] = $this->reportPercentage($commissionRow['count'], $totalItemChecklists);
        }
        unset($commissionRow);

        $totalItemStatuses = array_sum($itemStatusCounts);
        $itemStatusRows = collect([
            'ok' => 'OK / Good',
            'repair' => 'Repair / Near Maintenance',
            'na' => 'N/A',
            'blank' => 'Unfilled',
        ])->map(function ($label, $key) use ($itemStatusCounts, $totalItemStatuses) {
            return [
                'key' => $key,
                'label' => $label,
                'count' => $itemStatusCounts[$key] ?? 0,
                'percentage' => $this->reportPercentage($itemStatusCounts[$key] ?? 0, $totalItemStatuses),
            ];
        })->values()->all();

        ksort($monthlyTrend);
        $monthlyTrendRows = array_values($monthlyTrend);

        return response()->json([
            'generated_at' => now(config('app.timezone'))->toDateTimeString(),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'college_office_id' => $selectedCollegeOfficeId,
                'checklist_type' => $selectedChecklistType,
                'college_offices' => $collegeOffices,
                'checklist_types' => collect($checklistTypeKeys)->map(fn ($type) => [
                    'key' => $type,
                    'label' => $this->checklistTypeLabel($type),
                ])->values(),
            ],
            'summary' => [
                'total_preventive_maintenance' => $totalPreventiveMaintenance,
                'tagged_preventive_maintenance' => $taggedPreventiveMaintenance,
                'untagged_preventive_maintenance' => max(0, $totalPreventiveMaintenance - $taggedPreventiveMaintenance),
                'tagged_percentage' => $this->reportPercentage($taggedPreventiveMaintenance, $totalPreventiveMaintenance),
                'total_item_checklists' => $totalItemChecklists,
                'attention_item_checklists' => $attentionItemChecklists,
                'attention_percentage' => $this->reportPercentage($attentionItemChecklists, $totalItemChecklists),
                'office_coverage_count' => count($coveredOfficeIds),
                'office_coverage_total' => $collegeOffices->count(),
                'office_coverage_percentage' => $this->reportPercentage(count($coveredOfficeIds), $collegeOffices->count()),
            ],
            'college_offices' => $officeRows,
            'commission_statuses' => $commissionRows,
            'asset_categories' => $categoryRows,
            'item_statuses' => $itemStatusRows,
            'departments' => array_slice($departmentRows, 0, 12),
            'monthly_trend' => $monthlyTrendRows,
            'permissions' => [
                'view_user_reports' => $request->user()?->hasRole(User::ROLE_SUPERADMIN, User::ROLE_ADMIN) === true,
            ],
            'user_reports' => $this->buildUserReport($request, $selectedPmIds),
        ]);
    }

    private function buildUserReport(Request $request, array $selectedPmIds): ?array
    {
        $actor = $request->user();
        if (! $actor?->hasRole(User::ROLE_SUPERADMIN, User::ROLE_ADMIN)) {
            return null;
        }

        $users = User::query()
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $createdStatsByUser = collect();
        $updatedStatsByUser = collect();

        if ($selectedPmIds && Schema::hasColumn('psm', 'created_by')) {
            $createdStatsByUser = Psm::query()
                ->select([
                    'created_by',
                    DB::raw('COUNT(*) as records_created'),
                    DB::raw('MAX(created_at) as latest_created_at'),
                ])
                ->whereIn('psm_id', $selectedPmIds)
                ->whereNotNull('created_by')
                ->groupBy('created_by')
                ->get()
                ->keyBy(fn ($row) => (int) $row->created_by);

            if (Schema::hasTable('preventive_maintenance_revisions')) {
                $updatedStatsByUser = PreventiveMaintenanceRevision::query()
                    ->join('psm', 'preventive_maintenance_revisions.psm_id', '=', 'psm.psm_id')
                    ->select([
                        'psm.created_by',
                        DB::raw('COUNT(DISTINCT preventive_maintenance_revisions.psm_id) as records_updated'),
                        DB::raw('MAX(preventive_maintenance_revisions.created_at) as latest_updated_at'),
                    ])
                    ->whereIn('preventive_maintenance_revisions.psm_id', $selectedPmIds)
                    ->whereNotNull('psm.created_by')
                    ->groupBy('psm.created_by')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->created_by);
            }
        }

        $userRows = $users->map(function (User $user) use ($createdStatsByUser, $updatedStatsByUser) {
            $createdStats = $createdStatsByUser->get((int) $user->id);
            $updatedStats = $updatedStatsByUser->get((int) $user->id);
            $latestUserUpdate = $user->updated_at?->toDateTimeString();
            $latestCreatedRecord = $createdStats?->latest_created_at;
            $latestUpdatedRecord = $updatedStats?->latest_updated_at;

            $lastActivity = collect([
                $latestUserUpdate,
                $latestCreatedRecord,
                $latestUpdatedRecord,
            ])->filter()->max();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
                'is_active' => (bool) $user->is_active,
                'status_label' => $user->is_active ? 'Active' : 'Inactive',
                'records_created' => (int) ($createdStats?->records_created ?? 0),
                'records_updated' => (int) ($updatedStats?->records_updated ?? 0),
                'last_activity_at' => $lastActivity,
                'last_updated_at' => $latestUserUpdate,
                'created_at' => $user->created_at?->toDateTimeString(),
            ];
        })->values();

        return [
            'summary' => [
                'total_users' => $users->count(),
                'active_users' => $users->where('is_active', true)->count(),
                'inactive_users' => $users->where('is_active', false)->count(),
                'admin_users' => $users
                    ->filter(fn (User $user) => $user->hasRole(User::ROLE_SUPERADMIN, User::ROLE_ADMIN))
                    ->count(),
                'staff_users' => $users
                    ->reject(fn (User $user) => $user->hasRole(User::ROLE_SUPERADMIN, User::ROLE_ADMIN))
                    ->count(),
                'users_with_created_pm_records' => $userRows
                    ->filter(fn (array $row) => $row['records_created'] > 0)
                    ->count(),
            ],
            'users' => $userRows,
        ];
    }

    private function reportPercentage(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 1);
    }

    private function collegeOfficeDepartmentRules(Request $request): array
    {
        return [
            'college_office_id' => ['required', 'integer', 'exists:college_offices,id'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('college_office_id', $request->input('college_office_id'))),
            ],
        ];
    }

    private function applyCollegeOfficeDepartmentSelection(Request $request, array $payload): array
    {
        $collegeOffice = CollegeOffice::findOrFail((int) $request->input('college_office_id'));
        $department = Department::where('college_office_id', $collegeOffice->id)
            ->findOrFail((int) $request->input('department_id'));

        $payload['college_office_id'] = (string) $collegeOffice->id;
        $payload['department_id'] = (string) $department->id;
        $payload['office_college'] = $collegeOffice->name;
        $payload['department'] = $department->name;

        return $payload;
    }

    private function organizationSelectionFromValueMap(array $valueMap): array
    {
        $collegeOffice = null;
        $department = null;

        if (! empty($valueMap['college_office_id'])) {
            $collegeOffice = CollegeOffice::find((int) $valueMap['college_office_id']);
        }

        if (! empty($valueMap['department_id'])) {
            $department = Department::with('collegeOffice')->find((int) $valueMap['department_id']);
        }

        if (! $collegeOffice && $department?->collegeOffice) {
            $collegeOffice = $department->collegeOffice;
        }

        if ($collegeOffice && $department && (int) $department->college_office_id !== (int) $collegeOffice->id) {
            $department = null;
        }

        if (! $collegeOffice && ! empty($valueMap['office_college'])) {
            $collegeOffice = CollegeOffice::where('name', $valueMap['office_college'])->first();
        }

        if ($collegeOffice && ! $department && ! empty($valueMap['department'])) {
            $department = $collegeOffice->departments()
                ->where('name', $valueMap['department'])
                ->first();
        }

        return [
            'college_office_id' => $collegeOffice?->id,
            'department_id' => $department?->id,
            'office_college' => $collegeOffice?->name ?? ($valueMap['office_college'] ?? ''),
            'department' => $department?->name ?? ($valueMap['department'] ?? ''),
        ];
    }

    public function getPreventiveMaintenance($id)
    {
        $checklist = Psm::with(['values.variable'])->findOrFail($id);
        $this->abortUnlessCanAccessPsm($checklist);

        // Build a map of all values
        $valueMap = $this->buildPsmValueMap($checklist);
        $checklistType = $this->normalizeChecklistType($valueMap['checklist_type'] ?? null);
        $assetName = $this->resolvePreventiveMaintenanceAssetName($valueMap, $checklist->name, $checklistType);
        $organizationSelection = $this->organizationSelectionFromValueMap($valueMap);
        $photoPaths = $this->preventiveMaintenancePhotoPaths($valueMap[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] ?? null);
        $photoUrls = $this->preventiveMaintenancePhotoUrls((int) $checklist->psm_id, $photoPaths);
        
        // Prepare response data
        $data = [
            'psm_id' => $checklist->psm_id,
            'identifier' => $checklist->preventiveMaintenanceIdentifier($checklistType),
            'name' => $checklist->name,
            'pc_name' => $assetName,
            'asset_name' => $assetName,
            'checklist_type' => $checklistType,
            'checklist_type_label' => $this->checklistTypeLabel($checklistType),
            'asset_label' => $this->checklistAssetLabel($checklistType),
            'checklist_date' => $valueMap['checklist_date'] ?? null,
            'user_operator' => $valueMap['user_operator'] ?? '',
            'college_office_id' => $organizationSelection['college_office_id'],
            'department_id' => $organizationSelection['department_id'],
            'office_college' => $organizationSelection['office_college'],
            'department' => $organizationSelection['department'],
            'date_acquired' => $valueMap['date_acquired'] ?? null,
            'mac_address' => $valueMap['mac_address'] ?? '',
            'ip_address' => $valueMap['ip_address'] ?? '',
            'equipment_others' => !empty($valueMap['equipment_others']) ? true : false,
            'equipment_others_specify' => $valueMap['equipment_others_specify'] ?? '',
            'os_others' => !empty($valueMap['os_others']) ? true : false,
            'os_others_specify' => $valueMap['os_others_specify'] ?? '',
            'software_others' => !empty($valueMap['software_others']) ? true : false,
            'software_others_specify' => $valueMap['software_others_specify'] ?? '',
            'maintenance_photo' => $valueMap[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] ?? '',
            'maintenance_photos' => $photoPaths,
            'maintenance_photo_url' => $this->preventiveMaintenancePhotoUrl((int) $checklist->psm_id, $photoPaths[0] ?? null),
            'maintenance_photo_urls' => $photoUrls,
            'is_locked' => (bool) $checklist->is_locked,
        ];
        
        // Add all equipment checkboxes
        $equipment = Equipment::where('enabled', true)->orderBy('sort_order')->get();
        $data['equipment_data'] = [];
        foreach ($equipment as $item) {
            $fieldName = $this->referenceItemFieldName('equipment', $item->name);
            $isChecked = !empty($valueMap[$fieldName]);
            $data[$fieldName] = $isChecked;
            if ($isChecked) {
                $data['equipment_data'][] = ['id' => $item->id, 'name' => $item->name];
            }
        }
        
        // Add all OS checkboxes
        $systems = $this->operatingSystemsQuery()->get();
        $data['os_data'] = [];
        foreach ($systems as $item) {
            $fieldName = $this->referenceItemFieldName('os', $item->name);
            $isChecked = false;
            if (!empty($valueMap[$fieldName])) {
                $isChecked = true;
            }

            $data[$fieldName] = $isChecked;
            if ($isChecked) {
                $data['os_data'][] = ['id' => $item->id, 'name' => $item->name];
            }
        }
        
        // Add all software checkboxes
        $software = SoftwareApplication::where('enabled', true)->orderBy('sort_order')->get();
        $data['software_data'] = [];
        foreach ($software as $item) {
            $canonicalField = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $item->name));
            $aliasFields = [$canonicalField];

            if ($canonicalField === 'software_enrollment_system' || $canonicalField === 'software_enrolment_system') {
                $aliasFields[] = 'software_enrollment_system';
                $aliasFields[] = 'software_enrolment_system';
                $aliasFields[] = 'software_enrollment';
            }

            if ($canonicalField === 'software_anti_virus') {
                $aliasFields[] = 'software_antivirus';
                $aliasFields[] = 'software_anti-virus';
            }

            $isChecked = false;
            foreach ($aliasFields as $aliasField) {
                if (!empty($valueMap[$aliasField])) {
                    $isChecked = true;
                    break;
                }
            }

            $fieldName = $canonicalField;
            $data[$fieldName] = $isChecked;
            if ($isChecked) {
                $data['software_data'][] = ['id' => $item->id, 'name' => $item->name];
            }
        }
        
        // Add specification fields
        $specFields = SpecificationField::where('enabled', true)->orderBy('sort_order')->get();
        foreach ($specFields as $field) {
            if ($field->name !== 'ip_address') {
                $data[$field->name] = $valueMap[$field->name] ?? '';
            }
        }

        foreach ($this->preventiveMaintenanceCustomFields($checklistType) as $fieldName => $fieldMeta) {
            $data[$fieldName] = $valueMap[$fieldName] ?? '';
        }
        
        return response()->json($data);
    }

    public function previewPreventiveMaintenanceIdentifier(Request $request)
    {
        $request->validate([
            'checklist_type' => 'nullable|in:pc,server,ip_phone,network_device,wifi,ups,cctv',
        ]);

        $checklistType = $this->normalizeChecklistType($request->query('checklist_type'));
        $nextPsmId = $this->nextPreventiveMaintenancePreviewId();

        return response()->json([
            'psm_id' => $nextPsmId,
            'identifier' => sprintf(
                'PM%s-%04d',
                Psm::preventiveMaintenanceCategoryCode($checklistType),
                $nextPsmId
            ),
            'checklist_type' => $checklistType,
            'is_preview' => true,
        ]);
    }

    private function nextPreventiveMaintenancePreviewId(): int
    {
        $model = new Psm();
        $table = $model->getTable();
        $key = $model->getKeyName();
        $connection = DB::connection();

        if ($connection->getDriverName() === 'mysql') {
            $row = $connection->selectOne(
                'select AUTO_INCREMENT from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_NAME = ? limit 1',
                [$connection->getDatabaseName(), $connection->getTablePrefix() . $table]
            );
            $autoIncrement = $row?->AUTO_INCREMENT;

            if (is_numeric($autoIncrement) && (int) $autoIncrement > 0) {
                return (int) $autoIncrement;
            }
        }

        return max(1, ((int) Psm::query()->max($key)) + 1);
    }

    public function viewPreventiveMaintenancePhoto($id, $photoIndex = 0)
    {
        $checklist = Psm::with(['values.variable'])->findOrFail($id);
        $this->abortUnlessCanAccessPsm($checklist);
        $valueMap = $this->buildPsmValueMap($checklist);
        $photoPaths = $this->preventiveMaintenancePhotoPaths($valueMap[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] ?? null);
        $photoPath = $photoPaths[max(0, (int) $photoIndex)] ?? '';

        if ($photoPath === '' || ! str_starts_with($photoPath, 'preventive-maintenance/photos/')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($photoPath)) {
            abort(404);
        }

        return Storage::disk('public')->response($photoPath);
    }

    public function listPreventiveMaintenanceRevisions($id)
    {
        $checklist = Psm::findOrFail($id);
        if ((int) $checklist->template_psm_id !== 1) {
            abort(404);
        }
        $this->abortUnlessCanAccessPsm($checklist);

        $revisions = PreventiveMaintenanceRevision::where('psm_id', $checklist->psm_id)
            ->latest()
            ->get()
            ->map(function (PreventiveMaintenanceRevision $revision) {
                $values = $revision->values_snapshot ?? [];
                $checklistType = $this->normalizeChecklistType($values['checklist_type'] ?? null);

                return [
                    'id' => $revision->id,
                    'psm_id' => $revision->psm_id,
                    'name' => $revision->name,
                    'asset_name' => $this->resolvePreventiveMaintenanceAssetName($values, $revision->name, $checklistType),
                    'checklist_type' => $checklistType,
                    'checklist_type_label' => $this->checklistTypeLabel($checklistType),
                    'revision_date' => $this->appDateFromDate($revision->created_at),
                    'revision_time' => $this->appTimeFromDate($revision->created_at),
                ];
            })
            ->values();

        return response()->json($revisions);
    }

    public function getPreventiveMaintenanceRevision($id, $revisionId)
    {
        $checklist = Psm::findOrFail($id);
        $this->abortUnlessCanAccessPsm($checklist);
        $revision = PreventiveMaintenanceRevision::where('psm_id', $id)->findOrFail($revisionId);
        $values = $revision->values_snapshot ?? [];
        $checklistType = $this->normalizeChecklistType($values['checklist_type'] ?? null);
        $assetName = $this->resolvePreventiveMaintenanceAssetName($values, $revision->name, $checklistType);

        return response()->json(array_merge($values, [
            'psm_id' => $revision->psm_id,
            'revision_id' => $revision->id,
            'is_revision' => true,
            'name' => $revision->name,
            'pc_name' => $assetName,
            'asset_name' => $assetName,
            'checklist_type' => $checklistType,
            'checklist_type_label' => $this->checklistTypeLabel($checklistType),
            'asset_label' => $this->checklistAssetLabel($checklistType),
            'revision_date' => $this->appDateFromDate($revision->created_at),
            'revision_time' => $this->appTimeFromDate($revision->created_at),
        ]));
    }

    public function storePreventiveMaintenance(Request $request)
    {
        try {
            $payload = $this->normalizeOthersSelections($request->all());
            unset($payload[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD]);
            unset($payload['maintenance_existing_photos'], $payload['maintenance_photo_keep_list_present']);
            $payload['checklist_date'] = $this->currentAppDate();
            $payload['date_acquired'] = $this->currentAppDate();

            $request->validate([
                'checklist_type' => 'nullable|in:pc,server,ip_phone,network_device,wifi,ups,cctv',
                'user_operator' => 'nullable|string',
                'office_college' => 'nullable|string',
                'department' => 'nullable|string',
                ...$this->collegeOfficeDepartmentRules($request),
                'pc_name' => 'nullable|string',
                'mac_address' => 'nullable|string',
                'ip_address' => 'nullable|string',
                'brand_name' => 'nullable|string',
                'model_name' => 'nullable|string',
                'serial_number' => 'nullable|string',
                'office_located' => 'nullable|string',
                'ip_address_tagged' => 'nullable|string',
                'vlan' => 'nullable|string',
                'telephone_number' => 'nullable|string',
                // Network Device fields
                'network_device_category_type' => 'nullable|string',
                'network_device_product_name' => 'nullable|string',
                'network_device_model_name' => 'nullable|string',
                'network_device_serial' => 'nullable|string',
                'network_device_mac_address' => 'nullable|string',
                'network_device_office_location' => 'nullable|string',
                'network_device_ip_address' => 'nullable|string',
                'network_device_vlan' => 'nullable|string',
                // WiFi fields
                'wifi_category_type' => 'nullable|string',
                'wifi_product_name' => 'nullable|string',
                'wifi_model_name' => 'nullable|string',
                'wifi_serial' => 'nullable|string',
                'wifi_mac_address' => 'nullable|string',
                'wifi_office_location' => 'nullable|string',
                'wifi_ip_address' => 'nullable|string',
                'wifi_vlan' => 'nullable|string',
                'wifi_name' => 'nullable|string',
                'wifi_password' => 'nullable|string',
                'wifi_channel_supported' => 'nullable|string',
                'ups_category' => 'nullable|string',
                'ups_brand_name' => 'nullable|string',
                'ups_model_name' => 'nullable|string',
                'ups_mac_address' => 'nullable|string',
                'ups_serial' => 'nullable|string',
                'ups_total_power_capacity' => 'nullable|string',
                'cctv_category_type' => 'nullable|string',
                'cctv_product_name' => 'nullable|string',
                'cctv_model_name' => 'nullable|string',
                'cctv_serial' => 'nullable|string',
                'cctv_mac_address' => 'nullable|string',
                'cctv_office_location' => 'nullable|string',
                'cctv_ip_address' => 'nullable|string',
                'cctv_vlan' => 'nullable|string',
                ...$this->preventiveMaintenancePhotoValidationRules($request),
            ]);

            $payload = $this->applyCollegeOfficeDepartmentSelection($request, $payload);

            $photoPaths = $this->storePreventiveMaintenancePhotos($request);
            $encodedPhotoPaths = $this->encodePreventiveMaintenancePhotoPaths($photoPaths);
            if ($encodedPhotoPaths) {
                $payload[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] = $encodedPhotoPaths;
            }

            $assetName = $this->resolvePreventiveMaintenanceAssetName($payload, 'Untitled');

            $checklist = Psm::create([
                'type' => 'submission',
                'template_psm_id' => 1,
                'name' => $assetName,
                'detail' => 'Preventive Maintenance Submission',
                'created_by' => $request->user()?->id,
            ]);

            // Get the template to find variable IDs
            $template = Psm::where('psm_id', 1)
                ->where('type', 'template')
                ->first();
            if (!$template) {
                throw new \Exception('Template not found');
            }

            $this->ensurePreventiveMaintenanceTemplateVariables($template->psm_id);

            // Store form data using template variables
            foreach ($payload as $key => $value) {
                // Skip internal fields
                if (in_array($key, ['_token', '_method', 'pc_name', 'server_name', 'asset_name'], true)) {
                    continue;
                }
                
                // Only store non-empty values
                if ($value === null || $value === '' || $value === false) {
                    continue;
                }

                $candidateKeys = [$key];
                if (in_array($key, ['software_anti_virus', 'software_antivirus', 'software_anti-virus'], true)) {
                    $candidateKeys = ['software_anti_virus', 'software_antivirus', 'software_anti-virus'];
                } elseif (in_array($key, ['software_enrollment_system', 'software_enrolment_system', 'software_enrollment'], true)) {
                    $candidateKeys = ['software_enrollment_system', 'software_enrolment_system', 'software_enrollment'];
                }

                // Find the variable in the template (supports legacy anti-virus key variants)
                $variable = PsmVariable::where('psm_id', $template->psm_id)
                    ->whereIn('name', $candidateKeys)
                    ->first();

                if ($variable) {
                    PsmValue::updateOrCreate([
                        'psm_id' => $checklist->psm_id,
                        'psm_var_id' => $variable->psm_var_id,
                    ], [
                        'value' => $value === true ? '1' : ($value === false ? '0' : $value),
                        'status' => null,
                    ]);
                }
            }

            $identifier = $checklist->persistPreventiveMaintenanceIdentifier($payload['checklist_type'] ?? null);

            return response()->json([
                'psm_id' => $checklist->psm_id,
                'identifier' => $identifier,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updatePreventiveMaintenance(Request $request, $id)
    {
        try {
            $checklist = Psm::findOrFail($id);
            $this->abortUnlessCanAccessPsm($checklist);
            $this->abortIfPsmLocked($checklist);
            $payload = $this->normalizeOthersSelections($request->all());
            unset($payload[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD]);
            unset($payload['maintenance_existing_photos'], $payload['maintenance_photo_keep_list_present']);
            $existingValueMap = $this->buildPsmValueMap($checklist, true);
            
            $request->validate([
                'checklist_type' => 'nullable|in:pc,server,ip_phone,network_device,wifi,ups,cctv',
                'checklist_date' => 'nullable|date',
                'user_operator' => 'nullable|string',
                'office_college' => 'nullable|string',
                'department' => 'nullable|string',
                ...$this->collegeOfficeDepartmentRules($request),
                'date_acquired' => 'nullable|date',
                'pc_name' => 'nullable|string',
                'mac_address' => 'nullable|string',
                'ip_address' => 'nullable|string',
                'brand_name' => 'nullable|string',
                'model_name' => 'nullable|string',
                'serial_number' => 'nullable|string',
                'office_located' => 'nullable|string',
                'ip_address_tagged' => 'nullable|string',
                'vlan' => 'nullable|string',
                'telephone_number' => 'nullable|string',
                'network_device_category_type' => 'nullable|string',
                'network_device_product_name' => 'nullable|string',
                'network_device_model_name' => 'nullable|string',
                'network_device_serial' => 'nullable|string',
                'network_device_mac_address' => 'nullable|string',
                'network_device_office_location' => 'nullable|string',
                'network_device_ip_address' => 'nullable|string',
                'network_device_vlan' => 'nullable|string',
                'wifi_category_type' => 'nullable|string',
                'wifi_product_name' => 'nullable|string',
                'wifi_model_name' => 'nullable|string',
                'wifi_serial' => 'nullable|string',
                'wifi_mac_address' => 'nullable|string',
                'wifi_office_location' => 'nullable|string',
                'wifi_ip_address' => 'nullable|string',
                'wifi_vlan' => 'nullable|string',
                'wifi_name' => 'nullable|string',
                'wifi_password' => 'nullable|string',
                'wifi_channel_supported' => 'nullable|string',
                'ups_category' => 'nullable|string',
                'ups_brand_name' => 'nullable|string',
                'ups_model_name' => 'nullable|string',
                'ups_mac_address' => 'nullable|string',
                'ups_serial' => 'nullable|string',
                'ups_total_power_capacity' => 'nullable|string',
                'cctv_category_type' => 'nullable|string',
                'cctv_product_name' => 'nullable|string',
                'cctv_model_name' => 'nullable|string',
                'cctv_serial' => 'nullable|string',
                'cctv_mac_address' => 'nullable|string',
                'cctv_office_location' => 'nullable|string',
                'cctv_ip_address' => 'nullable|string',
                'cctv_vlan' => 'nullable|string',
                ...$this->preventiveMaintenancePhotoValidationRules($request),
            ]);

            $payload = $this->applyCollegeOfficeDepartmentSelection($request, $payload);

            $existingPhotoPaths = $this->preventiveMaintenancePhotoPaths(
                $existingValueMap[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] ?? null
            );
            $keptPhotoPaths = $this->keptPreventiveMaintenancePhotoPaths($request, $existingPhotoPaths);
            $removedPhotoPaths = array_values(array_diff($existingPhotoPaths, $keptPhotoPaths));
            $photoPaths = $this->storePreventiveMaintenancePhotos(
                $request,
                $keptPhotoPaths
            );
            $this->deletePreventiveMaintenancePhotos($removedPhotoPaths);
            $encodedPhotoPaths = $this->encodePreventiveMaintenancePhotoPaths($photoPaths);
            if ($encodedPhotoPaths) {
                $payload[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] = $encodedPhotoPaths;
            }

            $assetName = $this->resolvePreventiveMaintenanceAssetName($payload, $checklist->name);

            $this->snapshotPreventiveMaintenance($checklist);

            $checklist->update(['name' => $assetName]);

            // Get the template to find variable IDs
            $template = Psm::where('psm_id', 1)
                ->where('type', 'template')
                ->first();
            if (!$template) {
                throw new \Exception('Template not found');
            }

            $this->ensurePreventiveMaintenanceTemplateVariables($template->psm_id);

            // Delete old values
            PsmValue::where('psm_id', $checklist->psm_id)->delete();

            $payload['checklist_date'] = $this->currentAppDate();
            $payload['date_acquired'] = ! empty($existingValueMap['date_acquired'])
                ? $existingValueMap['date_acquired']
                : $this->currentAppDate();

            // Store updated form data using template variables
            foreach ($payload as $key => $value) {
                // Skip internal fields
                if (in_array($key, ['_token', '_method', 'pc_name', 'server_name', 'asset_name'], true)) {
                    continue;
                }
                
                // Only store non-empty values
                if ($value === null || $value === '' || $value === false) {
                    continue;
                }

                $candidateKeys = [$key];
                if (in_array($key, ['software_anti_virus', 'software_antivirus', 'software_anti-virus'], true)) {
                    $candidateKeys = ['software_anti_virus', 'software_antivirus', 'software_anti-virus'];
                } elseif (in_array($key, ['software_enrollment_system', 'software_enrolment_system', 'software_enrollment'], true)) {
                    $candidateKeys = ['software_enrollment_system', 'software_enrolment_system', 'software_enrollment'];
                }

                // Find the variable in the template (supports legacy anti-virus key variants)
                $variable = PsmVariable::where('psm_id', $template->psm_id)
                    ->whereIn('name', $candidateKeys)
                    ->first();

                if ($variable) {
                    PsmValue::updateOrCreate([
                        'psm_id' => $checklist->psm_id,
                        'psm_var_id' => $variable->psm_var_id,
                    ], [
                        'value' => $value === true ? '1' : ($value === false ? '0' : $value),
                        'status' => null,
                    ]);
                }
            }

            $identifier = $checklist->persistPreventiveMaintenanceIdentifier($payload['checklist_type'] ?? null);

            return response()->json([
                'psm_id' => $checklist->psm_id,
                'identifier' => $identifier,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function itemChecklistEntries($preventiveMaintenanceId)
    {
        $preventiveMaintenance = Psm::with(['values.variable'])->findOrFail($preventiveMaintenanceId);
        $this->abortUnlessCanAccessPsm($preventiveMaintenance);
        $valueMap = $this->buildPsmValueMap($preventiveMaintenance);
        $checklistType = $this->normalizeChecklistType($valueMap['checklist_type'] ?? null);
        $organizationSelection = $this->organizationSelectionFromValueMap($valueMap);
        $pmData = $preventiveMaintenance->toArray();
        $pmData['pc_name'] = $this->resolvePreventiveMaintenanceAssetName($valueMap, $pmData['name'] ?? null, $checklistType);
        $pmData['asset_name'] = $pmData['pc_name'];
        $pmData['checklist_type'] = $checklistType;
        $pmData['identifier'] = $preventiveMaintenance->preventiveMaintenanceIdentifier($checklistType);
        $pmData['checklist_type_label'] = $this->checklistTypeLabel($checklistType);
        $pmData['asset_label'] = $this->checklistAssetLabel($checklistType);
        $pmData['college_office_id'] = $organizationSelection['college_office_id'];
        $pmData['department_id'] = $organizationSelection['department_id'];
        $pmData['office_college'] = $organizationSelection['office_college'];
        $pmData['department'] = $organizationSelection['department'];
        $pmData['checklist_date'] = $valueMap['checklist_date'] ?? null;
        foreach (array_keys($this->preventiveMaintenanceCustomFields($checklistType)) as $fieldName) {
            $pmData[$fieldName] = $valueMap[$fieldName] ?? null;
        }

        $entries = $this->enabledItemChecklistEntries($checklistType)->map(function ($i, $index) {
                return [
                    'item_no' => $i->item_no,
                    'task' => $i->task,
                    'description' => $i->description,
                    'sort_order' => $i->sort_order,
                    'entry_index' => $index,
                    'status' => null,
                ];
            });

        // Provide both flat entries and grouped-by-item for UI consumers
        $grouped = $this->groupItemChecklistEntries($entries);
        $summaryState = $this->resolveItemChecklistSummary((int) $preventiveMaintenanceId);

        return response()->json([
            'preventiveMaintenance' => $pmData,
            'entries' => $entries,
            'grouped_entries' => $grouped,
            'summary_enabled' => $summaryState['enabled'],
            'default_maintenance_date' => $this->currentAppDate(),
            'default_maintenance_month' => $this->currentAppMonth(),
        ]);
    }

    public function listItemChecklistsForPm($preventiveMaintenanceId)
    {
        $preventiveMaintenance = Psm::with(['values.variable'])->find($preventiveMaintenanceId);
        if ($preventiveMaintenance) {
            $this->abortUnlessCanAccessPsm($preventiveMaintenance);
        }
        $pmValueMap = $preventiveMaintenance ? $this->buildPsmValueMap($preventiveMaintenance, true) : [];
        $parentVarId = PsmVariable::where('psm_id', 2)
            ->where('name', 'parent_psm_id')
            ->value('psm_var_id');

        if (! $parentVarId) {
            return response()->json([]);
        }

        $itemChecklistIds = PsmValue::where('psm_var_id', $parentVarId)
            ->where('value', (string) $preventiveMaintenanceId)
            ->pluck('psm_id');

        $itemChecklistQuery = Psm::whereIn('psm_id', $itemChecklistIds);

        if ($this->currentUserIsEncoder()) {
            $itemChecklistQuery->where('created_by', request()->user()->id);
        }

        $itemChecklists = $itemChecklistQuery
            ->with('values.variable')
            ->get()
            ->map(function ($item) use ($preventiveMaintenance, $pmValueMap) {
                $valueMap = $item->getValueMap();
                // Attach maintenance_date so the frontend can show the actual scheduled date
                $item->maintenance_date = $valueMap['maintenance_date'] ?? null;
                $item->maintenance_time = $item->created_at
                    ? $item->created_at->timezone(config('app.timezone'))->format('h:i A')
                    : null;
                $item->commission_status = $this->normalizeItemChecklistCommissionStatus($valueMap['commission_status'] ?? null);
                $item->commission_status_label = $this->itemChecklistCommissionStatusLabel($item->commission_status);
                $item->identifier = $this->itemChecklistIdentifier($item, $preventiveMaintenance, $valueMap, $pmValueMap);
                $item->is_locked = (bool) $item->is_locked;
                return $item;
            })
            ->sortByDesc(function ($item) {
                return $item->maintenance_date ?? $item->created_at;
            })
            ->values();

        return response()->json($itemChecklists);
    }

    public function getItemChecklist($id)
    {
        $itemChecklist = Psm::with(['values.variable'])->findOrFail($id);

        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $valueMap = $this->buildPsmValueMap($itemChecklist, true);
        $parentPsmId = $valueMap['parent_psm_id'] ?? null;
        $preventiveMaintenance = $parentPsmId ? Psm::with(['values.variable'])->find($parentPsmId) : null;
        $pmValueMap = $preventiveMaintenance ? $this->buildPsmValueMap($preventiveMaintenance, true) : [];
        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance, $pmValueMap);

        $dbEntries = $this->enabledItemChecklistEntries($checklistType);

        $entries = $dbEntries->map(function ($e, $i) use ($valueMap) {
            $status = $valueMap['item_' . $i] ?? null;
            return [
                'item_no' => $e->item_no,
                'task' => $e->task,
                'description' => $e->description,
                'status' => $status,
                'sort_order' => $e->sort_order,
                'entry_index' => $i,
            ];
        })->values();

        $grouped = $this->groupItemChecklistEntries($entries);
        $summaryState = $this->resolveItemChecklistSummary((int) $id);
        $summaryEnabled = $summaryState['enabled'];
        $summaryText = $summaryState['text'];

        return response()->json([
            'psm_id' => $itemChecklist->psm_id,
            'name' => $itemChecklist->name,
            'identifier' => $this->itemChecklistIdentifier($itemChecklist, $preventiveMaintenance, $valueMap, $pmValueMap),
            'checklist_type' => $checklistType,
            'maintenance_date' => $valueMap['maintenance_date'] ?? null,
            'maintenance_month' => $valueMap['maintenance_month'] ?? null,
            'commission_status' => $this->normalizeItemChecklistCommissionStatus($valueMap['commission_status'] ?? null),
            'commission_status_label' => $this->itemChecklistCommissionStatusLabel($valueMap['commission_status'] ?? null),
            'summary_recommendation' => $summaryText,
            'summary_enabled' => $summaryEnabled,
            'checked_by' => $valueMap['checked_by'] ?? '',
            'conforme_by' => $valueMap['conforme_by'] ?? '',
            'noted_by' => $this->resolveItemChecklistNotedBy($valueMap, $checklistType),
            'parent_psm_id' => $valueMap['parent_psm_id'] ?? null,
            'is_locked' => (bool) $itemChecklist->is_locked,
            'entries' => $entries,
            'grouped_entries' => $grouped,
        ]);
    }

    public function storeItemChecklist(Request $request)
    {
        $preventiveMaintenanceId = $request->input('preventive_maintenance_id');
        $preventiveMaintenance = $preventiveMaintenanceId ? Psm::find($preventiveMaintenanceId) : null;
        if ($preventiveMaintenance) {
            $this->abortUnlessCanAccessPsm($preventiveMaintenance);
        }
        $pmName = $preventiveMaintenance ? $preventiveMaintenance->name : now()->format('Y-m-d H:i:s');
        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance);

        $submission = Psm::create([
            'type' => 'submission',
            'template_psm_id' => 2,
            'name' => 'Item Checklist - ' . $pmName,
            'created_by' => $request->user()?->id,
        ]);

        $dbEntries = $this->enabledItemChecklistEntries($checklistType);
        $this->ensureItemChecklistBaseVariables(2);
        $this->ensureItemChecklistVariablesForEntries(2, $dbEntries);

        $template = Psm::with('variables')->find(2);
        $varByName = $template ? $template->variables->keyBy('name') : collect();

            if ($preventiveMaintenanceId && $varByName->has('parent_psm_id')) {
            $var = $varByName->get('parent_psm_id');
            PsmValue::updateOrCreate([
                'psm_id' => $submission->psm_id,
                'psm_var_id' => $var->psm_var_id,
            ], [
                'value' => (string) $preventiveMaintenanceId,
                'status' => null,
            ]);
        }

        $this->setItemChecklistValue($submission->psm_id, $varByName, 'maintenance_date', $this->currentAppDate());
        $this->setItemChecklistValue($submission->psm_id, $varByName, 'maintenance_month', $this->currentAppMonth());
        $this->setItemChecklistValue(
            $submission->psm_id,
            $varByName,
            'commission_status',
            $this->normalizeItemChecklistCommissionStatus($request->input('commission_status'))
        );
        $this->setItemChecklistValue($submission->psm_id, $varByName, 'checked_by', $request->input('checked_by'));
        $this->setItemChecklistValue($submission->psm_id, $varByName, 'conforme_by', $request->input('conforme_by'));
        $this->setItemChecklistValue(
            $submission->psm_id,
            $varByName,
            'noted_by',
            $request->input('noted_by') ?: $this->defaultNotedByForChecklistType($checklistType)
        );

        DB::table('item_checklist_summaries')->updateOrInsert(
            ['psm_id' => $submission->psm_id],
            [
                'summary_recommendation' => $request->input('summary_recommendation'),
                'enabled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        foreach ($dbEntries->keys() as $i) {
            $status = $request->input("item_{$i}_status");
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

        $identifier = $this->itemChecklistIdentifier($submission, $preventiveMaintenance);

        return response()->json([
            'psm_id' => $submission->psm_id,
            'parent_psm_id' => (int) $preventiveMaintenanceId,
            'identifier' => $identifier,
        ], 201);
    }

    public function updateItemChecklist(Request $request, $id)
    {
        $itemChecklist = Psm::with(['values.variable'])->findOrFail($id);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404);
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);
        $this->abortIfPsmLocked($itemChecklist);
        $itemChecklistValueMap = $this->buildPsmValueMap($itemChecklist, true);
        $parentPsmId = $itemChecklistValueMap['parent_psm_id'] ?? null;
        $preventiveMaintenance = $parentPsmId ? Psm::with(['values.variable'])->find($parentPsmId) : null;
        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance);

        $dbEntries = $this->enabledItemChecklistEntries($checklistType);
        $this->ensureItemChecklistBaseVariables(2);
        $this->ensureItemChecklistVariablesForEntries(2, $dbEntries);

        $template = Psm::with('variables')->find(2);
        $varByName = $template->variables->keyBy('name');

        $this->updateOrCreateItemChecklistValue(
            $itemChecklist,
            $varByName,
            'commission_status',
            $this->normalizeItemChecklistCommissionStatus($request->input('commission_status'))
        );
        $this->updateOrCreateItemChecklistValue($itemChecklist, $varByName, 'checked_by', $request->input('checked_by'));
        $this->updateOrCreateItemChecklistValue($itemChecklist, $varByName, 'conforme_by', $request->input('conforme_by'));
        $this->updateOrCreateItemChecklistValue(
            $itemChecklist,
            $varByName,
            'noted_by',
            $request->input('noted_by') ?: $this->defaultNotedByForChecklistType($checklistType)
        );

        $summaryRow = DB::table('item_checklist_summaries')
            ->where('psm_id', $itemChecklist->psm_id)
            ->first();
        $summaryEnabled = $summaryRow ? ((int) $summaryRow->enabled === 1) : true;
        $summaryValue = $request->input('summary_recommendation');
        DB::table('item_checklist_summaries')->updateOrInsert(
            ['psm_id' => $itemChecklist->psm_id],
            [
                'summary_recommendation' => $summaryValue,
                'enabled' => $summaryEnabled ? 1 : 0,
                'updated_at' => now(),
                'created_at' => $summaryRow?->created_at ?? now(),
            ]
        );

        foreach ($dbEntries->keys() as $i) {
            $status = $request->input("item_{$i}_status");
            $var = $varByName->get('item_' . $i);
            if (! $var) continue;
            $existing = $itemChecklist->values->first(fn ($v) => $v->variable?->name === 'item_' . $i);
            if ($existing) {
                $existing->update(['value' => $status ?? '', 'status' => $status]);
            } elseif ($status) {
                PsmValue::updateOrCreate([
                    'psm_id' => $itemChecklist->psm_id,
                    'psm_var_id' => $var->psm_var_id,
                ], [
                    'value' => $status,
                    'status' => $status,
                ]);
            }
        }

        // Item checklist data is stored in PSM/PSM_VALUE only.
    }

    public function deleteItemChecklist($id)
    {
        $itemChecklist = Psm::findOrFail($id);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404);
        }
        $this->abortIfPsmLocked($itemChecklist);

        $itemChecklist->delete();

        return response()->json(null, 204);
    }

    private function setItemChecklistValue(int $psmId, $varByName, string $name, $value): void
    {
        if ($value === null || $value === '') return;
        $var = $varByName->get($name);
        if ($var) {
            PsmValue::updateOrCreate([
                'psm_id' => $psmId,
                'psm_var_id' => $var->psm_var_id,
            ], [
                'value' => (string) $value,
                'status' => null,
            ]);
        }
    }

    private function updateOrCreateItemChecklistValue(Psm $itemChecklist, $varByName, string $name, $value): void
    {
        $var = $varByName->get($name);
        if (! $var) return;
        $existing = $itemChecklist->values->first(fn ($v) => $v->variable?->name === $name);
        if ($existing) {
            $existing->update(['value' => $value ?? '']);
        } elseif ($value !== null && $value !== '') {
            PsmValue::updateOrCreate([
                'psm_id' => $itemChecklist->psm_id,
                'psm_var_id' => $var->psm_var_id,
            ], [
                'value' => (string) $value,
                'status' => null,
            ]);
        }
    }

    private function setItemChecklistTaskEnabled(Request $request, int $itemNo, bool $enabled)
    {
        $checklistType = $this->resolveChecklistTypeForItemToggle($request);
        $modelClass = $this->itemChecklistEntryModelClass($checklistType);
        $table = $this->itemChecklistEntryTable($checklistType);
        $query = $modelClass::query()->where('item_no', $itemNo);

        if (Schema::hasColumn($table, 'checklist_type')) {
            $query->where('checklist_type', $checklistType);
        }

        $affected = $query->update(['enabled' => $enabled]);

        return response()->json([
            'item_no' => $itemNo,
            'checklist_type' => $checklistType,
            'enabled' => $enabled,
            'affected_rows' => $affected,
        ]);
    }

    private function setItemChecklistItemEnabled(Request $request, int $id, bool $enabled)
    {
        $checklistType = $this->resolveChecklistTypeForItemToggle($request);
        $modelClass = $this->itemChecklistEntryModelClass($checklistType);
        $item = $modelClass::query()->findOrFail($id);

        $item->enabled = $enabled;
        $item->save();

        return response()->json([
            'id' => $item->id,
            'item_no' => $item->item_no,
            'task' => $item->task,
            'checklist_type' => $checklistType,
            'enabled' => $enabled,
        ]);
    }

    private function resolveChecklistTypeForItemToggle(Request $request): string
    {
        $type = $request->input('checklist_type', $request->query('checklist_type'));

        if ($type === null || $type === '') {
            abort(422, 'The checklist_type field is required.');
        }

        return $this->normalizeChecklistType($type);
    }

    public function listPreventiveMaintenance(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        // Only show preventive maintenance checklists (template_psm_id=1), not item checklists (template_psm_id=2)
        $checklistQuery = Psm::where('type', 'submission')
            ->where('template_psm_id', 1)
            ->with('creator')
            ->orderBy('created_at', 'desc');

        if ($this->currentUserIsEncoder()) {
            $checklistQuery->where('created_by', $request->user()->id);
        }

        $checklists = $checklistQuery
            ->paginate($perPage)
            ->appends($request->query());

        // Map fields for frontend
        $data = collect($checklists->items())->map(function ($checklist) {
            $data = $checklist->toArray();

            // Fetch user_operator and checklist_date from psm_value
            $values = PsmValue::where('psm_value.psm_id', $checklist->psm_id)
                ->join('psm_variable', 'psm_value.psm_var_id', '=', 'psm_variable.psm_var_id')
                ->select('psm_variable.name', 'psm_value.value')
                ->pluck('value', 'name')
                ->toArray();

            $checklistType = $this->normalizeChecklistType($values['checklist_type'] ?? null);
            $organizationSelection = $this->organizationSelectionFromValueMap($values);

            $data['pc_name'] = $this->resolvePreventiveMaintenanceAssetName($values, $data['name'], $checklistType);
            $data['asset_name'] = $data['pc_name'];
            $data['checklist_type'] = $checklistType;
            $data['identifier'] = $checklist->preventiveMaintenanceIdentifier($checklistType);
            $data['checklist_type_label'] = $this->checklistTypeLabel($checklistType);
            $data['asset_label'] = $this->checklistAssetLabel($checklistType);
            $data['user_operator'] = $values['user_operator'] ?? null;
            $data['checklist_date'] = $values['checklist_date'] ?? null;
            $data['checklist_time'] = $checklist->created_at
                ? $checklist->created_at->timezone(config('app.timezone'))->format('h:i A')
                : null;
            $data['college_office_id'] = $organizationSelection['college_office_id'];
            $data['department_id'] = $organizationSelection['department_id'];
            $data['office_college'] = $organizationSelection['office_college'];
            $data['department'] = $organizationSelection['department'];
            $data['created_by'] = $checklist->created_by;
            $data['creator_name'] = $checklist->creator?->name;
            $data['is_locked'] = (bool) $checklist->is_locked;
            foreach (array_keys($this->preventiveMaintenanceCustomFields($checklistType)) as $fieldName) {
                $data[$fieldName] = $values[$fieldName] ?? null;
            }

            return $data;
        })->values()->all();

        return response()->json([
            'current_page' => $checklists->currentPage(),
            'data' => $data,
            'first_page_url' => $checklists->url(1),
            'from' => $checklists->firstItem(),
            'last_page' => $checklists->lastPage(),
            'last_page_url' => $checklists->url($checklists->lastPage()),
            'next_page_url' => $checklists->nextPageUrl(),
            'path' => $request->url(),
            'per_page' => $checklists->perPage(),
            'prev_page_url' => $checklists->previousPageUrl(),
            'to' => $checklists->lastItem(),
            'total' => $checklists->total(),
        ]);
    }

    public function deletePreventiveMaintenance($id)
    {
        $checklist = Psm::with(['values.variable'])->findOrFail($id);
        $this->abortIfPsmLocked($checklist);
        $valueMap = $this->buildPsmValueMap($checklist);
        $this->deletePreventiveMaintenancePhotos(
            $this->preventiveMaintenancePhotoPaths($valueMap[self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD] ?? null)
        );
        
        // Delete associated values
        PsmValue::where('psm_id', $id)->delete();
        
        $checklist->delete();
        return response()->json(['message' => 'Checklist deleted']);
    }

    /**
     * Disable an entire task category (all descriptions under the same item_no) in item_checklist_items.
     */
    public function disableItemChecklistTask(Request $request, int $itemNo)
    {
        return $this->setItemChecklistTaskEnabled($request, $itemNo, false);
    }

    public function enableItemChecklistTask(Request $request, int $itemNo)
    {
        return $this->setItemChecklistTaskEnabled($request, $itemNo, true);
    }

    /**
     * Disable a single description row in item_checklist_items.
     */
    public function disableItemChecklistItem(Request $request, int $id)
    {
        return $this->setItemChecklistItemEnabled($request, $id, false);
    }

    public function enableItemChecklistItem(Request $request, int $id)
    {
        return $this->setItemChecklistItemEnabled($request, $id, true);
    }

    public function lockPreventiveMaintenance(int $id)
    {
        return $this->setPsmLockState($id, 1, true);
    }

    public function unlockPreventiveMaintenance(int $id)
    {
        return $this->setPsmLockState($id, 1, false);
    }

    public function lockItemChecklist(int $id)
    {
        return $this->setPsmLockState($id, 2, true);
    }

    public function unlockItemChecklist(int $id)
    {
        return $this->setPsmLockState($id, 2, false);
    }

    public function listChecklistItems(Request $request)
    {
        $request->validate([
            'checklist_type' => ['nullable', Rule::in(['pc', 'server', 'ip_phone', 'network_device', 'wifi', 'ups', 'cctv'])],
        ]);

        $checklistType = $this->normalizeChecklistType($request->query('checklist_type', 'pc'));
        $this->ensureItemChecklistEntriesForType($checklistType);

        $modelClass = $this->itemChecklistEntryModelClass($checklistType);
        $table = $this->itemChecklistEntryTable($checklistType);
        $query = $modelClass::query();

        if (Schema::hasColumn($table, 'checklist_type')) {
            $query->where('checklist_type', $checklistType);
        }

        $items = $query
            ->orderBy('item_no')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'checklist_type' => $checklistType,
                'item_no' => $item->item_no,
                'task' => $item->task,
                'description' => $item->description,
                'enabled' => (bool) $item->enabled,
                'sort_order' => $item->sort_order,
            ])
            ->values();

        return response()->json([
            'checklist_type' => $checklistType,
            'checklist_type_label' => $this->checklistTypeLabel($checklistType),
            'items' => $items,
        ]);
    }

    public function printItemChecklist(Request $request, $id)
    {
        $format = $request->query('format', 'pdf');
        $itemChecklist = Psm::with(['values.variable'])->findOrFail($id);
        
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $valueMap = $this->buildPsmValueMap($itemChecklist, true);

        $parentPsmId = $valueMap['parent_psm_id'] ?? null;
        $preventiveMaintenance = $parentPsmId ? Psm::with(['values.variable'])->find($parentPsmId) : null;
        
        $pmValueMap = $preventiveMaintenance ? $this->buildPsmValueMap($preventiveMaintenance) : [];

        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance, $pmValueMap);
        $dbEntries = $this->enabledItemChecklistEntries($checklistType);
        $entries = $dbEntries->map(function ($e, $i) use ($valueMap) {
            $status = $valueMap['item_' . $i] ?? null;
            return [
                'item_no' => $e->item_no,
                'task' => $e->task,
                'description' => $e->description,
                'status' => $status,
                'sort_order' => $e->sort_order,
            ];
        })->values()->all();

        $summaryState = $this->resolveItemChecklistSummary($itemChecklist->psm_id ?? null);

        // If linked to a Preventive Maintenance checklist, prefer the PM DOCX
        // template for the first page and append the item checklist page.
        if ($preventiveMaintenance) {
            $pmTemplateHtml = $this->createFilledPmTemplateHtml($preventiveMaintenance, $pmValueMap);
            $itemOnlyHtml = $this->generateItemChecklistHtml(
                $itemChecklist,
                $entries,
                $valueMap,
                $preventiveMaintenance,
                $pmValueMap,
                $summaryState,
                $checklistType
            );

            if ($pmTemplateHtml) {
                $itemBody = $this->extractHtmlBody($itemOnlyHtml);
                $html = preg_replace(
                    '/<\/body>\s*<\/html>\s*$/i',
                    '<div style="page-break-after: always;"></div>' . $itemBody . '</body></html>',
                    $pmTemplateHtml,
                    1
                );

                if (! is_string($html) || $html === '') {
                    $html = $pmTemplateHtml;
                }
            } else {
            $referenceData = $this->pmReferenceData();
            $equipment = $referenceData['equipment'];
            $operatingSystems = $referenceData['operatingSystems'];
            $softwareApplications = $referenceData['softwareApplications'];
            $specificationFields = $referenceData['specificationFields'];

            $html = $this->generateCombinedPrintHtml(
                $preventiveMaintenance,
                $pmValueMap,
                $equipment,
                $operatingSystems,
                $softwareApplications,
                $specificationFields,
                $itemChecklist,
                $entries,
                $valueMap,
                $summaryState,
                $checklistType
            );
            }
        } else {
            // Fallback: item checklist page only
            $html = $this->generateItemChecklistHtml(
                $itemChecklist,
                $entries,
                $valueMap,
                $preventiveMaintenance,
                $pmValueMap,
                $summaryState,
                $checklistType
            );
        }

        if ($format === 'word') {
            if ($preventiveMaintenance) {
                return $this->generateWordFromTemplate(
                    $preventiveMaintenance,
                    $pmValueMap,
                    $valueMap,
                    $summaryState
                );
            }
            // Fallback to HTML if no parent PM
            return response($html, 200)
                ->header('Content-Type', 'application/msword')
                ->header('Content-Disposition', 'attachment; filename="' . $this->getExportFilename('docx', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)) . '"');
        }

        if ($format === 'pdf' && $preventiveMaintenance) {
            $templatedPdf = $this->generatePdfFromDocxTemplate(
                $id,
                $preventiveMaintenance,
                $pmValueMap,
                $valueMap,
                $summaryState,
                $this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap))
            );
            if ($templatedPdf) {
                return $templatedPdf;
            }
        }

        // Prefer server-side PDF generation when available
        if (class_exists(DomPdfFacade::class)) {
            try {
                $pdf = DomPdfFacade::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                return $pdf->download($this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)));
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        if (class_exists(Dompdf::class)) {
            try {
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return response($dompdf->output(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)) . '"');
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        // Fallback: return HTML (browser will render it)
        return response($html, 200)
            ->header('Content-Type', 'text/html');
    }

    public function printPreventiveMaintenance(Request $request, $id)
    {
        $format = $request->query('format', 'pdf');
        $checklist = Psm::with(['values.variable'])->findOrFail($id);
        
        if ((int) $checklist->template_psm_id !== 1) {
            abort(404, 'Not a preventive maintenance checklist');
        }
        $this->abortUnlessCanAccessPsm($checklist);

        $valueMap = $this->buildPsmValueMap($checklist);
        $checklistType = $this->resolvePmTemplateChecklistType($checklist, $valueMap);

        $referenceData = $this->pmReferenceData();
        $equipment = $referenceData['equipment'];
        $operatingSystems = $referenceData['operatingSystems'];
        $softwareApplications = $referenceData['softwareApplications'];
        $specificationFields = $referenceData['specificationFields'];

        // Build PM page from DOCX template first.
        $pmTemplateHtml = $this->createFilledPmTemplateHtml($checklist, $valueMap);

        // If there is a linked item checklist, render a two-page export (PM first, Item Checklist second)
        $html = null;
        $latestItemChecklistId = null;
        $latestItemChecklistValueMap = null;
        $latestItemChecklistSummaryState = null;
        $parentVarId = PsmVariable::where('psm_id', 2)->where('name', 'parent_psm_id')->value('psm_var_id');
        if ($parentVarId) {
            $latestItemChecklistId = PsmValue::where('psm_var_id', $parentVarId)
                ->where('value', (string) $id)
                ->orderBy('created_at', 'desc')
                ->value('psm_id');

            if ($latestItemChecklistId) {
                $itemChecklist = Psm::with(['values.variable'])->find($latestItemChecklistId);
                if ($itemChecklist && (int) $itemChecklist->template_psm_id === 2) {
                    $icValueMap = $this->buildPsmValueMap($itemChecklist, true);

                    $checklistType = $this->resolvePreventiveMaintenanceChecklistType($checklist, $valueMap);
                    $dbEntries = $this->enabledItemChecklistEntries($checklistType);
                    $entries = $dbEntries->map(function ($e, $i) use ($icValueMap) {
                        $status = $icValueMap['item_' . $i] ?? null;
                        return [
                            'item_no' => $e->item_no,
                            'task' => $e->task,
                            'description' => $e->description,
                            'status' => $status,
                            'sort_order' => $e->sort_order,
                        ];
                    })->values()->all();

                    $summaryState = $this->resolveItemChecklistSummary($itemChecklist->psm_id ?? null);
                    $latestItemChecklistValueMap = $icValueMap;
                    $latestItemChecklistSummaryState = $summaryState;

                    $itemOnlyHtml = $this->generateItemChecklistHtml(
                        $itemChecklist,
                        $entries,
                        $icValueMap,
                        $checklist,
                        $valueMap,
                        $summaryState
                    );

                    if ($pmTemplateHtml) {
                        $itemBody = $this->extractHtmlBody($itemOnlyHtml);
                        $html = preg_replace(
                            '/<\/body>\s*<\/html>\s*$/i',
                            '<div style="page-break-after: always;"></div>' . $itemBody . '</body></html>',
                            $pmTemplateHtml,
                            1
                        );

                        if (! is_string($html) || $html === '') {
                            $html = $pmTemplateHtml;
                        }
                    } else {
                        $html = $this->generateCombinedPrintHtml(
                            $checklist,
                            $valueMap,
                            $equipment,
                            $operatingSystems,
                            $softwareApplications,
                            $specificationFields,
                            $itemChecklist,
                            $entries,
                            $icValueMap,
                            $summaryState
                        );
                    }
                }
            }
        }

        // Fallback: PM only page
        if (! $html) {
            $html = $pmTemplateHtml ?: $this->generatePreventiveMaintenanceHtml($checklist, $valueMap, $equipment, $operatingSystems, $softwareApplications, $specificationFields);
        }

        if ($format === 'word') {
            return $this->generateWordFromTemplate($checklist, $valueMap);
        }

        if ($format === 'pdf') {
            $templatedPdf = $this->generatePdfFromDocxTemplate(
                $id,
                $checklist,
                $valueMap,
                is_array($latestItemChecklistValueMap) ? $latestItemChecklistValueMap : null,
                is_array($latestItemChecklistSummaryState) ? $latestItemChecklistSummaryState : null,
                $this->getExportFilename('pdf', $checklistType)
            );
            if ($templatedPdf) {
                return $templatedPdf;
            }
        }

        // Prefer server-side PDF generation when available
        if (class_exists(DomPdfFacade::class)) {
            try {
                $pdf = DomPdfFacade::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                return $pdf->download($this->getExportFilename('pdf', $checklistType));
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        if (class_exists(Dompdf::class)) {
            try {
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return response($dompdf->output(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $this->getExportFilename('pdf', $checklistType) . '"');
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        // Fallback: return HTML (browser will render it)
        return response($html, 200)
            ->header('Content-Type', 'text/html');
    }

    private function generateItemChecklistHtml($itemChecklist, $entries, $valueMap, $preventiveMaintenance, $pmValueMap, array $summaryState, $checklistType = 'pc')
    {
        $maintenanceDate = $valueMap['maintenance_date'] ?? '';
        $itemIdentifier = $this->itemChecklistIdentifier($itemChecklist, $preventiveMaintenance, $valueMap, $pmValueMap);
        $checkedBy = $valueMap['checked_by'] ?? '';
        $notedBy = $this->resolveItemChecklistNotedBy($valueMap, $checklistType);
        $conformeBy = $valueMap['conforme_by'] ?? '';
        $summaryEnabled = $summaryState['enabled'] ?? true;
        $summaryRecommendation = $summaryState['text'] ?? '';

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ITEM CHECKLIST</title>
    <style>
        @page { size: 8.5in 13in; margin: 6mm 6mm; }
        @page WordSection1 { size: 8.5in 13in; margin: 6mm 6mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }
        .WordSection1 { page: WordSection1; }

        .page { border: 1px solid #000; padding: 4px 4px 6px 4px; box-sizing: border-box; }
        .title { text-align: center; font-size: 15px; font-weight: bold; margin: 2px 0 6px 0; letter-spacing: 0.3px; }
        .meta { text-align: right; font-size: 11px; margin-bottom: 6px; }

        .table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
        .table th, .table td { border: 1px solid #000; padding: 3px 3px; vertical-align: top; }
        .table th { background: #f2f2f2; font-weight: bold; text-align: center; }
        .table td:nth-child(3) { word-break: break-word; }
        .task { font-weight: bold; }
        .status { text-align: center; width: 8%; }

        .summary { border: 1px solid #000; min-height: 60px; padding: 6px 8px; margin: 6px 0 4px 0; font-size: 11px; overflow-wrap: anywhere; word-break: break-word; text-align: left; }
        .summary b { display: block; margin-bottom: 4px; font-weight: bold; }
        .note { font-size: 10px; font-style: italic; margin-top: 4px; }

        .signatures { display: flex; justify-content: space-between; gap: 5px; margin-top: 10px; }
        .sig-box { flex: 1; border: 1px solid #000; padding: 4px; text-align: center; font-size: 10.5px; }
        .sig-line { border-top: 1px solid #000; margin: 12px 0 3px 0; padding-top: 3px; }
    </style>
</head>
<body class="WordSection1">
    <div class="page">
        <div class="title">ITEM CHECKLIST</div>
        <div class="meta">' . htmlspecialchars($itemIdentifier) . ' &nbsp; Maintenance Date: ' . htmlspecialchars($maintenanceDate) . '</div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 7%;">ITEM #</th>
                    <th style="width: 16%;">TASK</th>
                    <th style="width: 50%;">DESCRIPTION</th>
                    <th class="status">OK</th>
                    <th class="status">REPAIR</th>
                    <th class="status">N/A</th>
                </tr>
            </thead>
            <tbody>';

        $groupedEntries = [];
        foreach ($entries as $entry) {
            $key = $entry['item_no'] . '|' . $entry['task'];
            if (! isset($groupedEntries[$key])) {
                $groupedEntries[$key] = [
                    'item_no' => $entry['item_no'],
                    'task' => $entry['task'],
                    'rows' => [],
                ];
            }
            $groupedEntries[$key]['rows'][] = $entry;
        }

        foreach ($groupedEntries as $group) {
            $rowspan = count($group['rows']);
            foreach ($group['rows'] as $idx => $entry) {
                $status = $entry['status'] ?? null;
                // Use forward slash to mark selections as requested.
                $okChecked = $status === 'ok' ? '/' : '&nbsp;';
                $repairChecked = $status === 'repair' ? '/' : '&nbsp;';
                $naChecked = ($status === 'na' || $status === 'n/a' || $status === '?') ? '/' : '&nbsp;';

                $html .= '<tr>';
                if ($idx === 0) {
                    $html .= '<td rowspan="' . $rowspan . '">' . htmlspecialchars($group['item_no']) . '</td>';
                    $html .= '<td class="task" rowspan="' . $rowspan . '">' . htmlspecialchars($group['task']) . '</td>';
                }

                $html .= '<td>' . htmlspecialchars($entry['description']) . '</td>
                    <td class="status">' . $okChecked . '</td>
                    <td class="status">' . $repairChecked . '</td>
                    <td class="status">' . $naChecked . '</td>
                </tr>';
            }
        }

        $html .= '</tbody>
        </table>';

        if ($summaryEnabled) {
            $html .= '

        <div class="summary">
            <b>Summary/Recommendation:</b>
            ' . nl2br(htmlspecialchars($summaryRecommendation)) . '
        </div>';
        }

        $html .= '

        <div class="note">Note: To be filled by Technician attending to ICT Equipment.</div>

        <div class="signatures">
            <div class="sig-box">
                <div><b>Checked by:</b></div>
                <div><b>' . htmlspecialchars($checkedBy) . '</b></div>
                <div class="sig-line"></div>
                <div>Signature over Printed Name</div>
                <div>Technician</div>
            </div>
            <div class="sig-box">
                <div><b>' . ($checklistType === 'ip_phone' ? 'Noted by:' : 'Conforme:') . '</b></div>
                <div><b>' . htmlspecialchars($checklistType === 'ip_phone' ? $notedBy : $conformeBy) . '</b></div>
                <div class="sig-line"></div>
                <div>Signature over Printed Name</div>
                <div>' . ($checklistType === 'ip_phone' ? 'DTO Chief' : 'End User') . '</div>
            </div>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    private function generatePreventiveMaintenanceHtml($checklist, $valueMap, $equipment, $operatingSystems, $softwareApplications, $specificationFields)
    {
        $pmValueMap = $valueMap;
        $pcName = $valueMap['pc_name'] ?? $checklist->name ?? '';
        $userOperator = $valueMap['user_operator'] ?? '';
        $officeCollege = $valueMap['office_college'] ?? '';
        $department = $valueMap['department'] ?? '';
        $dateAcquired = $valueMap['date_acquired'] ?? '';
        $checklistDate = $valueMap['checklist_date'] ?? date('Y-m-d');
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PREVENTIVE MAINTENANCE CHECKLIST</title>
    <style>
        @page { size: 8.5in 13in; margin: 12mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }

        .page { border: 1px solid #000; padding: 12px 12px 14px 12px; box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; }
        .header-text { flex: 1; text-align: center; line-height: 1.2; font-size: 11px; }
        .header-text .university { font-weight: bold; font-size: 12px; }
        .header-text .office { margin-top: 4px; font-weight: bold; }
        .header-text .title { font-weight: bold; font-size: 14px; margin-top: 6px; letter-spacing: 0.3px; }
        .date { min-width: 120px; text-align: right; font-size: 11px; margin-top: 2px; }

        .info-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; table-layout: fixed; }
        .info-table td { border: 1px solid #000; padding: 6px 7px; }
        .info-table .label { width: 32%; background: #f7f7f7; font-weight: bold; }

        .section-label { font-weight: bold; font-size: 12px; margin: 10px 0 6px 0; }
        .checkbox-grid { display: flex; flex-wrap: wrap; column-gap: 18px; row-gap: 6px; font-size: 11px; align-items: center; }
        .checkbox-row { display: flex; align-items: center; gap: 6px; min-width: 160px; }
        .checkbox-row.os { min-width: 150px; }
        .checkbox-row.software { min-width: 170px; }
        .checkbox-row.full { flex: 1 0 100%; }
        .box { width: 13px; height: 13px; border: 1px solid #000; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; }
        .line { flex: 1; border-bottom: 1px solid #000; min-width: 70px; display: inline-block; height: 13px; }
        .checkbox-row.full { flex: 1 0 100%; }
        .box { width: 13px; height: 13px; border: 1px solid #000; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; }
        .line { flex: 1; border-bottom: 1px solid #000; min-width: 70px; display: inline-block; height: 13px; }

        .spec-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11px; table-layout: fixed; }
        .spec-table td { border: 1px solid #000; padding: 6px 7px; }
        .spec-table .label { width: 36%; background: #f7f7f7; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-text">
                <div>Republic of the Philippines</div>
                <div class="university">CENTRAL MINDANAO UNIVERSITY</div>
                <div>University Town, Musuan, Bukidnon</div>
                <div class="office">OFFICE OF DIGITAL TRANSFORMATION</div>
                <div class="title">PREVENTIVE MAINTENANCE CHECKLIST</div>
            </div>
            <div class="date">Date: ' . htmlspecialchars($checklistDate) . '</div>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">User/Operator:</td>
                <td>' . htmlspecialchars($userOperator) . '</td>
            </tr>
            <tr>
                <td class="label">Office/College:</td>
                <td>' . htmlspecialchars($officeCollege) . '</td>
            </tr>
            <tr>
                <td class="label">Department:</td>
                <td>' . htmlspecialchars($department) . '</td>
            </tr>
            <tr>
                <td class="label">Date Acquired:</td>
                <td>' . htmlspecialchars($dateAcquired) . '</td>
            </tr>
            <tr>
                <td class="label">PC Name:</td>
                <td>' . htmlspecialchars($pcName) . '</td>
            </tr>
        </table>

        <div class="section-label">Equipment Installed</div>
        <div class="checkbox-grid">';

        foreach ($equipment as $item) {
            $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name));
            $isChecked = !empty($valueMap[$fieldName]) && $valueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($item->name) . '</span></div>';
        }
        $otherMark = (!empty($valueMap['equipment_others']) && $valueMap['equipment_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row full"><span class="box">' . $otherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($valueMap['equipment_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Operating System Installed</div>
        <div class="checkbox-grid" style="grid-template-columns: repeat(4, 1fr);">';

        foreach ($operatingSystems as $os) {
            $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $os->name));
            $isChecked = !empty($valueMap[$fieldName]) && $valueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row os"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($os->name) . '</span></div>';
        }
        $osOtherMark = (!empty($valueMap['os_others']) && $valueMap['os_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row os full"><span class="box">' . $osOtherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($valueMap['os_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Software/Applications Installed</div>
        <div class="checkbox-grid" style="grid-template-columns: repeat(3, 1fr);">';

        foreach ($softwareApplications as $software) {
            $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $software->name));
            $isChecked = !empty($valueMap[$fieldName]) && $valueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row software"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($software->name) . '</span></div>';
        }
        $softOtherMark = (!empty($valueMap['software_others']) && $valueMap['software_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row software full"><span class="box">' . $softOtherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($valueMap['software_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Desktop/Laptop Specifications</div>
        <table class="spec-table">';

        foreach ($specificationFields as $field) {
            if ($field->name === 'ip_address') {
                continue;
            }
            $value = $valueMap[$field->name] ?? '';
            if ($field->name === 'mac_address' && isset($valueMap['ip_address'])) {
                $value .= ($value ? ' / ' : '') . $valueMap['ip_address'];
            }
            $html .= '<tr>
                <td class="label">' . htmlspecialchars($field->label) . ':</td>
                <td>' . htmlspecialchars($value) . '</td>
            </tr>';
        }

        $html .= '</table>
    </div>
</body>
</html>';

        return $html;
    }

    public function printItemChecklistWithPM(Request $request, $id)
    {
        $format = $request->query('format', 'pdf');
        $inlinePdf = $request->boolean('inline') || $request->boolean('view');
        $itemChecklist = Psm::with(['values.variable'])->findOrFail($id);
        
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $valueMap = $this->buildPsmValueMap($itemChecklist, true);

        $parentPsmId = $valueMap['parent_psm_id'] ?? null;
        if (!$parentPsmId) {
            abort(404, 'Parent preventive maintenance not found');
        }

        // Always include the full Preventive Maintenance Checklist page
        // together with the Item Checklist page, regardless of dates.
        $preventiveMaintenance = Psm::with(['values.variable'])->findOrFail($parentPsmId);
        $this->abortUnlessCanAccessPsm($preventiveMaintenance);
        
        $pmValueMap = $this->buildPsmValueMap($preventiveMaintenance);

        // Use the combined-print HTML which already follows
        // the updated templates for both pages.
        $summaryState = $this->resolveItemChecklistSummary($itemChecklist->psm_id ?? null);

        if ($format === 'word') {
            return $this->generateWordFromTemplate(
                $preventiveMaintenance,
                $pmValueMap,
                $valueMap,
                $summaryState
            );
        }

        if ($format === 'pdf') {
            $templatedPdf = $this->generatePdfFromDocxTemplate(
                $id,
                $preventiveMaintenance,
                $pmValueMap,
                $valueMap,
                $summaryState,
                $this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)),
                $inlinePdf
            );
            if ($templatedPdf) {
                return $templatedPdf;
            }
        }

        $referenceData = $this->pmReferenceData();
        $equipment = $referenceData['equipment'];
        $operatingSystems = $referenceData['operatingSystems'];
        $softwareApplications = $referenceData['softwareApplications'];
        $specificationFields = $referenceData['specificationFields'];

        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance, $pmValueMap);
        $dbEntries = $this->enabledItemChecklistEntries($checklistType);
        $entries = $dbEntries->map(function ($e, $i) use ($valueMap) {
            $status = $valueMap['item_' . $i] ?? null;
            return [
                'item_no' => $e->item_no,
                'task' => $e->task,
                'description' => $e->description,
                'status' => $status,
                'sort_order' => $e->sort_order,
            ];
        })->values()->all();

        $pmTemplateHtml = $this->createFilledPmTemplateHtml($preventiveMaintenance, $pmValueMap);
        $itemOnlyHtml = $this->generateItemChecklistHtml(
            $itemChecklist,
            $entries,
            $valueMap,
            $preventiveMaintenance,
            $pmValueMap,
            $summaryState
        );

        if ($pmTemplateHtml) {
            $itemBody = $this->extractHtmlBody($itemOnlyHtml);
            $html = preg_replace(
                '/<\/body>\s*<\/html>\s*$/i',
                '<div style="page-break-after: always;"></div>' . $itemBody . '</body></html>',
                $pmTemplateHtml,
                1
            );

            if (! is_string($html) || $html === '') {
                $html = $pmTemplateHtml;
            }
        } else {
            $html = $this->generateCombinedPrintHtml(
                $preventiveMaintenance,
                $pmValueMap,
                $equipment,
                $operatingSystems,
                $softwareApplications,
                $specificationFields,
                $itemChecklist,
                $entries,
                $valueMap,
                $summaryState
            );
        }

        // Prefer server-side PDF generation when available
        if (class_exists(DomPdfFacade::class)) {
            try {
                $pdf = DomPdfFacade::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                return $inlinePdf
                    ? $pdf->stream($this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)))
                    : $pdf->download($this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)));
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        if (class_exists(Dompdf::class)) {
            try {
            $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return response($dompdf->output(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', ($inlinePdf ? 'inline' : 'attachment') . '; filename="' . $this->getExportFilename('pdf', $this->resolvePmTemplateChecklistType($preventiveMaintenance, $pmValueMap)) . '"');
            } catch (\Throwable $e) {
                // fall back to HTML
            }
        }

        // Fallback: return HTML (browser will render it)
        return response($html, 200)
            ->header('Content-Type', 'text/html');
    }

    public function printItemChecklistQrCode(Request $request, $id)
    {
        $label = $this->thermalLabelData((int) $id);
        $pdfUrl = $this->shortChecklistScanUrl((int) $id);

        $this->printThermalLabel(function ($printer) use ($label, $pdfUrl) {
            $this->printThermalHeader($printer, $label);
            $this->printQrSymbol($printer, $pdfUrl);
            $this->printThermalFooter($printer, 'Scan to view PDF');
        });

        return response()->json([
            'message' => 'QR code label sent to PT-210.',
            'pdf_url' => $pdfUrl,
        ]);
    }

    public function printItemChecklistBarcode(Request $request, $id)
    {
        $label = $this->thermalLabelData((int) $id);
        $code = $this->checklistScanCode((int) $id);
        $scanUrl = $this->shortChecklistScanUrl((int) $id);

        $barcodePath = $this->createTemporaryBarcodeImage($scanUrl);

        try {
            $this->printThermalLabel(function ($printer) use ($label, $barcodePath) {
                $this->printThermalHeader($printer, $label);
                $this->printImage($printer, $barcodePath);
                $this->printThermalFooter($printer, 'Scan to view PDF');
            });
        } finally {
            $this->deleteTemporaryLabelImage($barcodePath);
        }

        return response()->json([
            'message' => 'Barcode label sent to PT-210.',
            'code' => $code,
            'scan_url' => $scanUrl,
        ]);
    }

    public function redirectScannedChecklist(string $code)
    {
        if (! preg_match('/^CHECKLIST-(\d+)$/i', $code, $matches)) {
            abort(404, 'Invalid checklist code.');
        }

        $itemChecklist = Psm::findOrFail((int) $matches[1]);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist.');
        }

        return redirect()->to($this->itemChecklistPdfUrl((int) $itemChecklist->psm_id));
    }

    public function redirectChecklistByItemId(int $id)
    {
        $itemChecklist = Psm::findOrFail($id);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist.');
        }

        return redirect()->to($this->itemChecklistPdfUrl((int) $itemChecklist->psm_id));
    }

    public function printTest()
    {
        $this->printThermalLabel(function ($printer) {
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
            $printer->text("PT-210 TEST PRINT SUCCESSFUL\n");
            $printer->feed(3);
        });

        return response('PT-210 TEST PRINT SUCCESSFUL');
    }

    private function thermalLabelData(int $itemChecklistId): array
    {
        $itemChecklist = Psm::with(['values.variable'])->findOrFail($itemChecklistId);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist.');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $itemValueMap = $this->buildPsmValueMap($itemChecklist, true);
        $parentPsmId = $itemValueMap['parent_psm_id'] ?? null;
        if (! $parentPsmId) {
            abort(404, 'Parent preventive maintenance not found.');
        }

        $preventiveMaintenance = Psm::with(['values.variable'])->findOrFail($parentPsmId);
        $this->abortUnlessCanAccessPsm($preventiveMaintenance);
        $pmValueMap = $this->buildPsmValueMap($preventiveMaintenance);
        $checklistType = $this->resolvePreventiveMaintenanceChecklistType($preventiveMaintenance, $pmValueMap);

        return [
            'id' => $itemChecklistId,
            'identifier' => $preventiveMaintenance->preventiveMaintenanceIdentifier($checklistType),
            'product_name' => $this->resolvePreventiveMaintenanceAssetName(
                $pmValueMap,
                $preventiveMaintenance->name,
                $checklistType
            ),
        ];
    }

    private function printThermalLabel(callable $callback, string $profileName = 'simple'): void
    {
        $requiredClasses = [
            \Mike42\Escpos\Printer::class,
            \Mike42\Escpos\CapabilityProfile::class,
            \Mike42\Escpos\PrintConnectors\WindowsPrintConnector::class,
        ];

        foreach ($requiredClasses as $class) {
            if (! class_exists($class)) {
                abort(500, 'Thermal printer package is missing. Run: composer require mike42/escpos-php');
            }
        }

        $printer = null;
        $connector = null;

        try {
            $profile = \Mike42\Escpos\CapabilityProfile::load($profileName);
            $connector = new \Mike42\Escpos\PrintConnectors\WindowsPrintConnector(
                env('PT210_PRINTER_SHARE', 'smb://localhost/PT210')
            );
            $printer = new \Mike42\Escpos\Printer($connector, $profile);
            $printer->initialize();

            $callback($printer);

            $printer->feed(2);
            $printer->close();
        } catch (\Throwable $e) {
            if ($printer) {
                try {
                    $printer->close();
                } catch (\Throwable $closeException) {
                    // Ignore close failures so the original printer error is shown.
                }
            }

            abort(500, 'Unable to print to PT-210: ' . $e->getMessage());
        }
    }

    private function printThermalHeader($printer, array $label): void
    {
        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(\Mike42\Escpos\Printer::MODE_EMPHASIZED);
        $printer->text("Central Mindanao University\n");
        $printer->selectPrintMode();
        $printer->text("Preventive Maintenance Checklist\n");
        $printer->text('Checklist ID: ' . ($label['identifier'] ?? $label['id']) . "\n");
        $printer->text($this->fitThermalLine('Product: ' . $label['product_name']) . "\n");
        $printer->feed(1);
    }

    private function printThermalFooter($printer, string $text): void
    {
        $printer->feed(1);
        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $printer->text($text . "\n");
    }

    private function printQrSymbol($printer, string $pdfUrl): void
    {
        $qrPath = $this->createTemporaryQrImage($pdfUrl);

        try {
            $printer->feed(1);
            $this->printImage($printer, $qrPath);
            $printer->feed(2);
        } finally {
            $this->deleteTemporaryLabelImage($qrPath);
        }
    }

    private function printImage($printer, string $imagePath): void
    {
        if (! class_exists(\Mike42\Escpos\EscposImage::class)) {
            abort(500, 'ESC/POS image printing support is missing.');
        }

        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_LEFT);
        $image = \Mike42\Escpos\EscposImage::load($imagePath, false, ['gd', 'native']);
        $printer->bitImage($image);
    }

    private function createTemporaryQrImage(string $pdfUrl): string
    {
        if (! extension_loaded('gd')) {
            abort(500, 'PHP GD extension is missing. Enable extension=gd in C:\\xampp\\php\\php.ini and restart Apache.');
        }

        if (! class_exists(\BaconQrCode\Encoder\Encoder::class)) {
            abort(500, 'QR package is missing. Run: composer require simplesoftwareio/simple-qrcode');
        }

        $path = $this->temporaryLabelImagePath('qr');
        $qrCode = \BaconQrCode\Encoder\Encoder::encode(
            $pdfUrl,
            \BaconQrCode\Common\ErrorCorrectionLevel::L(),
            'UTF-8'
        );
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();
        $paperWidth = 384;
        $quietZone = 4;
        $moduleSize = 6;
        $qrSize = ($matrixSize + ($quietZone * 2)) * $moduleSize;
        $imageWidth = $paperWidth;
        $imageHeight = $qrSize + 32;
        $offsetX = (int) (($imageWidth - $qrSize) / 2);
        $offsetY = 16;

        $image = imagecreatetruecolor($imageWidth, $imageHeight);
        if (! $image) {
            abort(500, 'Unable to create QR image.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix->get($x, $y)) {
                    imagefilledrectangle(
                        $image,
                        $offsetX + (($x + $quietZone) * $moduleSize),
                        $offsetY + (($y + $quietZone) * $moduleSize),
                        $offsetX + ((($x + $quietZone + 1) * $moduleSize) - 1),
                        $offsetY + ((($y + $quietZone + 1) * $moduleSize) - 1),
                        $black
                    );
                }
            }
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function createTemporaryBarcodeImage(string $code): string
    {
        if (! class_exists(\Picqer\Barcode\BarcodeGeneratorPNG::class)) {
            abort(500, 'Barcode package is missing. Run: composer require picqer/php-barcode-generator');
        }

        $path = $this->temporaryLabelImagePath('barcode');
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $rawBarcode = imagecreatefromstring($generator->getBarcode($code, $generator::TYPE_CODE_128, 1, 95));

        if (! $rawBarcode) {
            abort(500, 'Unable to create barcode image.');
        }

        $rawWidth = imagesx($rawBarcode);
        $rawHeight = imagesy($rawBarcode);
        $quietZone = 18;
        $targetWidth = 360;
        $targetHeight = 105;
        $barcodeWidth = min($targetWidth - ($quietZone * 2), $rawWidth);

        $image = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        imagecopyresampled(
            $image,
            $rawBarcode,
            (int) (($targetWidth - $barcodeWidth) / 2),
            5,
            0,
            0,
            $barcodeWidth,
            $targetHeight - 10,
            $rawWidth,
            $rawHeight
        );

        imagepng($image, $path);
        imagedestroy($rawBarcode);
        imagedestroy($image);

        return $path;
    }

    private function temporaryLabelImagePath(string $prefix): string
    {
        $directory = storage_path('app/thermal-labels');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory . DIRECTORY_SEPARATOR . $prefix . '-' . uniqid('', true) . '.png';
    }

    private function deleteTemporaryLabelImage(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    private function itemChecklistPdfUrl(int $itemChecklistId): string
    {
        return $this->appUrl('/api/item-checklist/' . $itemChecklistId . '/print-with-pm?format=pdf&inline=1');
    }

    private function checklistScanCode(int $itemChecklistId): string
    {
        return 'CHECKLIST-' . $itemChecklistId;
    }

    private function checklistScanUrl(string $code): string
    {
        return $this->publicAppUrl() . '/scan/checklist/' . rawurlencode($code);
    }

    private function shortChecklistScanUrl(int $itemChecklistId): string
    {
        return $this->publicAppUrl() . '/c/' . $itemChecklistId;
    }

    private function publicAppUrl(): string
    {
        $configuredAppUrl = rtrim((string) config('app.url'), '/');
        $configuredAppPath = trim((string) parse_url($configuredAppUrl, PHP_URL_PATH), '/');

        if ($configuredAppUrl !== '' && $configuredAppPath !== '') {
            return $configuredAppUrl;
        }

        $requestBaseUrl = request()->getSchemeAndHttpHost() . request()->getBaseUrl();
        $appBaseUrl = rtrim(preg_replace('#/public$#', '', $requestBaseUrl), '/');

        if ($appBaseUrl !== '') {
            return $appBaseUrl;
        }

        return $configuredAppUrl;
    }

    private function appUrl(string $path = ''): string
    {
        $normalizedPath = '/' . ltrim($path, '/');

        return $this->publicAppUrl() . ($normalizedPath === '/' ? '' : $normalizedPath);
    }

    private function fitThermalLine(string $text, int $width = 32): string
    {
        return mb_strimwidth($text, 0, $width, '...');
    }

    public function getItemChecklistViewPdfLink(Request $request, $id)
    {
        $itemChecklist = Psm::findOrFail($id);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $mode = $request->query('mode') === 'pdf' ? 'pdf' : 'viewer';
        $expiresAt = now()->addSeconds(self::VIEW_PDF_TTL_SECONDS);
        $signedUrl = URL::temporarySignedRoute(
            'api.item-checklist.view-pdf.signed',
            $expiresAt,
            ['id' => $id, 'mode' => $mode]
        );

        return response()->json([
            'url' => $signedUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'ttl_seconds' => self::VIEW_PDF_TTL_SECONDS,
        ]);
    }

    public function viewItemChecklistPdfSigned(Request $request, $id)
    {
        $mode = $request->query('mode', 'viewer');

        if ($mode === 'pdf') {
            $request->merge([
                'format' => 'pdf',
                'inline' => 1,
                'view' => 1,
            ]);

            return $this->printItemChecklistWithPM($request, $id);
        }

        $itemChecklist = Psm::findOrFail($id);
        if ((int) $itemChecklist->template_psm_id !== 2) {
            abort(404, 'Not an item checklist');
        }
        $this->abortUnlessCanAccessPsm($itemChecklist);

        $pdfExpiresAt = now()->addSeconds(self::VIEW_PDF_TTL_SECONDS);
        $initialPdfUrl = URL::temporarySignedRoute(
            'api.item-checklist.view-pdf.signed',
            $pdfExpiresAt,
            ['id' => $id, 'mode' => 'pdf']
        );

        $ttlSeconds = self::VIEW_PDF_TTL_SECONDS;

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMU-F-4-DTO-002-Preventive-Maintenance-Checklist     </title>
    <style>
        html, body { height: 100%; margin: 0; font-family: Arial, sans-serif; }
        body { background: #fff; }
        iframe { width: 100%; height: 100%; border: 0; }
        .expired { height: 100%; display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; box-sizing: border-box; }
        .expired h1 { margin: 0 0 8px; font-size: 20px; color: #111827; }
        .expired p { margin: 0; font-size: 14px; color: #4b5563; }
    </style>
</head>
<body>
    <iframe id="pdfFrame" src="' . htmlspecialchars($initialPdfUrl, ENT_QUOTES, 'UTF-8') . '"></iframe>
    <script>
        (function () {
            const ttlDefault = ' . (int) $ttlSeconds . ';
            const frame = document.getElementById("pdfFrame");

            function expireView() {
                if (frame) {
                    frame.src = "about:blank";
                }

                // Works when opened via window.open (your current flow).
                window.close();

                // Fallback if the browser blocks close.
                window.setTimeout(function () {
                    if (!window.closed) {
                        document.body.innerHTML = "<div class=\"expired\"><div><h1>PDF session expired</h1><p>This tab could not be closed automatically by your browser.</p></div></div>";
                    }
                }, 150);
            }

            const ms = Math.max(1000, ttlDefault * 1000);
            window.setTimeout(expireView, ms);
        })();
    </script>
</body>
</html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function generateCombinedPrintHtml($preventiveMaintenance, $pmValueMap, $equipment, $operatingSystems, $softwareApplications, $specificationFields, $itemChecklist, $entries, $valueMap, array $summaryState, $checklistType = 'pc')
    {
        $pcName = $pmValueMap['pc_name'] ?? $preventiveMaintenance->name ?? '';
        $userOperator = $pmValueMap['user_operator'] ?? '';
        $officeCollege = $pmValueMap['office_college'] ?? '';
        $department = $pmValueMap['department'] ?? '';
        $dateAcquired = $pmValueMap['date_acquired'] ?? '';
        $checklistDate = $pmValueMap['checklist_date'] ?? date('Y-m-d');
        
        $maintenanceDate = $valueMap['maintenance_date'] ?? '';
        $itemIdentifier = $this->itemChecklistIdentifier($itemChecklist, $preventiveMaintenance, $valueMap, $pmValueMap);
        $checkedBy = $valueMap['checked_by'] ?? '';
        $notedBy = $this->resolveItemChecklistNotedBy($valueMap, $checklistType);
        $conformeBy = $valueMap['conforme_by'] ?? '';
        $summaryEnabled = $summaryState['enabled'] ?? true;
        $summaryRecommendation = $summaryState['text'] ?? '';
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PREVENTIVE MAINTENANCE CHECKLIST WITH ITEM CHECKLIST</title>
    <style>
        @page { size: 8.5in 13in; margin: 6mm 6mm; }
        @page WordSection1 { size: 8.5in 13in; margin: 6mm 6mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }
        .WordSection1 { page: WordSection1; }
        .page { border: 1px solid #000; padding: 4px 4px 6px 4px; box-sizing: border-box; }
        .page-break { page-break-after: always; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; }
        .header-text { flex: 1; text-align: center; line-height: 1.2; font-size: 11px; }
        .header-text .university { font-weight: bold; font-size: 12px; }
        .header-text .office { margin-top: 4px; font-weight: bold; }
        .header-text .title { font-weight: bold; font-size: 14px; margin-top: 6px; letter-spacing: 0.3px; }
        .date { min-width: 120px; text-align: right; font-size: 11px; margin-top: 2px; }

        .info-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; table-layout: fixed; }
        .info-table td { border: 1px solid #000; padding: 5px 6px; }
        .info-table .label { width: 32%; background: #f7f7f7; font-weight: bold; }

        .section-label { font-weight: bold; font-size: 12px; margin: 10px 0 6px 0; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 16px; font-size: 11px; align-items: center; }
        .checkbox-row { display: flex; align-items: center; gap: 6px; }
        .box { width: 13px; height: 13px; border: 1px solid #000; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; }
        .line { flex: 1; border-bottom: 1px solid #000; min-width: 70px; display: inline-block; height: 13px; }

        .spec-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11px; table-layout: fixed; }
        .spec-table td { border: 1px solid #000; padding: 6px 7px; }
        .spec-table .label { width: 36%; background: #f7f7f7; font-weight: bold; }

        .ic-title { text-align: center; font-size: 15px; font-weight: bold; margin: 2px 0 6px 0; letter-spacing: 0.3px; }
        .meta { text-align: right; font-size: 11px; margin-bottom: 6px; }
        .ic-table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
        .ic-table th, .ic-table td { border: 1px solid #000; padding: 3px 3px; vertical-align: top; }
        .ic-table th { background: #f2f2f2; font-weight: bold; text-align: center; }
        .ic-table td:nth-child(3) { word-break: break-word; }
        .ic-task { font-weight: bold; }
        .ic-status { text-align: center; width: 8%; }
        .summary { border: 1px solid #000; min-height: 60px; padding: 6px 8px; margin: 6px 0 4px 0; font-size: 11px; overflow-wrap: anywhere; word-break: break-word; text-align: left; }
        .summary b { display: block; margin-bottom: 4px; font-weight: bold; }
        .note { font-size: 10px; font-style: italic; margin-top: 4px; }
        .signatures { display: flex; justify-content: space-between; gap: 5px; margin-top: 10px; }
        .sig-box { flex: 1; border: 1px solid #000; padding: 4px; text-align: center; font-size: 10.5px; }
        .sig-line { border-top: 1px solid #000; margin: 12px 0 3px 0; padding-top: 3px; }
    </style>
</head>
<body class="WordSection1">
    <div class="page">
        <div class="header">
            <div class="header-text">
                <div>Republic of the Philippines</div>
                <div class="university">CENTRAL MINDANAO UNIVERSITY</div>
                <div>University Town, Musuan, Bukidnon</div>
                <div class="office">OFFICE OF DIGITAL TRANSFORMATION</div>
                <div class="title">PREVENTIVE MAINTENANCE CHECKLIST</div>
            </div>
            <div class="date">Date: ' . htmlspecialchars($checklistDate) . '</div>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">User/Operator:</td>
                <td>' . htmlspecialchars($userOperator) . '</td>
            </tr>
            <tr>
                <td class="label">Office/College:</td>
                <td>' . htmlspecialchars($officeCollege) . '</td>
            </tr>
            <tr>
                <td class="label">Department:</td>
                <td>' . htmlspecialchars($department) . '</td>
            </tr>
            <tr>
                <td class="label">Date Acquired:</td>
                <td>' . htmlspecialchars($dateAcquired) . '</td>
            </tr>
            <tr>
                <td class="label">PC Name:</td>
                <td>' . htmlspecialchars($pcName) . '</td>
            </tr>
        </table>

        <div class="section-label">Equipment Installed</div>
        <div class="checkbox-grid">';

        foreach ($equipment as $item) {
            $fieldName = 'equipment_' . strtolower(str_replace(' ', '_', $item->name));
            $isChecked = !empty($pmValueMap[$fieldName]) && $pmValueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($item->name) . '</span></div>';
        }

        $otherMark = (!empty($pmValueMap['equipment_others']) && $pmValueMap['equipment_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row full"><span class="box">' . $otherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($pmValueMap['equipment_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Operating System Installed</div>
        <div class="checkbox-grid" style="grid-template-columns: repeat(4, 1fr);">';

        foreach ($operatingSystems as $os) {
            $fieldName = 'os_' . strtolower(str_replace([' ', '.', '-'], '_', $os->name));
            $isChecked = !empty($pmValueMap[$fieldName]) && $pmValueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row os"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($os->name) . '</span></div>';
        }

        $osOtherMark = (!empty($pmValueMap['os_others']) && $pmValueMap['os_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row os full"><span class="box">' . $osOtherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($pmValueMap['os_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Software/Applications Installed</div>
        <div class="checkbox-grid" style="grid-template-columns: repeat(3, 1fr);">';

        foreach ($softwareApplications as $software) {
            $fieldName = 'software_' . strtolower(str_replace([' ', '.', '-'], '_', $software->name));
            $isChecked = !empty($pmValueMap[$fieldName]) && $pmValueMap[$fieldName] !== '0';
            $mark = $isChecked ? '/' : '&nbsp;';
            $html .= '<div class="checkbox-row software"><span class="box">' . $mark . '</span><span>' . htmlspecialchars($software->name) . '</span></div>';
        }

        $softOtherMark = (!empty($pmValueMap['software_others']) && $pmValueMap['software_others'] !== '0') ? '/' : '&nbsp;';
        $html .= '<div class="checkbox-row software full"><span class="box">' . $softOtherMark . '</span><span>Others (Specify):</span><span class="line"></span><span>' . htmlspecialchars($pmValueMap['software_others_specify'] ?? '') . '</span></div>';

        $html .= '</div>

        <div class="section-label" style="margin-top: 12px;">Desktop/Laptop Specifications</div>
        <table class="spec-table">';

        foreach ($specificationFields as $field) {
            if ($field->name === 'ip_address') {
                continue;
            }
            $value = $pmValueMap[$field->name] ?? '';
            if ($field->name === 'mac_address' && isset($pmValueMap['ip_address'])) {
                $value .= ($value ? ' / ' : '') . $pmValueMap['ip_address'];
            }
            $html .= '<tr>
                <td class="label">' . htmlspecialchars($field->label) . ':</td>
                <td>' . htmlspecialchars($value) . '</td>
            </tr>';
        }

        $html .= '</table>
    </div>

    <div class="page-break"></div>

    <div class="page">
        <div class="ic-title">ITEM CHECKLIST</div>
        <div class="meta">' . htmlspecialchars($itemIdentifier) . ' &nbsp; Maintenance Date: ' . htmlspecialchars($maintenanceDate) . '</div>

        <table class="ic-table">
            <thead>
                <tr>
                    <th style="width: 7%;">ITEM #</th>
                    <th style="width: 16%;">TASK</th>
                    <th style="width: 50%;">DESCRIPTION</th>
                    <th class="ic-status">OK</th>
                    <th class="ic-status">REPAIR</th>
                    <th class="ic-status">N/A</th>
                </tr>
            </thead>
            <tbody>';

        $groupedEntries = [];
        foreach ($entries as $entry) {
            $key = $entry['item_no'] . '|' . $entry['task'];
            if (! isset($groupedEntries[$key])) {
                $groupedEntries[$key] = [
                    'item_no' => $entry['item_no'],
                    'task' => $entry['task'],
                    'rows' => [],
                ];
            }
            $groupedEntries[$key]['rows'][] = $entry;
        }

        foreach ($groupedEntries as $group) {
            $rowspan = count($group['rows']);
            foreach ($group['rows'] as $idx => $entry) {
                $status = $entry['status'] ?? null;
                $okChecked = $status === 'ok' ? '/' : '&nbsp;';
                $repairChecked = $status === 'repair' ? '/' : '&nbsp;';
                $naChecked = ($status === 'na' || $status === 'n/a' || $status === '?') ? '/' : '&nbsp;';

                $html .= '<tr>';
                if ($idx === 0) {
                    $html .= '<td rowspan="' . $rowspan . '">' . htmlspecialchars($group['item_no']) . '</td>';
                    $html .= '<td class="ic-task" rowspan="' . $rowspan . '">' . htmlspecialchars($group['task']) . '</td>';
                }

                $html .= '<td>' . htmlspecialchars($entry['description']) . '</td>
                    <td class="ic-status">' . $okChecked . '</td>
                    <td class="ic-status">' . $repairChecked . '</td>
                    <td class="ic-status">' . $naChecked . '</td>
                </tr>';
            }
        }

        $html .= '</tbody>
        </table>';

        if ($summaryEnabled) {
            $html .= '

        <div class="summary">
            <b>Summary/Recommendation:</b>
            ' . nl2br(htmlspecialchars($summaryRecommendation)) . '
        </div>';
        }

        $html .= '

        <div class="note">Note: To be filled by Technician attending to ICT Equipment.</div>

        <div class="signatures">
            <div class="sig-box">
                <div><b>Checked by:</b></div>
                <div><b>' . htmlspecialchars($checkedBy) . '</b></div>
                <div class="sig-line"></div>
                <div>Signature over Printed Name</div>
                <div>Technician</div>
            </div>
            <div class="sig-box">
                <div><b>' . ($checklistType === 'ip_phone' ? 'Noted by:' : 'Conforme:') . '</b></div>
                <div><b>' . htmlspecialchars($checklistType === 'ip_phone' ? $notedBy : $conformeBy) . '</b></div>
                <div class="sig-line"></div>
                <div>Signature over Printed Name</div>
                <div>' . ($checklistType === 'ip_phone' ? 'DTO Chief' : 'End User') . '</div>
            </div>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Resolve summary visibility and text for item checklist printing with global override.
     */
    private function resolveItemChecklistSummary($psmId): array
    {
        $globalSummaryRow = DB::table('item_checklist_summaries')
            ->where(function ($q) {
                $q->where('psm_id', 0)->orWhereNull('psm_id');
            })
            ->orderBy('id')
            ->first();

        $summaryRow = null;
        if ($psmId) {
            $summaryRow = DB::table('item_checklist_summaries')
                ->where('psm_id', $psmId)
                ->first();
        }

        if (! $summaryRow) {
            $summaryRow = $globalSummaryRow;
        }

        if (! $summaryRow) {
            $summaryRow = DB::table('item_checklist_summaries')
                ->orderBy('id')
                ->first();
        }

        $perRowEnabled = $summaryRow ? ((int) $summaryRow->enabled === 1) : true;
        $globalEnabled = $globalSummaryRow ? ((int) $globalSummaryRow->enabled === 1) : true;
        $enabled = $globalEnabled && $perRowEnabled;
        $text = $enabled ? ($summaryRow->summary_recommendation ?? '') : '';

        return [
            'enabled' => $enabled,
            'text' => $text,
        ];
    }

    private function pmReferenceData(): array
    {
        return [
            'equipment' => Equipment::where('enabled', true)->orderBy('sort_order')->get(),
            'operatingSystems' => $this->operatingSystemsQuery()->get(),
            'softwareApplications' => SoftwareApplication::where('enabled', true)->orderBy('sort_order')->get(),
            'specificationFields' => SpecificationField::where('enabled', true)->orderBy('sort_order')->get(),
        ];
    }

    private function normalizeChecklistType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        if ($normalized === 'server') {
            return 'server';
        }

        if (in_array($normalized, ['ip_phone', 'ip-phone', 'ipphone', 'ip phones', 'ip_phones'], true)) {
            return 'ip_phone';
        }

        if (in_array($normalized, ['network_device', 'network-device', 'networkdevice', 'network device'], true)) {
            return 'network_device';
        }

        if (in_array($normalized, ['wifi', 'wi-fi', 'wireless'], true)) {
            return 'wifi';
        }

        if ($normalized === 'ups') {
            return 'ups';
        }

        if ($normalized === 'cctv') {
            return 'cctv';
        }

        return 'pc';
    }

    private function checklistTypeLabel(?string $type): string
    {
        return match ($this->normalizeChecklistType($type)) {
            'server' => 'Server',
            'ip_phone' => 'IP Phone',
            'network_device' => 'Network Device',
            'wifi' => 'WiFi',
            'ups' => 'UPS',
            'cctv' => 'CCTV',
            default => 'PC',
        };
    }

    private function checklistAssetLabel(?string $type): string
    {
        return match ($this->normalizeChecklistType($type)) {
            'server' => 'Server Name',
            'ip_phone' => 'IP Phone',
            'network_device' => 'Product Name',
            'wifi' => 'Product Name',
            'ups' => 'Brand / Model',
            'cctv' => 'Product Name',
            default => 'PC Name',
        };
    }

    private function preventiveMaintenanceCustomFields(?string $checklistType = null): array
    {
        return match ($this->normalizeChecklistType($checklistType)) {
            'ip_phone' => [
                'brand_name' => ['description' => 'Brand Name', 'input_type' => 'text'],
                'model_name' => ['description' => 'Model Name', 'input_type' => 'text'],
                'serial_number' => ['description' => 'Serial Number', 'input_type' => 'text'],
                'office_located' => ['description' => 'Location / Office Located', 'input_type' => 'text'],
                'ip_address_tagged' => ['description' => 'IP Address Tagged', 'input_type' => 'text'],
                'vlan' => ['description' => 'VLAN', 'input_type' => 'text'],
                'telephone_number' => ['description' => 'Telephone Number', 'input_type' => 'text'],
            ],
            'network_device' => [
                'network_device_category_type' => ['description' => 'Category Type', 'input_type' => 'text'],
                'network_device_product_name' => ['description' => 'Product Name', 'input_type' => 'text'],
                'network_device_model_name' => ['description' => 'Model Name', 'input_type' => 'text'],
                'network_device_serial' => ['description' => 'Serial Number', 'input_type' => 'text'],
                'network_device_mac_address' => ['description' => 'MAC Address', 'input_type' => 'text'],
                'network_device_office_location' => ['description' => 'Office Location', 'input_type' => 'text'],
                'network_device_ip_address' => ['description' => 'IP Address', 'input_type' => 'text'],
                'network_device_vlan' => ['description' => 'VLAN', 'input_type' => 'text'],
            ],
            'wifi' => [
                'wifi_category_type' => ['description' => 'Category Type', 'input_type' => 'text'],
                'wifi_product_name' => ['description' => 'Product Name', 'input_type' => 'text'],
                'wifi_model_name' => ['description' => 'Model Name', 'input_type' => 'text'],
                'wifi_serial' => ['description' => 'Serial', 'input_type' => 'text'],
                'wifi_mac_address' => ['description' => 'MAC Address', 'input_type' => 'text'],
                'wifi_office_location' => ['description' => 'Office Located', 'input_type' => 'text'],
                'wifi_ip_address' => ['description' => 'IP Address', 'input_type' => 'text'],
                'wifi_vlan' => ['description' => 'VLAN', 'input_type' => 'text'],
                'wifi_name' => ['description' => 'WiFi Name', 'input_type' => 'text'],
                'wifi_password' => ['description' => 'WiFi Password', 'input_type' => 'text'],
                'wifi_channel_supported' => ['description' => 'Channel Supported', 'input_type' => 'text'],
            ],
            'ups' => [
                'ups_category' => ['description' => 'Category', 'input_type' => 'text'],
                'ups_brand_name' => ['description' => 'Brand Name', 'input_type' => 'text'],
                'ups_model_name' => ['description' => 'Model Name', 'input_type' => 'text'],
                'ups_mac_address' => ['description' => 'MAC Address', 'input_type' => 'text'],
                'ups_serial' => ['description' => 'Serial', 'input_type' => 'text'],
                'ups_total_power_capacity' => ['description' => 'Total Power or Capacity', 'input_type' => 'text'],
            ],
            'cctv' => [
                'cctv_category_type' => ['description' => 'Category Type', 'input_type' => 'text'],
                'cctv_product_name' => ['description' => 'Product Name', 'input_type' => 'text'],
                'cctv_model_name' => ['description' => 'Model Name', 'input_type' => 'text'],
                'cctv_serial' => ['description' => 'Serial', 'input_type' => 'text'],
                'cctv_mac_address' => ['description' => 'MAC Address', 'input_type' => 'text'],
                'cctv_office_location' => ['description' => 'Office Located', 'input_type' => 'text'],
                'cctv_ip_address' => ['description' => 'IP Address', 'input_type' => 'text'],
                'cctv_vlan' => ['description' => 'VLAN', 'input_type' => 'text'],
            ],
            default => [],
        };
    }

    private function ensurePreventiveMaintenanceTemplateVariables(int $templatePsmId): void
    {
        PsmVariable::firstOrCreate(
            ['psm_id' => $templatePsmId, 'name' => 'checklist_type'],
            [
                'description' => 'Checklist Type',
                'enabled' => 1,
                'input_type' => 'select',
            ]
        );

        $baseFields = [
            'user_operator' => ['description' => 'User/Operator', 'input_type' => 'text'],
            'college_office_id' => ['description' => 'College/Office ID', 'input_type' => 'hidden'],
            'office_college' => ['description' => 'Office/College', 'input_type' => 'text'],
            'department_id' => ['description' => 'Department ID', 'input_type' => 'hidden'],
            'department' => ['description' => 'Department', 'input_type' => 'text'],
            'date_acquired' => ['description' => 'Date Acquired', 'input_type' => 'date'],
            'checklist_date' => ['description' => 'Date (Checklist)', 'input_type' => 'date'],
            'pc_name' => ['description' => 'PC Name', 'input_type' => 'text'],
            'equipment_others' => ['description' => 'Others', 'input_type' => 'checkbox'],
            'equipment_others_specify' => ['description' => 'Others (Specify)', 'input_type' => 'text'],
            'os_others' => ['description' => 'OS Others', 'input_type' => 'checkbox'],
            'os_others_specify' => ['description' => 'OS Others (Specify)', 'input_type' => 'text'],
            'software_others' => ['description' => 'Software Others', 'input_type' => 'checkbox'],
            'software_others_specify' => ['description' => 'Software Others (Specify)', 'input_type' => 'text'],
            'mac_address' => ['description' => 'MAC Address', 'input_type' => 'text'],
            'ip_address' => ['description' => 'IP Address', 'input_type' => 'text'],
            self::PREVENTIVE_MAINTENANCE_PHOTO_FIELD => ['description' => 'Maintenance Photo', 'input_type' => 'file'],
        ];

        foreach ($baseFields as $fieldName => $fieldMeta) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $fieldMeta['description'],
                    'enabled' => 1,
                    'input_type' => $fieldMeta['input_type'],
                ]
            );
        }

        foreach (Equipment::where('enabled', true)->get() as $item) {
            $fieldName = $this->referenceItemFieldName('equipment', $item->name);
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $item->name,
                    'enabled' => 1,
                    'input_type' => 'checkbox',
                ]
            );
        }

        foreach ($this->operatingSystemsQuery()->get() as $item) {
            $fieldName = $this->referenceItemFieldName('os', $item->name);
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $item->name,
                    'enabled' => 1,
                    'input_type' => 'checkbox',
                ]
            );
        }

        foreach (SoftwareApplication::where('enabled', true)->get() as $item) {
            $fieldName = $this->referenceItemFieldName('software', $item->name);
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $item->name,
                    'enabled' => 1,
                    'input_type' => 'checkbox',
                ]
            );
        }

        foreach (SpecificationField::where('enabled', true)->get() as $field) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $field->name],
                [
                    'description' => $field->label,
                    'enabled' => 1,
                    'input_type' => 'text',
                ]
            );
        }

        foreach ($this->preventiveMaintenanceCustomFields('ip_phone') as $fieldName => $fieldMeta) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $fieldMeta['description'],
                    'enabled' => 1,
                    'input_type' => $fieldMeta['input_type'],
                ]
            );
        }

        foreach (array_merge(
            $this->preventiveMaintenanceCustomFields('network_device'),
            $this->preventiveMaintenanceCustomFields('wifi'),
            $this->preventiveMaintenanceCustomFields('ups'),
            $this->preventiveMaintenanceCustomFields('cctv')
        ) as $fieldName => $fieldMeta) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $fieldName],
                [
                    'description' => $fieldMeta['description'],
                    'enabled' => 1,
                    'input_type' => $fieldMeta['input_type'],
                ]
            );
        }
    }

    private function resolvePreventiveMaintenanceAssetName(array $valueMap, ?string $fallbackName = null, ?string $checklistType = null): string
    {
        $normalizedType = $this->normalizeChecklistType($checklistType ?? ($valueMap['checklist_type'] ?? null));

        if ($normalizedType === 'ip_phone') {
            $brandName = trim((string) ($valueMap['brand_name'] ?? ''));
            $modelName = trim((string) ($valueMap['model_name'] ?? ''));
            $composedName = trim($brandName . ' ' . $modelName);

            if ($composedName !== '') {
                return $composedName;
            }
        }

        if ($normalizedType === 'network_device') {
            $productName = trim((string) ($valueMap['network_device_product_name'] ?? ''));
            if ($productName !== '') {
                return $productName;
            }
        }

        if ($normalizedType === 'wifi') {
            $productName = trim((string) ($valueMap['wifi_product_name'] ?? ''));
            if ($productName !== '') {
                return $productName;
            }

            $wifiName = trim((string) ($valueMap['wifi_name'] ?? ''));
            if ($wifiName !== '') {
                return $wifiName;
            }
        }

        if ($normalizedType === 'ups') {
            $assetName = trim((string) ($valueMap['ups_brand_name'] ?? '') . ' ' . (string) ($valueMap['ups_model_name'] ?? ''));
            if ($assetName !== '') {
                return $assetName;
            }
        }

        if ($normalizedType === 'cctv') {
            $productName = trim((string) ($valueMap['cctv_product_name'] ?? ''));
            if ($productName !== '') {
                return $productName;
            }
        }

        $assetName = trim((string) ($valueMap['pc_name'] ?? $valueMap['server_name'] ?? $fallbackName ?? ''));

        return $assetName !== '' ? $assetName : 'Untitled';
    }

    private function enabledItemChecklistEntries(?string $checklistType = 'pc')
    {
        $normalizedType = $this->normalizeChecklistType($checklistType);
        $this->ensureItemChecklistEntriesForType($normalizedType);
        $modelClass = $this->itemChecklistEntryModelClass($normalizedType);
        $table = $this->itemChecklistEntryTable($normalizedType);

        $query = $modelClass::query()->where('enabled', true);

        if (Schema::hasColumn($table, 'checklist_type')) {
            $query->where('checklist_type', $normalizedType);
        }

        return $query
            ->orderBy('item_no')
            ->orderBy('sort_order')
            ->get()
            ->values();
    }

    private function resolvePreventiveMaintenanceChecklistType(?Psm $preventiveMaintenance, ?array $valueMap = null): string
    {
        if (! $preventiveMaintenance) {
            return 'pc';
        }

        $resolvedValueMap = $valueMap ?? $this->buildPsmValueMap($preventiveMaintenance);

        return $this->normalizeChecklistType($resolvedValueMap['checklist_type'] ?? null);
    }

    private function ensureItemChecklistEntriesForType(string $checklistType): void
    {
        $table = $this->itemChecklistEntryTable($checklistType);
        $modelClass = $this->itemChecklistEntryModelClass($checklistType);

        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (ItemChecklistTemplate::defaultEntriesForType($checklistType) as $entry) {
            $attributes = [
                'item_no' => $entry['item_no'],
                'task' => $entry['task'],
                'description' => $entry['description'],
            ];

            if (Schema::hasColumn($table, 'checklist_type')) {
                $attributes['checklist_type'] = $checklistType;
            }

            $modelClass::query()->firstOrCreate(
                $attributes,
                [
                    'enabled' => true,
                    'sort_order' => $entry['sort_order'] ?? 0,
                ]
            );
        }
    }

    private function itemChecklistEntryModelClass(string $checklistType): string
    {
        $normalized = $this->normalizeChecklistType($checklistType);

        if ($normalized === 'server') {
            return ServerItemChecklistItem::class;
        }

        if ($normalized === 'ip_phone') {
            return IpPhoneItemChecklistItem::class;
        }

        if ($normalized === 'network_device') {
            return NetworkDeviceItemChecklistItem::class;
        }

        return ItemChecklistItem::class;
    }

    private function itemChecklistEntryTable(string $checklistType): string
    {
        $normalized = $this->normalizeChecklistType($checklistType);

        if ($normalized === 'server') {
            return 'server_item_checklist_items';
        }

        if ($normalized === 'ip_phone') {
            return 'ip_phone_item_checklist_items';
        }

        if ($normalized === 'network_device') {
            return 'network_device_item_checklist_items';
        }

        return 'item_checklist_items';
    }

    private function ensureItemChecklistVariablesForEntries(int $templatePsmId, $entries): void
    {
        foreach ($entries as $index => $entry) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => 'item_' . $index],
                [
                    'description' => $entry->task . ': ' . $entry->description,
                    'enabled' => 1,
                    'input_type' => 'radio',
                ]
            );
        }
    }

    private function ensureItemChecklistBaseVariables(int $templatePsmId): void
    {
        foreach ([
            ['name' => 'parent_psm_id', 'description' => 'Parent PM submission', 'input_type' => 'hidden'],
            ['name' => 'maintenance_date', 'description' => 'Maintenance Date', 'input_type' => 'date'],
            ['name' => 'maintenance_month', 'description' => 'Maintenance Month', 'input_type' => 'month'],
            ['name' => 'commission_status', 'description' => 'Commission Status', 'input_type' => 'select'],
            ['name' => 'summary_recommendation', 'description' => 'Summary/Recommendation', 'input_type' => 'textarea'],
            ['name' => 'checked_by', 'description' => 'Checked by', 'input_type' => 'text'],
            ['name' => 'conforme_by', 'description' => 'Conforme', 'input_type' => 'text'],
            ['name' => 'noted_by', 'description' => 'Noted by', 'input_type' => 'text'],
        ] as $variable) {
            PsmVariable::firstOrCreate(
                ['psm_id' => $templatePsmId, 'name' => $variable['name']],
                [
                    'description' => $variable['description'],
                    'enabled' => 1,
                    'input_type' => $variable['input_type'],
                ]
            );
        }
    }

    private function groupItemChecklistEntries($entries)
    {
        return $entries->groupBy('item_no')->values()->map(function ($items) {
            $taskLabel = $items->first()['task'] ?? '';

            return [
                'item_no' => $items->first()['item_no'],
                'task' => $taskLabel,
                'descriptions' => $items->map(fn ($item) => [
                    'description' => $item['description'],
                    'sort_order' => $item['sort_order'],
                    'entry_index' => $item['entry_index'],
                    'status' => $item['status'],
                ])->values(),
            ];
        });
    }

    private function buildPsmValueMap(Psm $psm, bool $useStatusFallback = false): array
    {
        $map = [];

        foreach ($psm->values as $value) {
            if (! $value->variable) {
                continue;
            }

            $map[$value->variable->name] = $useStatusFallback
                ? ($value->value ?? $value->status)
                : $value->value;
        }

        $legacyOtherOs = null;
        foreach (['os_windows_11', 'os_windows_11_pro_64_bit'] as $legacyOsKey) {
            if (!empty($map[$legacyOsKey]) && (string) $map[$legacyOsKey] !== '0') {
                $legacyOtherOs = 'Windows 11';
                break;
            }
        }

        if ($legacyOtherOs !== null) {
            $map['os_others'] = $map['os_others'] ?? '1';
            if (empty($map['os_others_specify']) || trim((string) $map['os_others_specify']) === '') {
                $map['os_others_specify'] = $legacyOtherOs;
            }
        }

        foreach ([
            'equipment_others' => 'equipment_others_specify',
            'os_others' => 'os_others_specify',
            'software_others' => 'software_others_specify',
        ] as $checkboxField => $textField) {
            if (! empty(trim((string) ($map[$textField] ?? '')))) {
                $map[$checkboxField] = '1';
            }
        }

        return $map;
    }

    private function sanitizeTemplateValue($text): string
    {
        if ($text === null) {
            return '';
        }
        $text = (string) $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        return trim($text);
    }

    private function extractHtmlBody(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            return $matches[1];
        }

        return $html;
    }

    private function getPmTemplatePaths(?string $checklistType = null): array
    {
        $normalizedType = $this->normalizeChecklistType($checklistType);

        if ($normalizedType === 'ip_phone') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(IP Phone).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        if ($normalizedType === 'server') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Server).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        if ($normalizedType === 'network_device') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Network Device).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        if ($normalizedType === 'wifi') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(WiFi).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        if ($normalizedType === 'ups') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(UPS).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        if ($normalizedType === 'cctv') {
            return [
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(CCTV).docx'),
                storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
            ];
        }

        return [
            storage_path('template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.docx'),
        ];
    }

    private function getExistingPmTemplatePath(?string $checklistType = null): ?string
    {
        foreach ($this->getPmTemplatePaths($checklistType) as $templatePath) {
            if (file_exists($templatePath)) {
                return $templatePath;
            }
        }

        return null;
    }

    private function getExistingPmPdfTemplatePath(?string $checklistType = null): ?string
    {
        $normalizedType = $this->normalizeChecklistType($checklistType);
        $candidates = match ($normalizedType) {
            'server' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Server).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            'ip_phone' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(IP Phone).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            'network_device' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Network Device).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            'wifi' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(WiFi).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            'ups' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(UPS).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            'cctv' => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(CCTV).pdf'),
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
            default => [
                resource_path('views/template/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf'),
            ],
        };

        foreach ($candidates as $templatePath) {
            if (file_exists($templatePath)) {
                return $templatePath;
            }
        }

        return null;
    }

    private function getExportFilename(string $extension = 'pdf', ?string $checklistType = null): string
    {
        $base = match ($this->normalizeChecklistType($checklistType)) {
            'server' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Server)',
            'ip_phone' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(IP Phone)',
            'network_device' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(Network Device)',
            'wifi' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(WiFi)',
            'ups' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(UPS)',
            'cctv' => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist(CCTV)',
            default => 'CMU-F-4-DTO-002-Preventive-Maintenance-Checklist',
        };
        $ext = strtolower(ltrim($extension, '.'));

        if (! in_array($ext, ['pdf', 'docx', 'doc'], true)) {
            $ext = 'pdf';
        }

        return $base . '.' . $ext;
    }

    private function resolvePmTemplateChecklistType($checklist, array $valueMap): string
    {
        $type = $valueMap['checklist_type'] ?? null;

        if ($type === null && $checklist instanceof Psm) {
            $type = $checklist->getValueByVarName('checklist_type');
        }

        return $this->normalizeChecklistType($type);
    }

    private function drawPdfTemplateText($pdf, float $x, float $y, string $text, float $w = 0, string $align = 'L', float $fontSize = 10.5): void
    {
        $text = preg_replace('/\R+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $pdf->SetFont('times', '', $fontSize);

        if ($w > 0) {
            $fittedText = $text;
            while ($fittedText !== '' && $pdf->GetStringWidth($fittedText) > ($w - 0.5)) {
                if (function_exists('mb_substr')) {
                    $fittedText = mb_substr($fittedText, 0, mb_strlen($fittedText, 'UTF-8') - 1, 'UTF-8');
                } else {
                    $fittedText = substr($fittedText, 0, strlen($fittedText) - 1);
                }
                $fittedText = rtrim($fittedText);
            }
            $text = $fittedText;
        }

        $drawX = $x;
        if ($w > 0) {
            $textWidth = $pdf->GetStringWidth($text);
            if ($align === 'R') {
                $drawX = max($x, $x + $w - $textWidth);
            } elseif ($align === 'C') {
                $drawX = $x + max(0, ($w - $textWidth) / 2);
            }
        }

        $pdf->Text($drawX, $y, $text);
    }

    /**
     * Draw multi-line wrapped text inside a box using TCPDF MultiCell.
     * x, y = top-left of the cell (mm). w = box width (mm). maxH = max height (mm).
     */
    private function drawPdfTemplateMultilineText($pdf, float $x, float $y, string $text, float $w, float $maxH, float $fontSize = 9.0): void
    {
        $text = preg_replace('/\R+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $text) ?? $text;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return;
        }
        $pdf->SetFont('times', '', $fontSize);
        $lineH = round($fontSize * 0.4, 1); // ~line height in mm for the font size
        $pdf->SetXY($x, $y);
        // Clip output to maxH by limiting number of lines
        $maxLines = (int) floor($maxH / $lineH);
        $pdf->MultiCell($w, $lineH, $text, 0, 'L', false, 1, $x, $y, true, 0, false, true, $maxH, 'T');
    }

    private function drawPdfTemplateMark($pdf, float $cx, float $cy, bool $checked): void
    {
        if (! $checked) {
            return;
        }

        // cx, cy = center of the checkbox square; draw a centered checkmark
        $pdf->SetFont('dejavusans', 'B', 10);
        $w = 6.0;
        $h = 6.0;
        $pdf->SetXY($cx - $w / 2, $cy - $h / 2);
        $pdf->Cell($w, $h, "\u{2713}", 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }

    private function overlayPmPdfTemplatePageOne($pdf, $checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null): void
    {
        $values = $this->buildPmTemplateData($checklist, $valueMap, $icValueMap, $summaryState);

        if ($this->resolvePmTemplateChecklistType($checklist, $valueMap) === 'ip_phone') {
            $this->overlayIpPhonePdfTemplatePageOne($pdf, $values);
            return;
        }

        if ($this->resolvePmTemplateChecklistType($checklist, $valueMap) === 'network_device') {
            $this->overlayNetworkDevicePdfTemplatePageOne($pdf, $values);
            return;
        }

        if ($this->resolvePmTemplateChecklistType($checklist, $valueMap) === 'wifi') {
            $this->overlayWifiPdfTemplatePageOne($pdf, $values);
            return;
        }

        if ($this->resolvePmTemplateChecklistType($checklist, $valueMap) === 'ups') {
            $this->overlayUpsPdfTemplatePageOne($pdf, $values);
            return;
        }

        if ($this->resolvePmTemplateChecklistType($checklist, $valueMap) === 'cctv') {
            $this->overlayCctvPdfTemplatePageOne($pdf, $values);
            return;
        }

       
        $this->drawPdfTemplateText($pdf, 13.0, 60.0, $values['print_identifier'] ?? '', 90.0, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, 145, 60.0, $values['checklist_date'] ?? '', 43, 'R', 10.5);

       
        $infoX = 58.0;
        $infoW = 140.0;
        $this->drawPdfTemplateText($pdf, $infoX, 65.5, $values['user_operator'] ?? '', $infoW, 'L', 10.5);
        $this->drawPdfTemplateText($pdf, $infoX, 71.5, $values['office_college'] ?? '', $infoW, 'L', 10.5);
        $this->drawPdfTemplateText($pdf, $infoX, 77.5, $values['department'] ?? '', $infoW, 'L', 10.5);
        $this->drawPdfTemplateText($pdf, $infoX, 83.5, $values['date_acquired'] ?? '', $infoW, 'L', 10.5);
        $this->drawPdfTemplateText($pdf, $infoX, 89.5, $values['pc_name'] ?? '', $infoW, 'L', 10.5);

        // Checkbox column centers — derived from DOCX grid cell positions:
    
        $chkCol1 = 21.0;
        $chkCol2 = 55.0;
        $chkCol3 = 101.0;
        $chkCol4 = 143.5;

        // Equipment checkboxes
        
        $eqRow1Y = 113.6;
        $eqRow2Y = 120.1;
        $equipmentChecks = [
            ['chk_equipment_cpu',      $chkCol1, $eqRow1Y],
            ['chk_equipment_monitor',  $chkCol2, $eqRow1Y],
            ['chk_equipment_printer',  $chkCol3, $eqRow1Y],
            ['chk_equipment_avr',      $chkCol4, $eqRow1Y],
            ['chk_equipment_keyboard', $chkCol1, $eqRow2Y],
            ['chk_equipment_mouse',    $chkCol2, $eqRow2Y],
            ['chk_equipment_ups',      $chkCol3, $eqRow2Y],
            ['chk_equipment_others',   $chkCol4, $eqRow2Y],
        ];
        foreach ($equipmentChecks as [$key, $x, $y]) {
            $this->drawPdfTemplateMark($pdf, $x, $y, ! empty($values[$key]));
        }
        $this->drawPdfTemplateText($pdf, 177.5, 117.8, $values['equipment_others_specify'] ?? '', 27.0, 'L', 9.0);

        // OS checkboxes — cols 2-4
        
        $osY = 140.1;
        $osOtherY = 146.6;
        $osChecks = [
            ['chk_os_windows_7',  $chkCol2, $osY],
            ['chk_os_windows_8',  $chkCol3, $osY],
            ['chk_os_windows_10', $chkCol4, $osY],
            ['chk_os_others',     $chkCol2, $osOtherY],
        ];
        foreach ($osChecks as [$key, $x, $y]) {
            $this->drawPdfTemplateMark($pdf, $x, $y, ! empty($values[$key]));
        }
        $this->drawPdfTemplateText($pdf, 97.0, 144.8, $values['os_others_specify'] ?? '', 72.0, 'L', 9.0);

        // Software checkboxes
        // PDF text extraction: "Adobe Reader" / "Word Processor" / "Browser" baseline = 168.8mm (row 1)
        //                     "Anti-Virus" / "Others (Specify)" baseline = 177.9mm (row 2)
        $swRow1Y = 168.8;
        $swRow2Y = 175.9;
        $softwareChecks = [
            ['chk_software_enrollment_system', $chkCol1, $swRow1Y],
            ['chk_software_media_player',      $chkCol1, $swRow2Y],
            ['chk_software_adobe_reader',      $chkCol2, $swRow1Y],
            ['chk_software_anti_virus',        $chkCol2, $swRow2Y],
            ['chk_software_word_processor',    $chkCol3, $swRow1Y],
            ['chk_software_browser',           $chkCol4, $swRow1Y],
            ['chk_software_others',            $chkCol3, $swRow2Y],
        ];
        foreach ($softwareChecks as [$key, $x, $y]) {
            $this->drawPdfTemplateMark($pdf, $x, $y, ! empty($values[$key]));
        }
        $this->drawPdfTemplateText($pdf, 148.0, 177.5, $values['software_others_specify'] ?? '', 43.0, 'L', 9.0);

        if (($values['mac_ip'] ?? '') === '') {
            $direct = $this->sanitizeTemplateValue($valueMap['mac_ip'] ?? $valueMap['network_mac_ip'] ?? $valueMap['network_mac_and_ip'] ?? '');
            $mac = $this->sanitizeTemplateValue($valueMap['mac_address'] ?? $valueMap['network_mac'] ?? '');
            $ip = $this->sanitizeTemplateValue($valueMap['ip_address'] ?? $valueMap['network_ip'] ?? '');
            $values['mac_ip'] = $direct !== ''
                ? $direct
                : trim($mac . ($mac !== '' && $ip !== '' ? ' / ' : '') . $ip);
        }

        // Desktop specifications — DOCX Table 1 (nested in Table 0 Row 20):
        //   Label col: x=28.5→73.7mm   Value col: x=73.7→174.7mm
        // Place value text at x=75 (just inside the value column cell)
        $specOrder = [
            'processor', 'motherboard', 'memory', 'graphics_card', 'hard_disk',
            'optical_drives', 'monitor', 'casing', 'power_supply_watts', 'keyboard',
            'mouse', 'avr_watts', 'ups', 'printer', 'mac_ip',
        ];

        // Align values directly on the printed underline area.
        // Calibrated from PDF content stream (object 4) — exact label baselines:
        //   Processor=196.3, spacing ~4.6-4.7mm per row.
        $specX = 90.0;
        $specY = [
            194, 198.8, 203.5, 208.2, 212.8,
            217.4, 222.0, 226.8, 231.4, 235.9,
            240.5, 244.9, 249.8, 254.2, 258.9,
        ];

        foreach ($specOrder as $index => $key) {
            $y = $specY[$index] ?? (196.3 + ($index * 4.7));
            $this->drawPdfTemplateText($pdf, $specX, $y, $values[$key] ?? '', 108, 'L', 8.0);
        }
    }

    private function overlayPmPdfTemplatePageTwo($pdf, array $icValueMap, ?array $summaryState = null, ?string $checklistType = null): void
    {
        if ($this->normalizeChecklistType($checklistType) === 'network_device') {
            $this->overlayNetworkDeviceChecklistPdfPage($pdf, $icValueMap, $summaryState);
            return;
        }

        if ($this->normalizeChecklistType($checklistType) === 'wifi') {
            $this->overlayWifiChecklistPdfPage($pdf, $icValueMap, $summaryState);
            return;
        }

        if ($this->normalizeChecklistType($checklistType) === 'ups') {
            $this->overlayUpsChecklistPdfPage($pdf, $icValueMap, $summaryState);
            return;
        }

        if ($this->normalizeChecklistType($checklistType) === 'cctv') {
            $this->overlayCctvChecklistPdfPage($pdf, $icValueMap, $summaryState);
            return;
        }

        if ($this->normalizeChecklistType($checklistType) === 'server') {
            $this->overlayServerChecklistPdfPage($pdf, $icValueMap);
            return;
        }

        if ($this->normalizeChecklistType($checklistType) === 'ip_phone') {
            $this->overlayIpPhoneChecklistPdfPage($pdf, $icValueMap, $summaryState);
            return;
        }

        // Column X centers — empirically calibrated from PDF text positions:
        // Table has ~10mm indent; TASK text starts at x=32.7mm, DOCX col widths used to derive OK/REPAIR/N/A.
        $okX     = 170.1;
        $repairX = 179.5;
        $naX     = 188.6;

        // Row Y positions extracted directly from page 2 PDF content stream (text baselines from top).
        // item_0 (System Boot) row is 15.86mm tall; use description mid-line for best centering.
        $rowY = [
             0 => 48.6,   // System Boot (tall row — use description baseline)
             1 => 60.9,   // System Log-in
             2 => 65.4,   // Network Settings
             3 => 69.6,   // Domain Name
             4 => 73.9,   // Security Settings
             5 => 78.1,   // Client Configurations
             6 => 82.4,   // Computer Name
             7 => 86.6,   // Computer Hardware Settings
             8 => 90.9,   // BIOS up-to-date
             9 => 95.1,   // Hard Disk
            10 => 99.4,   // DVD or CD/RW-drive firmware up-to-date
            11 => 103.7,  // Memory is O.K
            12 => 107.9,  // For Laptop: battery run-time is norm
            13 => 112.2,  // Browser/Proxy Settings
            14 => 116.4,  // Proper Software loads
            15 => 124.7,  // Viruses, and malware
            16 => 129.0,  // Virus scan done
            17 => 133.2,  // Clearance
            18 => 137.5,  // Temporary files removed
            19 => 141.7,  // Recycle Bin and caches emptied
            20 => 146.0,  // Peripheral devices clean
            21 => 150.3,  // Interiors, and cleaning
            22 => 154.5,  // No loose parts
            23 => 158.8,  // Airflow is O.K.
            24 => 163.0,  // Cables unplugged and re-plugged
            25 => 167.3,  // Fans are operating
            26 => 171.5,  // Peripheral devices — Mouse
            27 => 175.8,  // Keyboard
            28 => 180.0,  // Monitor
            29 => 184.3,  // UPS
            30 => 188.6,  // Printer
            31 => 192.8,  // Telephone extension
            32 => 197.1,  // Fax
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $okX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $repairX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        // Summary/Recommendation right cell — extracted from PDF rectangles:
        //   x=92.8mm, y=197.9mm, w=100.4mm, h=24.3mm (right edge 193.2mm, bottom 222.2mm)
        // Use 1mm inner padding: start at x=93.8, y=199, width=98.4, maxH=22mm
        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 93.8, 199.0, $summaryText, 98.4, 22.0, 9.0);
        }

        // Signatures — extracted from PDF:
        //   "Checked by:" label  y=237.9mm x=30.3mm
        //   "Conforme:" label    y=237.9mm x=109.9mm
        //   "Signature over Printer Name" (checked by col) y=250.2mm x=45.8mm
        //   "Signature over Printer Name" (conforme col)   y=250.2mm x=125.4mm
        // Draw the name values just above the "Signature over Printer Name" line (~y=246mm)
        $checkedBy  = trim((string) ($icValueMap['checked_by']  ?? ''));
        $conformeBy = trim((string) ($icValueMap['conforme_by'] ?? ''));
        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 28.0, 240.0, $checkedBy, 75, 'C', 9.0);
        }
        if ($conformeBy !== '') {
            $this->drawPdfTemplateText($pdf, 104.9, 240.0, $conformeBy, 85, 'C', 9.0);
        }
    }

    private function overlayNetworkDevicePdfTemplatePageOne($pdf, array $values): void
    {
        $this->drawPdfTemplateText($pdf, 13.0, 43.0, $values['print_identifier'] ?? '', 90.0, 'L', 9.5);
        $this->drawPdfTemplateText($pdf, 225.0, 43.0, $values['checklist_date'] ?? '', 36.0, 'L', 10.0);

        $infoX = 58.0;
        $infoW = 190.0;
        $this->drawPdfTemplateText($pdf, $infoX, 49.8, $values['user_operator'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 55.6, $values['office_college'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 61.4, $values['department'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 68.5, $values['date_acquired'] ?? '', $infoW, 'L', 10.0);

        $fieldOrder = [
            'network_device_category_type',
            'network_device_product_name',
            'network_device_model_name',
            'network_device_serial',
            'network_device_mac_address',
            'network_device_office_location',
            'network_device_ip_address',
            'network_device_vlan',
        ];
        $rowY = [84.0, 97.6, 111.0, 124.5, 137.9, 151.2, 164.6, 178.0];

        foreach ($fieldOrder as $index => $field) {
            $this->drawPdfTemplateText($pdf, $infoX, $rowY[$index], $values[$field] ?? '', $infoW, 'L', 9.5);
        }
    }

    private function overlayNetworkDeviceChecklistPdfPage($pdf, array $icValueMap, ?array $summaryState = null): void
    {
        $goodX = 165.9;
        $nearMaintenanceX = 188.1;
        $naX = 218.3;

        $rowY = [
            83.1,
            88.4,
            93.7,
            99.0,
            104.3,
            110.7,
            119.6,
            124.9,
            130.2,
            137.0,
            142.3,
            147.6,
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $goodX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $nearMaintenanceX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 91.0, 50.5, $monthValue, 43.0, 'C', 10.0);
        }

        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 60.0, 155.0, $summaryText, 174.0, 22.0, 9.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));

        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 35.0, 195.5, $checkedBy, 60.0, 'C', 9.0);
        }
    }

    private function overlayWifiPdfTemplatePageOne($pdf, array $values): void
    {
        $this->drawPdfTemplateText($pdf, 13.0, 51.0, $values['print_identifier'] ?? '', 90.0, 'L', 9.5);
        $this->drawPdfTemplateText($pdf, 225.0, 51.0, $values['checklist_date'] ?? '', 36.0, 'L', 10.0);

        $infoX = 58.0;
        $infoW = 190.0;
        $this->drawPdfTemplateText($pdf, $infoX, 56.8, $values['user_operator'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 62.6, $values['office_college'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 68.4, $values['department'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 75.5, $values['date_acquired'] ?? '', $infoW, 'L', 10.0);

        $fieldOrder = [
            'wifi_category_type',
            'wifi_product_name',
            'wifi_model_name',
            'wifi_serial',
            'wifi_mac_address',
            'wifi_office_location',
            'wifi_ip_address',
            'wifi_vlan',
            'wifi_name',
            'wifi_password',
            'wifi_channel_supported',
        ];
        $rowY = [89.0, 98.1, 107.2, 116.3, 125.4, 134.5, 143.6, 152.7, 161.8, 170.9, 180];

        foreach ($fieldOrder as $index => $field) {
            $this->drawPdfTemplateText($pdf, $infoX, $rowY[$index], $values[$field] ?? '', $infoW, 'L', 9.5);
        }
    }

    private function overlayWifiChecklistPdfPage($pdf, array $icValueMap, ?array $summaryState = null): void
    {
        $goodX = 165.9;
        $nearMaintenanceX = 188.1;
        $naX = 218.3;

        $rowY = [
            83.1,
            88.4,
            95.7,
            102.0,
            108.3,
            110.7,
            119.0,
            124.9,
            130.2,
            137.0,
            142.3,
            147.6,
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $goodX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $nearMaintenanceX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 91.0, 50.5, $monthValue, 43.0, 'C', 10.0);
        }

        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 71.0, 156.8, $summaryText, 174.0, 22.0, 9.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));
        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 35.0, 195.5, $checkedBy, 60.0, 'C', 9.0);
        }
    }

    private function overlayUpsChecklistPdfPage($pdf, array $icValueMap, ?array $summaryState = null): void
    {
        $goodX = 168.2;
        $nearMaintenanceX = 189.0;
        $naX = 220.8;

        $rowY = [
            85.0,
            93.0,
            100.2,
            108.0,
            115.6,
            123.8,
            133.0,
            141.8,
            150.5,
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $goodX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $nearMaintenanceX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 91.0, 50.6, $monthValue, 42.0, 'C', 10.0);
        }

        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 72.0, 157.5, $summaryText, 162.0, 20.0, 9.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));
        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 30.0, 197.0, $checkedBy, 70.0, 'C', 9.0);
        }
    }

    private function overlayCctvChecklistPdfPage($pdf, array $icValueMap, ?array $summaryState = null): void
    {
        $goodX = 148.0;
        $nearMaintenanceX = 168.5;
        $naX = 190.7;

        $rowY = [
            71.0,
            75.5,
            85.0,
            93.5,
            99.0,
            103.0,
            110.0,
            119.5,
            126.0,

        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $goodX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $nearMaintenanceX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 91.0, 40.6, $monthValue, 42.0, 'C', 10.0);
        }

        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 117.0, 135.5, $summaryText, 78.0, 23.0, 8.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));
        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 30.0, 183.8, $checkedBy, 70.0, 'C', 9.0);
        }
    }

    private function overlayUpsPdfTemplatePageOne($pdf, array $values): void
    {
        $this->drawPdfTemplateText($pdf, 13.0, 51.0, $values['print_identifier'] ?? '', 90.0, 'L', 9.5);
        $this->drawPdfTemplateText($pdf, 225.0, 51.0, $values['checklist_date'] ?? '', 36.0, 'L', 10.0);

        $infoX = 58.0;
        $infoW = 190.0;
        $this->drawPdfTemplateText($pdf, $infoX, 56.8, $values['user_operator'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 62.6, $values['office_college'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 68.4, $values['department'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 75.5, $values['date_acquired'] ?? '', $infoW, 'L', 10.0);

        $fieldOrder = [
            'ups_category',
            'ups_brand_name',
            'ups_model_name',
            'ups_mac_address',
            'ups_serial',
            'ups_total_power_capacity',
        ];
        $rowY = [97.0, 111.0, 124.5, 138.0, 152.0, 165.5];

        foreach ($fieldOrder as $index => $field) {
            $this->drawPdfTemplateText($pdf, $infoX, $rowY[$index], $values[$field] ?? '', $infoW, 'L', 9.5);
        }
    }

    private function overlayCctvPdfTemplatePageOne($pdf, array $values): void
    {
        $this->drawPdfTemplateText($pdf, 13.0, 44.8, $values['print_identifier'] ?? '', 90.0, 'L', 9.5);
        $this->drawPdfTemplateText($pdf, 225.0, 44.8, $values['checklist_date'] ?? '', 36.0, 'L', 10.0);

        $infoX = 58.0;
        $infoW = 190.0;
        $this->drawPdfTemplateText($pdf, $infoX, 49.8, $values['user_operator'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 55.6, $values['office_college'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 61.4, $values['department'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 68.5, $values['date_acquired'] ?? '', $infoW, 'L', 10.0);

        $fieldOrder = [
            'cctv_category_type',
            'cctv_product_name',
            'cctv_model_name',
            'cctv_serial',
            'cctv_mac_address',
            'cctv_office_location',
            'cctv_ip_address',
            'cctv_vlan',
        ];
        $rowY = [85.8, 99.3, 113.2, 126.8, 140.8, 154.7, 168.0, 182.0];

        foreach ($fieldOrder as $index => $field) {
            $this->drawPdfTemplateText($pdf, $infoX, $rowY[$index], $values[$field] ?? '', $infoW, 'L', 9.5);
        }
    }

    private function overlayServerChecklistPdfPage($pdf, array $icValueMap): void
    {
        $goodX = 147;
        $nearMaintenanceX = 168;
        $naX = 190;

        $rowY = [
             0 => 88,
             1 => 92.9,
             2 => 97.8,
             3 => 102.8,
             4 => 108.2,
             5 => 113.5,
             6 => 118.4,
             7 => 123.4,
             8 => 128.9,
             9 => 135.8,
            10 => 142,
            11 => 147.8,
            12 => 152.3,
            13 => 157.8,
            14 => 162.8,
            15 => 168.8,
            16 => 174.8,
            17 => 180.8,
            18 => 186.8,
            19 => 191.8,
            20 => 197.1,
            21 => 205.5,
            22 => 212.8,
            23 => 223.8,
            24 => 229.8,
            25 => 234.1,
            26 => 239.3,
            27 => 244.9,
            28 => 250.8,
            
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $goodX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $nearMaintenanceX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 92.0, 64.3, $monthValue, 46.0, 'C', 10.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));
        $notedBy = $this->resolveItemChecklistNotedBy($icValueMap, 'server');

        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 35.0, 297.8, $checkedBy, 60.0, 'C', 9.0);
        }

    }

    private function overlayIpPhonePdfTemplatePageOne($pdf, array $values): void
    {
        $this->drawPdfTemplateText($pdf, 13.0, 44.7, $values['print_identifier'] ?? '', 90.0, 'L', 9.5);
        $this->drawPdfTemplateText($pdf, 226.0, 44.7, $values['checklist_date'] ?? '', 28.0, 'L', 10.0);

        $infoX = 57.8;
        $infoW = 190.0;
        $this->drawPdfTemplateText($pdf, $infoX, 51.7, $values['user_operator'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 57.6, $values['office_college'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 63.4, $values['department'] ?? '', $infoW, 'L', 10.0);
        $this->drawPdfTemplateText($pdf, $infoX, 70.0, $values['date_acquired'] ?? '', $infoW, 'L', 10.0);

        $fieldOrder = [
            'brand_name',
            'model_name',
            'serial_number',
            'mac_address',
            'office_located',
            'ip_address_tagged',
            'vlan',
            'telephone_number',
        ];
        $rowY = [86.1, 99.5, 112.8, 126.3, 139.7, 153.1, 166.4, 179.8];
        $fieldX = 58.8;
        $fieldW = 112.0;

        foreach ($fieldOrder as $index => $field) {
            $this->drawPdfTemplateText($pdf, $fieldX, $rowY[$index], $values[$field] ?? '', $fieldW, 'L', 9.5);
        }
    }

    private function overlayIpPhoneChecklistPdfPage($pdf, array $icValueMap, ?array $summaryState = null): void
    {
        $yesX = 147.6;
        $noX = 163.0;
        $naX = 178.0;

        $rowY = [
            67.1,
            72.4,
            77.7,
            83.0,
            88.3,
            93.6,
            98.9,
            104.2,
            109.5,
            114.8,
            120.1,
            125.4,
            130.7,
            136.0,
        ];

        foreach ($rowY as $index => $cy) {
            $status = strtolower(trim((string) ($icValueMap['item_' . $index] ?? '')));
            if ($status === 'ok') {
                $this->drawPdfTemplateMark($pdf, $yesX, $cy, true);
            } elseif ($status === 'repair') {
                $this->drawPdfTemplateMark($pdf, $noX, $cy, true);
            } elseif (in_array($status, ['na', 'n/a', '?'], true)) {
                $this->drawPdfTemplateMark($pdf, $naX, $cy, true);
            }
        }

        $maintenanceMonth = trim((string) ($icValueMap['maintenance_month'] ?? ''));
        if ($maintenanceMonth !== '') {
            $monthValue = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? date('F Y', strtotime($maintenanceMonth . '-01'))
                : $maintenanceMonth;
            $this->drawPdfTemplateText($pdf, 90.8, 41.2, $monthValue, 41.2, 'C', 10.0);
        }

        $summaryText = ($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '';
        if ($summaryText !== '') {
            $this->drawPdfTemplateMultilineText($pdf, 115.5, 140.5, $summaryText, 65.0, 24.0, 9.0);
        }

        $checkedBy = trim((string) ($icValueMap['checked_by'] ?? ''));
        $notedBy = $this->resolveItemChecklistNotedBy($icValueMap, 'ip_phone');

        if ($checkedBy !== '') {
            $this->drawPdfTemplateText($pdf, 40.0, 184, $checkedBy, 48.0, 'C', 9.0);
        }

        // if ($notedBy !== '') {
        //     $this->drawPdfTemplateText($pdf, 122.0, 203.8, $notedBy, 48.0, 'C', 9.0);
        // }
    }

    private function generatePdfFromPdfTemplate($id, $checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null, ?string $filename = null, bool $inline = false)
    {
        if (! class_exists(Fpdi::class)) {
            return null;
        }

        $checklistType = $this->resolvePmTemplateChecklistType($checklist, $valueMap);

        $cachePayload = [
            'source' => 'pdf_template_overlay',
            'version' => 13,
            'id' => $id,
            'checklist_id' => $checklist->psm_id ?? null,
            'checklist_type' => $checklistType,
            'value_map' => $valueMap,
            'ic_value_map' => $icValueMap,
            'summary_state' => $summaryState,
        ];
        $cacheKey = 'pm_pdf:' . md5(serialize($cachePayload));
        $useCache = ! $inline;
        $cachedContent = $useCache ? Cache::get($cacheKey) : null;
        if ($useCache && is_string($cachedContent) && $cachedContent !== '') {
            $downloadName = $filename ?: $this->getExportFilename('pdf', $checklistType);
            return response($cachedContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($cachedContent))
                ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
        }

        $templatePath = $this->getExistingPmPdfTemplatePath($checklistType);
        if (! $templatePath) {
            return null;
        }

        try {
            $pdf = new Fpdi('P', 'mm', 'LEGAL', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetMargins(0, 0, 0, true);

            $pageCount = $pdf->setSourceFile($templatePath);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNo === 1) {
                    $this->overlayPmPdfTemplatePageOne($pdf, $checklist, $valueMap, $icValueMap, $summaryState);
                } elseif ($pageNo === 2 && is_array($icValueMap)) {
                    $this->overlayPmPdfTemplatePageTwo($pdf, $icValueMap, $summaryState, $checklistType);
                }
            }

            $content = $pdf->Output('', 'S');
            if (! is_string($content) || $content === '') {
                return null;
            }

            if ($useCache) {
                Cache::put($cacheKey, $content, now()->addSeconds(self::VIEW_PDF_CACHE_SECONDS));
            }

            $downloadName = $filename ?: $this->getExportFilename('pdf', $checklistType);
            return response($content, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeDocxCellText(string $cellXml): string
    {
        $text = preg_replace('/<w:tab\b[^>]*\/>/i', ' ', $cellXml) ?? $cellXml;
        $text = preg_replace('/<w:br\b[^>]*\/>/i', ' ', $text) ?? $text;
        $text = preg_replace('/<[^>]+>/', '', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normalizeDocxLabelKey(string $text): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($text, 'UTF-8')
            : strtolower($text);

        return preg_replace('/[^a-z0-9]+/i', '', $normalized) ?? $normalized;
    }

    private function injectPlaceholderIntoCellByIndex(string $rowXml, int $targetIndex, string $placeholder): string
    {
        if (str_contains($rowXml, '${' . $placeholder . '}')) {
            return $rowXml;
        }

        if (! preg_match_all('/<w:tc\\b[^>]*>.*?<\\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
            return $rowXml;
        }

        if (count($cellMatches[0]) === 0) {
            return $rowXml;
        }

        if ($targetIndex < 0 || $targetIndex >= count($cellMatches[0])) {
            return $rowXml;
        }

        $targetCell = $cellMatches[0][$targetIndex][0];
        $targetCellOffset = $cellMatches[0][$targetIndex][1];

        $updatedCell = preg_replace(
            '/(<w:p\\b[^>]*>)(.*?)(<\\/w:p>)/s',
            '$1$2<w:r><w:t>${' . $placeholder . '}</w:t></w:r>$3',
            $targetCell,
            1
        );

        if ($updatedCell === null || $updatedCell === $targetCell) {
            return $rowXml;
        }

        $updatedCell = preg_replace('/<w:textDirection\\b[^>]*\\/>/s', '', $updatedCell) ?? $updatedCell;


        return substr($rowXml, 0, $targetCellOffset)
            . $updatedCell
            . substr($rowXml, $targetCellOffset + strlen($targetCell));
    }

    private function injectPlaceholderIntoSecondCell(string $rowXml, string $placeholder): string
    {
        if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
            return $rowXml;
        }

        $lastCellIndex = count($cellMatches[0]) - 1;
        return $this->injectPlaceholderIntoCellByIndex($rowXml, $lastCellIndex, $placeholder);
    }

    private function injectCheckboxPlaceholderBeforeLabel(string $rowXml, string $label, string $placeholder): string
    {
        if (str_contains($rowXml, '${' . $placeholder . '}')) {
            return $rowXml;
        }

        if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
            return $rowXml;
        }

        $cells = $cellMatches[0];
        $targetLabelKey = $this->normalizeDocxLabelKey($label);
        $labelCellIndex = null;
        foreach ($cells as $index => $cellData) {
            $cellText = $this->normalizeDocxCellText($cellData[0]);
            $cellLabelKey = $this->normalizeDocxLabelKey($cellText);
            if ($cellLabelKey === $targetLabelKey || str_contains($cellLabelKey, $targetLabelKey)) {
                $labelCellIndex = $index;
                break;
            }
        }

        if ($labelCellIndex === null) {
            return $rowXml;
        }

        $targetIndex = max(0, $labelCellIndex - 1);
        return $this->injectPlaceholderIntoCellByIndex($rowXml, $targetIndex, $placeholder);
    }

    private function injectPmValuePlaceholderByExactLabel(string $rowXml, array $rowPlaceholders): string
    {
        if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
            return $rowXml;
        }

        $cells = $cellMatches[0];
        $cellCount = count($cells);
        if ($cellCount < 1 || $cellCount > 3) {
            return $rowXml;
        }

        foreach ($cells as $index => $cellData) {
            $cellText = $this->normalizeDocxCellText($cellData[0]);
            $cellKey = $this->normalizeDocxLabelKey($cellText);
            $placeholder = $rowPlaceholders[$cellKey] ?? null;
            if (! is_string($placeholder)) {
                continue;
            }

            if ($cellCount === 1) {
                $updatedCellXml = $this->injectSequentialPlaceholdersOntoUnderlineRuns($cellData[0], [$placeholder]);
                if ($updatedCellXml === $cellData[0]) {
                    continue;
                }

                return substr($rowXml, 0, $cellData[1])
                    . $updatedCellXml
                    . substr($rowXml, $cellData[1] + strlen($cellData[0]));
            }

            // Value text in the official PM template is placed in the rightmost
            // cell when rows are split into 3 cells (label | spacer | value).
            // Using the immediate next cell causes shifted/overlapping data.
            $targetIndex = $cellCount - 1;
            if ($targetIndex === $index) {
                continue;
            }

            return $this->injectPlaceholderIntoCellByIndex($rowXml, $targetIndex, $placeholder);
        }

        return $rowXml;
    }
    private function injectSequentialPlaceholdersOntoUnderlineRuns(string $xmlSegment, array $placeholders, bool $underlineValues = false): string
    {
        $index = 0;

        // Step 1: Replace each underline run with value + underline tab (run-level only)
        $xmlSegment = preg_replace_callback('/(<w:r\b[^>]*>)((?:<w:rPr>.*?<\/w:rPr>)?)<w:t>_{3,}<\/w:t>(<\/w:r>)/s', function ($matches) use ($placeholders, $underlineValues, &$index) {
            if (! isset($placeholders[$index])) {
                return $matches[0];
            }

            $placeholder = $placeholders[$index++];
            $rOpen = $matches[1];
            $rClose = $matches[3];

            // Value text
            $valueUnderline = $underlineValues ? '<w:u w:val="single"/>' : '';
            $valueRun = $rOpen
                . '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>' . $valueUnderline . '</w:rPr>'
                . '<w:t xml:space="preserve">${' . $placeholder . '}</w:t>'
                . $rClose;

            // Underline tab to fill remaining width
            $tabRun = $rOpen
                . '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:u w:val="single"/></w:rPr>'
                . '<w:tab/>'
                . $rClose;

            return $valueRun . $tabRun;
        }, $xmlSegment) ?? $xmlSegment;

        // Step 2: Add right tab stop to paragraphs that now contain a placeholder
        $xmlSegment = preg_replace_callback('/(<w:p\b[^>]*>)((?:<w:pPr>.*?<\/w:pPr>)?)(.*?<\/w:p>)/s', function ($m) {
            if (! str_contains($m[0], '<w:tab/>') || ! str_contains($m[0], '${')) {
                return $m[0];
            }

            $tabDef = '<w:tabs><w:tab w:val="right" w:pos="11000"/></w:tabs>';
            $pOpen = $m[1];
            $ppr = $m[2];
            $rest = $m[3];

            if ($ppr !== '' && ! str_contains($ppr, '<w:tabs>')) {
                $ppr = str_replace('</w:pPr>', $tabDef . '</w:pPr>', $ppr);
            } elseif ($ppr === '') {
                $ppr = '<w:pPr>' . $tabDef . '</w:pPr>';
            }

            return $pOpen . $ppr . $rest;
        }, $xmlSegment) ?? $xmlSegment;

        return $xmlSegment;
    }

    private function injectInlinePlaceholderAfterText(string $xml, string $label, string $placeholder): string
    {
        if (str_contains($xml, '${' . $placeholder . '}')) {
            return $xml;
        }

        return preg_replace_callback('/<w:t\b([^>]*)>' . preg_quote($label, '/') . '\s*<\/w:t>/', function ($matches) use ($label, $placeholder) {
            $attributes = $matches[1];
            if (! str_contains($attributes, 'xml:space=')) {
                $attributes .= ' xml:space="preserve"';
            }

            return '<w:t' . $attributes . '>' . $label . ' ${' . $placeholder . '}</w:t>';
        }, $xml, 1) ?? $xml;
    }

    private function injectDevicePageOneInlinePlaceholders(string $xml): string
    {
        foreach ([
            'Category Type' => 'processor',
            'Product Name' => 'motherboard',
            'Model Name' => 'memory',
            'odel Name' => 'memory',
            'Serial' => 'graphics_card',
            'Serial Number' => 'graphics_card',
            'Mac Address' => 'hard_disk',
            'Office Located' => 'optical_drives',
            'Office Location' => 'optical_drives',
            'IP Address' => 'monitor',
            'VLAN' => 'casing',
        ] as $label => $placeholder) {
            $xml = $this->injectInlinePlaceholderAfterText($xml, $label, $placeholder);
        }

        return $xml;
    }

    private function injectNetworkDevicePageOneAbsolutePlaceholders(string $xml): string
    {
        if (str_contains($xml, 'network_device_page_one_values')) {
            return $xml;
        }

        $fields = [
            ['processor', 168, 236],
            ['motherboard', 168, 274],
            ['memory', 168, 312],
            ['graphics_card', 168, 350],
            ['hard_disk', 168, 388],
            ['optical_drives', 168, 426],
            ['monitor', 168, 464],
            ['casing', 168, 502],
        ];

        $paragraphs = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:bookmarkStart w:id="991" w:name="network_device_page_one_values"/><w:bookmarkEnd w:id="991"/></w:p>';
        foreach ($fields as [$placeholder, $leftPt, $topPt]) {
            $paragraphs .= $this->absoluteTextBoxPlaceholderXml($placeholder, (float) $leftPt, (float) $topPt, 430.0, 14.0);
        }

        return preg_replace('/(<w:body\b[^>]*>)/', '$1' . $paragraphs, $xml, 1) ?? $xml;
    }

    private function injectWifiPageOneAbsolutePlaceholders(string $xml): string
    {
        if (str_contains($xml, 'wifi_page_one_values')) {
            return $xml;
        }

        $fields = [
            ['wifi_category_type', 168, 254],
            ['wifi_product_name', 168, 280],
            ['wifi_model_name', 168, 306],
            ['wifi_serial', 168, 332],
            ['wifi_mac_address', 168, 358],
            ['wifi_office_location', 168, 384],
            ['wifi_ip_address', 168, 410],
            ['wifi_vlan', 168, 436],
            ['wifi_name', 168, 462],
            ['wifi_password', 168, 488],
            ['wifi_channel_supported', 168, 514],
        ];

        $paragraphs = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:bookmarkStart w:id="992" w:name="wifi_page_one_values"/><w:bookmarkEnd w:id="992"/></w:p>';
        foreach ($fields as [$placeholder, $leftPt, $topPt]) {
            $paragraphs .= $this->absoluteTextBoxPlaceholderXml($placeholder, (float) $leftPt, (float) $topPt, 430.0, 14.0);
        }

        return preg_replace('/(<w:body\b[^>]*>)/', '$1' . $paragraphs, $xml, 1) ?? $xml;
    }

    private function injectUpsPageOneAbsolutePlaceholders(string $xml): string
    {
        if (str_contains($xml, 'ups_page_one_values')) {
            return $xml;
        }

        $fields = [
            ['ups_category', 168, 304],
            ['ups_brand_name', 168, 342],
            ['ups_model_name', 168, 380],
            ['ups_mac_address', 168, 418],
            ['ups_serial', 168, 456],
            ['ups_total_power_capacity', 168, 494],
        ];

        $paragraphs = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:bookmarkStart w:id="993" w:name="ups_page_one_values"/><w:bookmarkEnd w:id="993"/></w:p>';
        foreach ($fields as [$placeholder, $leftPt, $topPt]) {
            $paragraphs .= $this->absoluteTextBoxPlaceholderXml($placeholder, (float) $leftPt, (float) $topPt, 430.0, 14.0);
        }

        return preg_replace('/(<w:body\b[^>]*>)/', '$1' . $paragraphs, $xml, 1) ?? $xml;
    }

    private function injectCctvPageOneAbsolutePlaceholders(string $xml): string
    {
        if (str_contains($xml, 'cctv_page_one_values')) {
            return $xml;
        }

        $fields = [
            ['cctv_category_type', 168, 242],
            ['cctv_product_name', 168, 280],
            ['cctv_model_name', 168, 318],
            ['cctv_serial', 168, 356],
            ['cctv_mac_address', 168, 394],
            ['cctv_office_location', 168, 432],
            ['cctv_ip_address', 168, 470],
            ['cctv_vlan', 168, 508],
        ];

        $paragraphs = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:bookmarkStart w:id="994" w:name="cctv_page_one_values"/><w:bookmarkEnd w:id="994"/></w:p>';
        foreach ($fields as [$placeholder, $leftPt, $topPt]) {
            $paragraphs .= $this->absoluteTextBoxPlaceholderXml($placeholder, (float) $leftPt, (float) $topPt, 430.0, 14.0);
        }

        return preg_replace('/(<w:body\b[^>]*>)/', '$1' . $paragraphs, $xml, 1) ?? $xml;
    }

    private function absoluteTextBoxPlaceholderXml(string $placeholder, float $leftPt, float $topPt, float $widthPt, float $heightPt): string
    {
        $id = 'pm_' . preg_replace('/[^a-z0-9_]+/i', '_', $placeholder);

        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="1"/></w:rPr><w:pict>'
            . '<v:shape id="' . $id . '" type="#_x0000_t202" stroked="f" filled="f" '
            . 'style="position:absolute;z-index:251659264;margin-left:' . $leftPt . 'pt;margin-top:' . $topPt . 'pt;width:' . $widthPt . 'pt;height:' . $heightPt . 'pt;mso-position-horizontal-relative:page;mso-position-vertical-relative:page">'
            . '<v:textbox inset="0,0,0,0"><w:txbxContent><w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/></w:rPr></w:pPr>'
            . '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/></w:rPr><w:t>${' . $placeholder . '}</w:t></w:r>'
            . '</w:p></w:txbxContent></v:textbox></v:shape>'
            . '</w:pict></w:r></w:p>';
    }

    private function injectIpPhonePageOnePlaceholders(string $xml): string
    {
        $casingMarker = '<w:t>Casing</w:t>';
        $casingIndex = strpos($xml, $casingMarker);
        if ($casingIndex === false) {
            return $xml;
        }

        $rightCellStart = strpos($xml, '<w:tc><w:tcPr><w:tcW w:w="11250"', $casingIndex);
        if ($rightCellStart === false) {
            return $xml;
        }

        $rightCellEnd = strpos($xml, '</w:tc>', $rightCellStart);
        if ($rightCellEnd === false) {
            return $xml;
        }

        $rightCellEnd += strlen('</w:tc>');
        $rightCellXml = substr($xml, $rightCellStart, $rightCellEnd - $rightCellStart);
        $updatedRightCellXml = $this->injectSequentialPlaceholdersOntoUnderlineRuns($rightCellXml, [
            'processor',
            'motherboard',
            'memory',
            'graphics_card',
            'hard_disk',
            'optical_drives',
            'monitor',
            'casing',
        ], true);

        if ($updatedRightCellXml === $rightCellXml) {
            return $xml;
        }

        return substr($xml, 0, $rightCellStart)
            . $updatedRightCellXml
            . substr($xml, $rightCellEnd);
    }

    private function injectIpPhonePageTwoPlaceholders(string $xml): string
    {
        $xml = preg_replace(
            '/(<w:r><w:t xml:space="preserve">For the Month of <\/w:t><\/w:r>)(<w:r><w:rPr><w:rFonts w:ascii="Times New Roman"\/><w:b w:val="0"\/><w:u w:val="single"\/><\/w:rPr><w:tab\/><\/w:r>)/',
            '$1<w:r><w:rPr><w:rFonts w:ascii="Times New Roman"/><w:b w:val="0"/><w:u w:val="single"/></w:rPr><w:t>${maintenance_month}</w:t></w:r>$2',
            $xml,
            1
        ) ?? $xml;

        $xml = preg_replace(
            '/<w:p\b[^>]*><w:pPr><w:ind w:left="7027"\/><w:rPr><w:sz w:val="21"\/><\/w:rPr><\/w:pPr>.*?<w:t>Jeremy<\/w:t>.*?<w:t>Capili<\/w:t><\/w:p>/s',
            '<w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="7490"/></w:tabs><w:ind w:left="2272"/><w:rPr><w:sz w:val="21"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>${checked_by}</w:t></w:r><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:tab/></w:r><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>${noted_by}</w:t></w:r></w:p>',
            $xml,
            1
        ) ?? $xml;

        if (! str_contains($xml, '${checked_by}')) {
            $xml = preg_replace(
                '/<w:p\b[^>]*><w:pPr><w:ind w:left="7027"\/><w:rPr><w:sz w:val="21"\/><\/w:rPr><\/w:pPr>.*?<w:t>Carlo Martin A\. Sarausa<\/w:t><\/w:r><\/w:p>/s',
                '<w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="7490"/></w:tabs><w:ind w:left="2272"/><w:rPr><w:sz w:val="21"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>${checked_by}</w:t></w:r><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:tab/></w:r><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>${noted_by}</w:t></w:r></w:p>',
                $xml,
                1
            ) ?? $xml;
        }

        $xml = preg_replace(
            '/<w:p\b[^>]*><w:pPr><w:tabs><w:tab w:val="left" w:pos="7490"\/><\/w:tabs><w:spacing w:before="59"\/><w:ind w:left="2272"\/><w:rPr><w:sz w:val="21"\/><\/w:rPr><\/w:pPr>.*?<w:t>Technical<\/w:t>.*?<w:t>Staff<\/w:t>.*?<w:t>DTO<\/w:t>.*?<w:t>Chief<\/w:t><\/w:p>/s',
            '<w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="7490"/></w:tabs><w:spacing w:before="59"/><w:ind w:left="2272"/><w:rPr><w:sz w:val="21"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:t>${checked_by_title}</w:t></w:r><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:tab/></w:r><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:t>${noted_by_title}</w:t></w:r></w:p>',
            $xml,
            1
        ) ?? $xml;

        return $xml;
    }

    private function injectNetworkDeviceSignaturePlaceholders(string $xml): string
    {
        $xml = preg_replace(
            '/<w:p\b[^>]*><w:pPr><w:ind w:left="7027"\/>.*?Carlo Martin A\..*?Sarausa.*?<\/w:p>/s',
            '<w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="7490"/></w:tabs><w:ind w:left="2272"/><w:rPr><w:sz w:val="21"/></w:rPr></w:pPr><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>${checked_by}</w:t></w:r><w:r><w:rPr><w:sz w:val="21"/></w:rPr><w:tab/></w:r><w:r><w:rPr><w:sz w:val="21"/><w:u w:val="single"/></w:rPr><w:t>Carlo Martin A. Sarausa</w:t></w:r></w:p>',
            $xml,
            1
        ) ?? $xml;

        return $xml;
    }

    private function normalizeWifiChecklistTemplateText(string $xml): string
    {
        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($match) {
            $rowXml = $match[0];
            $rowKey = $this->normalizeDocxLabelKey($this->normalizeDocxCellText($rowXml));

            if (str_contains($rowKey, 'connectivityandsettings')) {
                $updatedRowXml = preg_replace_callback('/<w:t([^>]*)>([^<]*?)Settings<\/w:t>/', function ($textMatch) {
                    return '<w:t' . $textMatch[1] . '>' . str_replace('Settings', 'Coverage', $textMatch[2]) . '</w:t>';
                }, $rowXml, 1) ?? $rowXml;

                if (! str_contains($updatedRowXml, 'Coverage')) {
                    $updatedRowXml = preg_replace(
                        '/<w:t([^>]*)>(\s*and\s*)<\/w:t>/',
                        '<w:t$1>$2Coverage</w:t>',
                        $updatedRowXml,
                        1
                    ) ?? $updatedRowXml;
                }

                return $updatedRowXml;
            }

            if (! str_contains($rowKey, 'securityandfirmware')) {
                return $rowXml;
            }

            if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
                return $rowXml;
            }

            $firstCell = $cellMatches[0][0][0] ?? '';
            $firstOffset = $cellMatches[0][0][1] ?? null;
            if (! is_int($firstOffset)) {
                return $rowXml;
            }

            $updatedFirstCell = preg_replace('/<w:t>5<\/w:t>/', '<w:t>4</w:t>', $firstCell, 1) ?? $firstCell;
            if ($updatedFirstCell === $firstCell) {
                return $rowXml;
            }

            return substr($rowXml, 0, $firstOffset)
                . $updatedFirstCell
                . substr($rowXml, $firstOffset + strlen($firstCell));
        }, $xml) ?? $xml;

        return $xml;
    }

    private function injectNetworkDeviceMonthPlaceholder(string $xml): string
    {
        return preg_replace(
            '/<w:p\b(?:(?!<\/w:p>).)*<w:t xml:space="preserve">For the Month of <\/w:t>(?:(?!<\/w:p>).)*<w:tab\/>(?:(?!<\/w:p>).)*<\/w:p>/s',
            '<w:p><w:pPr><w:pStyle w:val="BodyText"/><w:spacing w:before="44" w:after="180"/><w:jc w:val="center"/><w:rPr><w:rFonts w:ascii="Times New Roman"/><w:b w:val="0"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">For the Month of </w:t></w:r><w:r><w:rPr><w:rFonts w:ascii="Times New Roman"/><w:b w:val="0"/><w:u w:val="single"/></w:rPr><w:t>${maintenance_month}</w:t></w:r></w:p>',
            $xml,
            1
        ) ?? $xml;
    }

    private function injectNetworkDeviceSummaryPlaceholder(string $rowXml): string
    {
        if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
            return $rowXml;
        }

        if (count($cellMatches[0]) < 2) {
            return $rowXml;
        }

        $targetCell = $cellMatches[0][1][0];
        $targetOffset = $cellMatches[0][1][1];
        $paragraph = '<w:p><w:pPr><w:pStyle w:val="BodyText"/><w:jc w:val="left"/><w:rPr><w:rFonts w:ascii="Times New Roman"/><w:sz w:val="21"/></w:rPr></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman"/><w:sz w:val="21"/></w:rPr><w:t>${summary_recommendation}</w:t></w:r></w:p>';

        $updatedCell = preg_replace('/(<w:tcPr\b[^>]*>.*?<\/w:tcPr>).*<\/w:tc>/s', '$1' . $paragraph . '</w:tc>', $targetCell, 1);
        if (! is_string($updatedCell) || $updatedCell === $targetCell) {
            return $this->ensureDocxRowMinimumHeight(
                $this->injectPlaceholderIntoSecondCell($rowXml, 'summary_recommendation'),
                1000
            );
        }

        return $this->ensureDocxRowMinimumHeight(substr($rowXml, 0, $targetOffset)
            . $updatedCell
            . substr($rowXml, $targetOffset + strlen($targetCell)), 1000);
    }

    private function ensureDocxRowMinimumHeight(string $rowXml, int $heightTwips): string
    {
        if (preg_match('/<w:trHeight\b[^>]*w:val="(\d+)"[^>]*\/>/', $rowXml, $matches)) {
            $currentHeight = (int) $matches[1];
            if ($currentHeight >= $heightTwips) {
                return $rowXml;
            }

            return preg_replace(
                '/<w:trHeight\b[^>]*\/>/',
                '<w:trHeight w:val="' . $heightTwips . '"/>',
                $rowXml,
                1
            ) ?? $rowXml;
        }

        if (str_contains($rowXml, '<w:trPr>')) {
            return preg_replace(
                '/<w:trPr>/',
                '<w:trPr><w:trHeight w:val="' . $heightTwips . '"/>',
                $rowXml,
                1
            ) ?? $rowXml;
        }

        return preg_replace(
            '/<w:tr\b([^>]*)>/',
            '<w:tr$1><w:trPr><w:trHeight w:val="' . $heightTwips . '"/></w:trPr>',
            $rowXml,
            1
        ) ?? $rowXml;
    }

    private function buildRuntimePmPlaceholderTemplate(?string $checklistType = null): ?string
    {
        $sourceTemplate = null;
        foreach ($this->getPmTemplatePaths($checklistType ?? null) as $candidate) {
            if (file_exists($candidate)) {
                $sourceTemplate = $candidate;
                break;
            }
        }

        if (! $sourceTemplate) {
            return null;
        }

        $runtimeTemplate = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_template_runtime_' . uniqid() . '.docx';
        if (! @copy($sourceTemplate, $runtimeTemplate)) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($runtimeTemplate) !== true) {
            @unlink($runtimeTemplate);
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml) || $xml === '') {
            $zip->close();
            @unlink($runtimeTemplate);
            return null;
        }

        $xml = str_replace('Date: _____________', 'Date: ${checklist_date}', $xml);
        $xml = $this->injectPmIdentifierPageOnePlaceholder($xml, $checklistType);
        $xml = preg_replace('/For the Month of\s*_+/i', 'For the Month of ${maintenance_month}', $xml) ?? $xml;
        $xml = preg_replace(
            '/(<w:r><w:t>For the Month of<\/w:t><\/w:r>)(<w:r><w:rPr><w:spacing w:val="2"\/><\/w:rPr><w:t xml:space="preserve"> <\/w:t><\/w:r><w:r><w:rPr><w:rFonts w:ascii="Times New Roman"\/><w:b w:val="0"\/><w:u w:val="thick"\/><\/w:rPr><w:tab\/><\/w:r>)/',
            '$1<w:r><w:t xml:space="preserve"> ${maintenance_month}</w:t></w:r>$2',
            $xml,
            1
        ) ?? $xml;

        if ($this->normalizeChecklistType($checklistType) === 'ip_phone') {
            $xml = $this->injectIpPhonePageOnePlaceholders($xml);
            $xml = $this->injectIpPhonePageTwoPlaceholders($xml);
        }

        $normalizedChecklistType = $this->normalizeChecklistType($checklistType);

        if ($normalizedChecklistType === 'cctv') {
            $xml = $this->injectCctvPageOneAbsolutePlaceholders($xml);
            $xml = $this->injectNetworkDeviceMonthPlaceholder($xml);
            $xml = $this->injectNetworkDeviceSignaturePlaceholders($xml);
        }

        if ($normalizedChecklistType === 'network_device') {
            $xml = $this->injectNetworkDevicePageOneAbsolutePlaceholders($xml);
            $xml = $this->injectNetworkDeviceMonthPlaceholder($xml);
            $xml = $this->injectNetworkDeviceSignaturePlaceholders($xml);
        }

        if ($normalizedChecklistType === 'wifi') {
            $xml = $this->injectWifiPageOneAbsolutePlaceholders($xml);
            $xml = $this->injectNetworkDeviceMonthPlaceholder($xml);
            $xml = $this->injectNetworkDeviceSignaturePlaceholders($xml);
            $xml = $this->normalizeWifiChecklistTemplateText($xml);
        }

        if ($normalizedChecklistType === 'ups') {
            $xml = $this->injectUpsPageOneAbsolutePlaceholders($xml);
            $xml = $this->injectNetworkDeviceMonthPlaceholder($xml);
            $xml = $this->injectNetworkDeviceSignaturePlaceholders($xml);
        }

        $rowPlaceholders = [
            'useroperator' => 'user_operator',
            'officecollegeunit' => 'office_college',
            'officecollege' => 'office_college',
            'department' => 'department',
            'dateacquired' => 'date_acquired',
            'pcname' => 'pc_name',
            'brandname' => 'brand_name',
            'modelname' => 'model_name',
            'serialnumber' => 'serial_number',
            'locationofficelocated' => 'office_located',
            'officelocated' => 'office_located',
            'location' => 'office_located',
            'macaddress' => 'mac_address',
            'ipaddresstagged' => 'ip_address_tagged',
            'vlan' => 'vlan',
            'telephonenumber' => 'telephone_number',
            'categorytype' => 'processor',
            'productname' => 'motherboard',
            'modelname' => 'memory',
            'serial' => 'graphics_card',
            'processor' => 'processor',
            'motherboard' => 'motherboard',
            'memory' => 'memory',
            'graphicscard' => 'graphics_card',
            'harddisk' => 'hard_disk',
            'opticaldrive' => 'optical_drives',
            'monitor' => 'monitor',
            'casing' => 'casing',
            'powersupplywatts' => 'power_supply_watts',
            'keyboard' => 'keyboard',
            'mouse' => 'mouse',
            'avrwatts' => 'avr_watts',
            'ups' => 'ups',
            'printer' => 'printer',
            'networkmacip' => 'mac_ip',
            'ipaddress' => 'ip_address',
        ];

        $currentPmSection = null;

        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($match) use ($rowPlaceholders, &$currentPmSection, $normalizedChecklistType) {
            if (in_array($normalizedChecklistType, ['network_device', 'wifi', 'ups', 'cctv'], true)) {
                $rowKey = $this->normalizeDocxLabelKey($this->normalizeDocxCellText($match[0]));
                foreach ([
                    'category',
                    'categorytype',
                    'brandname',
                    'productname',
                    'modelname',
                    'odelname',
                    'serialnumber',
                    'serial',
                    'macaddress',
                    'officelocated',
                    'officelocation',
                    'ipaddress',
                    'vlan',
                    'wifiname',
                    'wifipassword',
                    'channelsupported',
                    'totalpowerorcapacity',
                ] as $deviceLabelKey) {
                    if (str_contains($rowKey, $deviceLabelKey)) {
                        return $match[0];
                    }
                }
            }

            $rowXml = $this->injectPmValuePlaceholderByExactLabel($match[0], $rowPlaceholders);
            if ($rowXml !== $match[0]) {
                return $rowXml;
            }

            $normalizedRowText = $this->normalizeDocxLabelKey($this->normalizeDocxCellText($rowXml));
            if (str_contains($normalizedRowText, 'equipmentinstalled')) {
                $currentPmSection = 'equipment';
                return $rowXml;
            }

            if (str_contains($normalizedRowText, 'operatingsysteminstalled')) {
                $currentPmSection = 'os';
                return $rowXml;
            }

            if (str_contains($normalizedRowText, 'softwareapplicationsinstalled')) {
                $currentPmSection = 'software';
                return $rowXml;
            }

            if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
                return $rowXml;
            }

            foreach ($cellMatches[0] as $cellData) {
                $cellText = $this->normalizeDocxCellText($cellData[0]);
                $cellKey = $this->normalizeDocxLabelKey($cellText);

                if (! str_contains($cellKey, 'othersspecify') && ! str_contains($cellKey, 'otherspecify')) {
                    continue;
                }

                $placeholder = match ($currentPmSection) {
                    'equipment' => 'equipment_others_specify',
                    'os' => 'os_others_specify',
                    'software' => 'software_others_specify',
                    default => null,
                };

                if (! is_string($placeholder)) {
                    return $rowXml;
                }

                $updatedCellXml = $this->injectSequentialPlaceholdersOntoUnderlineRuns($cellData[0], [$placeholder], true);
                if ($updatedCellXml !== $cellData[0]) {
                    return substr($rowXml, 0, $cellData[1])
                        . $updatedCellXml
                        . substr($rowXml, $cellData[1] + strlen($cellData[0]));
                }

                return $this->injectPlaceholderIntoSecondCell($rowXml, $placeholder);
            }

            return $rowXml;
        }, $xml);

        $itemRowIndex = 0;
        $inItemChecklistTable = false;
        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($match) use (&$itemRowIndex, &$inItemChecklistTable, $normalizedChecklistType) {
            $rowXml = $match[0];

            preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cells);
            $cellTexts = array_map(function ($cellXml) {
                return $this->normalizeDocxCellText($cellXml);
            }, $cells[0] ?? []);
            $cellKeys = array_map(function ($text) {
                return $this->normalizeDocxLabelKey($text);
            }, $cellTexts);

            $isPcHeader = in_array('item', $cellKeys, true)
                && in_array('description', $cellKeys, true);
            $isServerHeader = $normalizedChecklistType === 'server'
                && in_array('item', $cellKeys, true)
                && in_array('maintenance', $cellKeys, true)
                && in_array('specification', $cellKeys, true)
                && in_array('status', $cellKeys, true);
            $isIpPhoneHeader = $normalizedChecklistType === 'ip_phone'
                && in_array('item', $cellKeys, true)
                && in_array('maintenance', $cellKeys, true)
                && in_array('specification', $cellKeys, true)
                && in_array('status', $cellKeys, true);
            $isDeviceHeader = in_array($normalizedChecklistType, ['network_device', 'wifi', 'ups', 'cctv'], true)
                && in_array('item', $cellKeys, true)
                && in_array('maintenance', $cellKeys, true)
                && in_array('specification', $cellKeys, true)
                && in_array('status', $cellKeys, true);

            if ($isPcHeader || $isServerHeader || $isIpPhoneHeader || $isDeviceHeader) {
                $inItemChecklistTable = true;
                return $rowXml;
            }

            if (! $inItemChecklistTable) {
                return $rowXml;
            }

            if (str_contains($rowXml, 'Summary/Recommendation')) {
                $inItemChecklistTable = false;
                if (in_array($normalizedChecklistType, ['network_device', 'wifi'], true)) {
                    return $this->injectNetworkDeviceSummaryPlaceholder($rowXml);
                }
                return $this->injectPlaceholderIntoSecondCell($rowXml, 'summary_recommendation');
            }

            if ($normalizedChecklistType === 'server'
                && in_array('good', $cellKeys, true)
                && in_array('nearmaintenance', $cellKeys, true)
                && in_array('na', $cellKeys, true)) {
                return $rowXml;
            }

            if ($normalizedChecklistType === 'ip_phone'
                && in_array('yes', $cellKeys, true)
                && in_array('no', $cellKeys, true)
                && in_array('na', $cellKeys, true)) {
                return $rowXml;
            }

            if (in_array($normalizedChecklistType, ['network_device', 'wifi', 'ups', 'cctv'], true)
                && in_array('good', $cellKeys, true)
                && in_array('nearmaintenance', $cellKeys, true)
                && in_array('na', $cellKeys, true)) {
                return $rowXml;
            }

            if (! preg_match_all('/<w:tc\\b[^>]*>.*?<\\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
                return $rowXml;
            }

            if (count($cellMatches[0]) < 6) {
                return $rowXml;
            }

            $rowXml = $this->injectPlaceholderIntoCellByIndex($rowXml, 3, 'item_' . $itemRowIndex . '_ok');
            $rowXml = $this->injectPlaceholderIntoCellByIndex($rowXml, 4, 'item_' . $itemRowIndex . '_repair');
            $rowXml = $this->injectPlaceholderIntoCellByIndex($rowXml, 5, 'item_' . $itemRowIndex . '_na');
            $itemRowIndex++;

            return $rowXml;
        }, $xml);
        $pendingSignatureLineRow = false;
        $signatureSecondPlaceholder = 'conforme_by';
        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($match) use (&$pendingSignatureLineRow, &$signatureSecondPlaceholder, $normalizedChecklistType) {
            $rowXml = $match[0];

            if (! preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cellMatches, PREG_OFFSET_CAPTURE)) {
                return $rowXml;
            }

            $cellKeys = [];
            foreach ($cellMatches[0] as $cellData) {
                $cellText = $this->normalizeDocxCellText($cellData[0]);
                $cellKeys[] = $this->normalizeDocxLabelKey($cellText);
            }

            $hasCheckedBy = in_array('checkedby', $cellKeys, true);
            $hasConforme = in_array('conforme', $cellKeys, true);
            $hasNotedBy = in_array('notedby', $cellKeys, true);
            if ($hasCheckedBy && ($hasConforme || $hasNotedBy)) {
                $pendingSignatureLineRow = true;
                $signatureSecondPlaceholder = (in_array($normalizedChecklistType, ['server', 'ip_phone'], true) || $hasNotedBy)
                    ? 'noted_by'
                    : 'conforme_by';
                return $rowXml;
            }

            if (! $pendingSignatureLineRow) {
                return $rowXml;
            }

            $pendingSignatureLineRow = false;

            if (count($cellMatches[0]) < 2) {
                return $rowXml;
            }

            $isLikelyBlankLineRow = trim(($cellKeys[0] ?? '')) === '' && trim(($cellKeys[1] ?? '')) === '';
            if (! $isLikelyBlankLineRow) {
                return $rowXml;
            }

            $rowXml = $this->injectPlaceholderIntoCellByIndex($rowXml, 0, 'checked_by');
            $rowXml = $this->injectPlaceholderIntoCellByIndex($rowXml, 1, $signatureSecondPlaceholder);

            return $rowXml;
        }, $xml);

        $checkboxPlaceholders = [
            'CPU' => 'chk_equipment_cpu',
            'MONITOR' => 'chk_equipment_monitor',
            'PRINTER' => 'chk_equipment_printer',
            'AVR' => 'chk_equipment_avr',
            'KEYBOARD' => 'chk_equipment_keyboard',
            'MOUSE' => 'chk_equipment_mouse',
            'UPS' => 'chk_equipment_ups',
            'WINDOWS 7' => 'chk_os_windows_7',
            'WINDOWS 8' => 'chk_os_windows_8',
            'WINDOWS 10' => 'chk_os_windows_10',
            'Enrollment System' => 'chk_software_enrollment_system',
            'Enrolment System' => 'chk_software_enrollment_system',
            'Media Player' => 'chk_software_media_player',
            'Adobe Reader' => 'chk_software_adobe_reader',
            'Word Processor' => 'chk_software_word_processor',
            'Browser' => 'chk_software_browser',
            'Anti-Virus' => 'chk_software_anti_virus',
        ];

        $equipmentLabels = ['CPU', 'MONITOR', 'PRINTER', 'AVR', 'KEYBOARD', 'MOUSE', 'UPS'];
        $osLabels = ['WINDOWS 7', 'WINDOWS 8', 'WINDOWS 10'];
        $softwareLabels = ['Enrollment System', 'Enrolment System', 'Media Player', 'Adobe Reader', 'Word Processor', 'Browser', 'Anti-Virus'];

        $currentCheckboxSection = null;

        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($match) use ($checkboxPlaceholders, $equipmentLabels, $osLabels, $softwareLabels, &$currentCheckboxSection) {
            $rowXml = $match[0];

            preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $cells);

            $normalizedRowText = $this->normalizeDocxLabelKey($this->normalizeDocxCellText($rowXml));
            if (str_contains($normalizedRowText, 'equipmentinstalled')) {
                $currentCheckboxSection = 'equipment';
                return $rowXml;
            }

            if (str_contains($normalizedRowText, 'operatingsysteminstalled')) {
                $currentCheckboxSection = 'os';
                return $rowXml;
            }

            if (str_contains($normalizedRowText, 'softwareapplicationsinstalled')) {
                $currentCheckboxSection = 'software';
                return $rowXml;
            }

            $countMatches = static function (string $xmlRow, array $labels): int {
                $count = 0;
                foreach ($labels as $label) {
                    if (str_contains($xmlRow, $label)) {
                        $count++;
                    }
                }
                return $count;
            };

            $equipmentHits = $countMatches($rowXml, $equipmentLabels);
            $osHits = $countMatches($rowXml, $osLabels);
            $softwareHits = $countMatches($rowXml, $softwareLabels);

            foreach ($checkboxPlaceholders as $label => $placeholder) {
                if (! str_contains($rowXml, $label)) {
                    continue;
                }

                if (in_array($label, $equipmentLabels, true) && $equipmentHits < 2) {
                    continue;
                }

                if (in_array($label, $osLabels, true) && $osHits < 2) {
                    continue;
                }

                if (in_array($label, $softwareLabels, true) && $softwareHits < 2) {
                    continue;
                }

                    $rowXml = $this->injectCheckboxPlaceholderBeforeLabel($rowXml, $label, $placeholder);
            }

            foreach ($cells[0] ?? [] as $cellXml) {
                $cellText = $this->normalizeDocxCellText($cellXml);
                $cellKey = $this->normalizeDocxLabelKey($cellText);

                if (! str_contains($cellKey, 'othersspecify') && ! str_contains($cellKey, 'otherspecify')) {
                    continue;
                }

                $placeholder = match ($currentCheckboxSection) {
                    'equipment' => 'chk_equipment_others',
                    'os' => 'chk_os_others',
                    'software' => 'chk_software_others',
                    default => null,
                };

                if (! is_string($placeholder)) {
                    return $rowXml;
                }

                return $this->injectCheckboxPlaceholderBeforeLabel($rowXml, $cellText, $placeholder);
            }

            return $rowXml;
        }, $xml);

        if ($xml === null) {
            $zip->close();
            @unlink($runtimeTemplate);
            return null;
        }

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $runtimeTemplate;
    }

    private function injectPmIdentifierPageOnePlaceholder(string $xml, ?string $checklistType = null): string
    {
        if (str_contains($xml, 'pm_identifier_page_one')) {
            return $xml;
        }

        $normalizedType = $this->normalizeChecklistType($checklistType);
        [$leftPt, $topPt, $widthPt] = match ($normalizedType) {
            'network_device' => [65.0, 122.0, 260.0],
            'wifi', 'ups' => [65.0, 145.0, 260.0],
            'cctv', 'ip_phone' => [65.0, 127.0, 260.0],
            default => [31.0, 170.0, 260.0],
        };

        $paragraphs = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:sz w:val="1"/></w:rPr></w:pPr><w:bookmarkStart w:id="995" w:name="pm_identifier_page_one"/><w:bookmarkEnd w:id="995"/></w:p>';
        $paragraphs .= $this->absoluteTextBoxPlaceholderXml('print_identifier', $leftPt, $topPt, $widthPt, 14.0);

        return preg_replace('/(<w:body\b[^>]*>)/', '$1' . $paragraphs, $xml, 1) ?? $xml;
    }

    private function buildPmTemplateData($checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null): array
    {
        $map = [];
        $mark = fn ($v) => (!empty($v) && (string) $v !== '0') ? '✓' : '';
        $pick = function (array $keys) use ($valueMap) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $valueMap)) {
                    return $valueMap[$key];
                }
            }
            return null;
        };

        $checklistType = $this->resolvePmTemplateChecklistType($checklist, $valueMap);
        $pcName = $valueMap['pc_name'] ?? $checklist->name ?? '';
        if ($checklistType === 'network_device') {
            $pcName = $valueMap['network_device_product_name'] ?? $pcName;
        }
        if ($checklistType === 'wifi') {
            $pcName = $valueMap['wifi_product_name'] ?? ($valueMap['wifi_name'] ?? $pcName);
        }
        if ($checklistType === 'ups') {
            $upsAssetName = trim((string) ($valueMap['ups_brand_name'] ?? '') . ' ' . (string) ($valueMap['ups_model_name'] ?? ''));
            $pcName = $upsAssetName !== '' ? $upsAssetName : $pcName;
        }
        if ($checklistType === 'cctv') {
            $pcName = $valueMap['cctv_product_name'] ?? $pcName;
        }

        $identifier = $checklist instanceof Psm
            ? $checklist->preventiveMaintenanceIdentifier($checklistType)
            : sprintf('PM%s-%04d', Psm::preventiveMaintenanceCategoryCode($checklistType), (int) ($checklist->psm_id ?? 0));

        $map['identifier'] = $this->sanitizeTemplateValue($identifier);
        $map['print_identifier'] = $this->sanitizeTemplateValue($identifier);
        $map['checklist_date'] = $this->sanitizeTemplateValue($valueMap['checklist_date'] ?? date('Y-m-d'));
        $map['user_operator'] = $this->sanitizeTemplateValue($valueMap['user_operator'] ?? '');
        $map['office_college'] = $this->sanitizeTemplateValue($valueMap['office_college'] ?? '');
        $map['department'] = $this->sanitizeTemplateValue($valueMap['department'] ?? '');
        $map['date_acquired'] = $this->sanitizeTemplateValue($valueMap['date_acquired'] ?? '');
        $map['pc_name'] = $this->sanitizeTemplateValue($pcName);
        $map['brand_name'] = $this->sanitizeTemplateValue($valueMap['brand_name'] ?? '');
        $map['model_name'] = $this->sanitizeTemplateValue($valueMap['model_name'] ?? '');
        $map['serial_number'] = $this->sanitizeTemplateValue($valueMap['serial_number'] ?? '');
        $map['office_located'] = $this->sanitizeTemplateValue($valueMap['office_located'] ?? '');
        $map['ip_address_tagged'] = $this->sanitizeTemplateValue($valueMap['ip_address_tagged'] ?? '');
        $map['vlan'] = $this->sanitizeTemplateValue($valueMap['vlan'] ?? '');
        $map['telephone_number'] = $this->sanitizeTemplateValue($valueMap['telephone_number'] ?? '');
        $map['network_device_category_type'] = $this->sanitizeTemplateValue($valueMap['network_device_category_type'] ?? '');
        $map['network_device_product_name'] = $this->sanitizeTemplateValue($valueMap['network_device_product_name'] ?? '');
        $map['network_device_model_name'] = $this->sanitizeTemplateValue($valueMap['network_device_model_name'] ?? '');
        $map['network_device_serial'] = $this->sanitizeTemplateValue($valueMap['network_device_serial'] ?? '');
        $map['network_device_mac_address'] = $this->sanitizeTemplateValue($valueMap['network_device_mac_address'] ?? '');
        $map['network_device_office_location'] = $this->sanitizeTemplateValue($valueMap['network_device_office_location'] ?? '');
        $map['network_device_ip_address'] = $this->sanitizeTemplateValue($valueMap['network_device_ip_address'] ?? '');
        $map['network_device_vlan'] = $this->sanitizeTemplateValue($valueMap['network_device_vlan'] ?? '');
        $map['wifi_category_type'] = $this->sanitizeTemplateValue($valueMap['wifi_category_type'] ?? '');
        $map['wifi_product_name'] = $this->sanitizeTemplateValue($valueMap['wifi_product_name'] ?? '');
        $map['wifi_model_name'] = $this->sanitizeTemplateValue($valueMap['wifi_model_name'] ?? '');
        $map['wifi_serial'] = $this->sanitizeTemplateValue($valueMap['wifi_serial'] ?? '');
        $map['wifi_mac_address'] = $this->sanitizeTemplateValue($valueMap['wifi_mac_address'] ?? '');
        $map['wifi_office_location'] = $this->sanitizeTemplateValue($valueMap['wifi_office_location'] ?? '');
        $map['wifi_ip_address'] = $this->sanitizeTemplateValue($valueMap['wifi_ip_address'] ?? '');
        $map['wifi_vlan'] = $this->sanitizeTemplateValue($valueMap['wifi_vlan'] ?? '');
        $map['wifi_name'] = $this->sanitizeTemplateValue($valueMap['wifi_name'] ?? '');
        $map['wifi_password'] = $this->sanitizeTemplateValue($valueMap['wifi_password'] ?? '');
        $map['wifi_channel_supported'] = $this->sanitizeTemplateValue($valueMap['wifi_channel_supported'] ?? '');
        $map['ups_category'] = $this->sanitizeTemplateValue($valueMap['ups_category'] ?? '');
        $map['ups_brand_name'] = $this->sanitizeTemplateValue($valueMap['ups_brand_name'] ?? '');
        $map['ups_model_name'] = $this->sanitizeTemplateValue($valueMap['ups_model_name'] ?? '');
        $map['ups_mac_address'] = $this->sanitizeTemplateValue($valueMap['ups_mac_address'] ?? '');
        $map['ups_serial'] = $this->sanitizeTemplateValue($valueMap['ups_serial'] ?? '');
        $map['ups_total_power_capacity'] = $this->sanitizeTemplateValue($valueMap['ups_total_power_capacity'] ?? '');
        $map['cctv_category_type'] = $this->sanitizeTemplateValue($valueMap['cctv_category_type'] ?? '');
        $map['cctv_product_name'] = $this->sanitizeTemplateValue($valueMap['cctv_product_name'] ?? '');
        $map['cctv_model_name'] = $this->sanitizeTemplateValue($valueMap['cctv_model_name'] ?? '');
        $map['cctv_serial'] = $this->sanitizeTemplateValue($valueMap['cctv_serial'] ?? '');
        $map['cctv_mac_address'] = $this->sanitizeTemplateValue($valueMap['cctv_mac_address'] ?? '');
        $map['cctv_office_location'] = $this->sanitizeTemplateValue($valueMap['cctv_office_location'] ?? '');
        $map['cctv_ip_address'] = $this->sanitizeTemplateValue($valueMap['cctv_ip_address'] ?? '');
        $map['cctv_vlan'] = $this->sanitizeTemplateValue($valueMap['cctv_vlan'] ?? '');
        $map['checked_by_title'] = '';
        $map['noted_by_title'] = '';

        $specFields = [
            'processor', 'motherboard', 'memory', 'graphics_card', 'hard_disk', 'optical_drives',
            'monitor', 'casing', 'power_supply_watts', 'keyboard', 'mouse', 'avr_watts', 'ups',
            'printer', 'mac_address', 'ip_address',
        ];

        foreach ($specFields as $field) {
            $map[$field] = $this->sanitizeTemplateValue($valueMap[$field] ?? '');
        }

        if ($checklistType === 'ip_phone') {
            $map['processor'] = $map['brand_name'];
            $map['motherboard'] = $map['model_name'];
            $map['memory'] = $map['serial_number'];
            $map['graphics_card'] = $map['mac_address'];
            $map['hard_disk'] = $map['office_located'];
            $map['optical_drives'] = $map['ip_address_tagged'];
            $map['monitor'] = $map['vlan'];
            $map['casing'] = $map['telephone_number'];
            $map['checked_by_title'] = 'Technical Staff';
            $map['noted_by_title'] = 'DTO Chief';
        }

        if ($checklistType === 'network_device') {
            $map['processor'] = $map['network_device_category_type'];
            $map['motherboard'] = $map['network_device_product_name'];
            $map['memory'] = $map['network_device_model_name'];
            $map['graphics_card'] = $map['network_device_serial'];
            $map['hard_disk'] = $map['network_device_mac_address'];
            $map['optical_drives'] = $map['network_device_office_location'];
            $map['monitor'] = $map['network_device_ip_address'];
            $map['casing'] = $map['network_device_vlan'];
            $map['mac_address'] = $map['network_device_mac_address'];
            $map['ip_address'] = $map['network_device_ip_address'];
            $map['checked_by_title'] = 'Technical Staff';
            $map['noted_by_title'] = 'DTO Chief';
        }

        if ($checklistType === 'wifi') {
            $map['processor'] = $map['wifi_category_type'];
            $map['motherboard'] = $map['wifi_product_name'];
            $map['memory'] = $map['wifi_model_name'];
            $map['graphics_card'] = $map['wifi_serial'];
            $map['hard_disk'] = $map['wifi_mac_address'];
            $map['optical_drives'] = $map['wifi_office_location'];
            $map['monitor'] = $map['wifi_ip_address'];
            $map['casing'] = $map['wifi_vlan'];
            $map['power_supply_watts'] = $map['wifi_name'];
            $map['keyboard'] = $map['wifi_password'];
            $map['mouse'] = $map['wifi_channel_supported'];
            $map['mac_address'] = $map['wifi_mac_address'];
            $map['ip_address'] = $map['wifi_ip_address'];
            $map['checked_by_title'] = 'Technical Staff';
            $map['noted_by_title'] = 'DTO Chief';
        }

        if ($checklistType === 'ups') {
            $map['processor'] = $map['ups_category'];
            $map['motherboard'] = $map['ups_brand_name'];
            $map['memory'] = $map['ups_model_name'];
            $map['graphics_card'] = $map['ups_mac_address'];
            $map['hard_disk'] = $map['ups_serial'];
            $map['optical_drives'] = $map['ups_total_power_capacity'];
            $map['mac_address'] = $map['ups_mac_address'];
        }

        if ($checklistType === 'cctv') {
            $map['processor'] = $map['cctv_category_type'];
            $map['motherboard'] = $map['cctv_product_name'];
            $map['memory'] = $map['cctv_model_name'];
            $map['graphics_card'] = $map['cctv_serial'];
            $map['hard_disk'] = $map['cctv_mac_address'];
            $map['optical_drives'] = $map['cctv_office_location'];
            $map['monitor'] = $map['cctv_ip_address'];
            $map['casing'] = $map['cctv_vlan'];
            $map['mac_address'] = $map['cctv_mac_address'];
            $map['ip_address'] = $map['cctv_ip_address'];
            $map['office_located'] = $map['cctv_office_location'];
        }

        $macAddress = $pick(['cctv_mac_address', 'ups_mac_address', 'wifi_mac_address', 'network_device_mac_address', 'mac_address', 'network_mac', 'network_mac_address', 'network_mac_ip']);
        $ipAddress = $pick(['cctv_ip_address', 'wifi_ip_address', 'network_device_ip_address', 'ip_address', 'network_ip', 'network_ip_address']);
        $map['mac_ip'] = $this->sanitizeTemplateValue(
            trim((string) ($macAddress ?? '') . (($ipAddress ?? '') !== '' ? ' / ' . (string) $ipAddress : ''))
        );

        foreach ($valueMap as $key => $val) {
            if (! is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'equipment_') || str_starts_with($key, 'os_') || str_starts_with($key, 'software_')) {
                $map[$key] = $mark($val);
                $map['chk_' . $key] = $mark($val);
            }
        }

        $checkboxAliases = [
            'equipment_cpu' => ['equipment_cpu'],
            'equipment_monitor' => ['equipment_monitor'],
            'equipment_printer' => ['equipment_printer'],
            'equipment_avr' => ['equipment_avr', 'equipment_avr_watts', 'equipment_avr_(watts)'],
            'equipment_keyboard' => ['equipment_keyboard'],
            'equipment_mouse' => ['equipment_mouse'],
            'equipment_ups' => ['equipment_ups'],
            'equipment_others' => ['equipment_others'],
            'os_windows_7' => ['os_windows_7'],
            'os_windows_8' => ['os_windows_8'],
            'os_windows_10' => ['os_windows_10'],
            'os_others' => ['os_others'],
            'software_enrollment_system' => ['software_enrollment_system', 'software_enrolment_system', 'software_enrollment'],
            'software_media_player' => ['software_media_player'],
            'software_adobe_reader' => ['software_adobe_reader'],
            'software_word_processor' => ['software_word_processor'],
            'software_browser' => ['software_browser'],
            'software_anti_virus' => ['software_anti_virus', 'software_antivirus', 'software_anti-virus'],
            'software_others' => ['software_others'],
        ];

        foreach ($checkboxAliases as $placeholderKey => $sourceKeys) {
            $checkValue = $pick($sourceKeys);
            $map[$placeholderKey] = $mark($checkValue);
            $map['chk_' . $placeholderKey] = $mark($checkValue);
        }

        $map['equipment_others_specify'] = $this->sanitizeTemplateValue($valueMap['equipment_others_specify'] ?? '');
        $map['os_others_specify'] = $this->sanitizeTemplateValue($valueMap['os_others_specify'] ?? '');
        $map['software_others_specify'] = $this->sanitizeTemplateValue($valueMap['software_others_specify'] ?? '');

        $map['summary_recommendation'] = $this->sanitizeTemplateValue(($summaryState['enabled'] ?? true) ? ($summaryState['text'] ?? '') : '');

        if (is_array($icValueMap)) {
            $maintenanceMonth = $this->sanitizeTemplateValue($icValueMap['maintenance_month'] ?? '');
            $map['maintenance_month'] = preg_match('/^\d{4}-\d{2}$/', $maintenanceMonth)
                ? $this->sanitizeTemplateValue(date('F Y', strtotime($maintenanceMonth . '-01')))
                : $maintenanceMonth;
            $map['checked_by'] = $this->sanitizeTemplateValue($icValueMap['checked_by'] ?? '');
            $map['conforme_by'] = $this->sanitizeTemplateValue($icValueMap['conforme_by'] ?? '');
            $map['noted_by'] = $this->sanitizeTemplateValue(
                $this->resolveItemChecklistNotedBy($icValueMap, $checklistType)
            );

            foreach ($icValueMap as $key => $status) {
                if (! is_string($key)) {
                    continue;
                }

                if (! preg_match('/^item_(\\d+)$/', $key, $matches)) {
                    continue;
                }

                $index = $matches[1];
                $normalized = strtolower(trim((string) $status));
                $map['item_' . $index . '_ok'] = $normalized === 'ok' ? '✓' : '';
                $map['item_' . $index . '_repair'] = $normalized === 'repair' ? '✓' : '';
                $map['item_' . $index . '_na'] = in_array($normalized, ['na', 'n/a', '?'], true) ? '✓' : '';
            }
        }

        return $map;
    }

    private function createFilledPmTemplateDocx($checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null): ?string
    {
        if (! class_exists(TemplateProcessor::class)) {
            return null;
        }

        $checklistType = $this->resolvePmTemplateChecklistType($checklist, $valueMap);
        $templatePath = $this->getExistingPmTemplatePath($checklistType);
        if (! $templatePath) {
            return null;
        }

        $runtimeTemplatePath = null;
        $processor = new TemplateProcessor($templatePath);
        $values = $this->buildPmTemplateData($checklist, $valueMap, $icValueMap, $summaryState);

        $templateVarsList = method_exists($processor, 'getVariables')
            ? $processor->getVariables()
            : null;

        if (is_array($templateVarsList) && count($templateVarsList) === 0) {
            $runtimeTemplatePath = $this->buildRuntimePmPlaceholderTemplate($checklistType);
            if ($runtimeTemplatePath) {
                $processor = new TemplateProcessor($runtimeTemplatePath);
                $templateVarsList = method_exists($processor, 'getVariables')
                    ? $processor->getVariables()
                    : null;
            }
        }

        $templateVars = is_array($templateVarsList)
            ? array_fill_keys($templateVarsList, true)
            : null;

        if ($templateVars !== null) {
            foreach (array_keys($templateVars) as $name) {
                $processor->setValue($name, (string)($values[$name] ?? ''));
            }
        } else {
            foreach ($values as $name => $value) {
                $processor->setValue($name, $value);
            }
        }

        $tempDocx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_template_' . uniqid() . '.docx';
        $processor->saveAs($tempDocx);

        if ($runtimeTemplatePath && file_exists($runtimeTemplatePath)) {
            @unlink($runtimeTemplatePath);
        }

        if (! file_exists($tempDocx) || filesize($tempDocx) === 0) {
            return null;
        }

        return $tempDocx;
    }

    private function createFilledPmTemplateHtml($checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null): ?string
    {
        $tempDocx = $this->createFilledPmTemplateDocx($checklist, $valueMap, $icValueMap, $summaryState);
        if (! $tempDocx) {
            return null;
        }

        $tempHtml = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_template_' . uniqid() . '.html';

        try {
            $phpWord = IOFactory::load($tempDocx);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            $htmlWriter->save($tempHtml);

            $html = file_get_contents($tempHtml);
            @unlink($tempDocx);
            @unlink($tempHtml);

            if (! is_string($html) || $html === '') {
                return null;
            }

            return $html;
        } catch (\Throwable $e) {
            @unlink($tempDocx);
            @unlink($tempHtml);
            return null;
        }
    }

    private function findSofficeBinary(): ?string
    {
        $candidates = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function convertDocxToPdfUsingLibreOffice(string $docxPath): ?string
    {
        $soffice = $this->findSofficeBinary();
        if (! $soffice) {
            return null;
        }

        $outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_pdf_' . uniqid();
        @mkdir($outputDir, 0777, true);

        $command = escapeshellarg($soffice)
            . ' --headless --convert-to pdf --outdir '
            . escapeshellarg($outputDir)
            . ' '
            . escapeshellarg($docxPath);

        $exitCode = 1;
        @exec($command, $unusedOutput, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        $pdfPath = $outputDir . DIRECTORY_SEPARATOR . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
        if (! file_exists($pdfPath) || filesize($pdfPath) === 0) {
            return null;
        }

        return $pdfPath;
    }

    private function convertDocxToPdfUsingWordCom(string $docxPath): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! class_exists('COM')) {
            return null;
        }

        $pdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_pdf_' . uniqid() . '.pdf';
        $word = null;
        $document = null;

        try {
            $word = new \COM('Word.Application');
            $word->Visible = false;
            $word->DisplayAlerts = 0;

            $document = $word->Documents->Open($docxPath, false, true, false);
            $wdFormatPDF = 17;
            $document->SaveAs($pdfPath, $wdFormatPDF);
            $document->Close(false);
            $word->Quit(false);

            if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
                return $pdfPath;
            }
        } catch (\Throwable $e) {
            if ($document) {
                try {
                    $document->Close(false);
                } catch (\Throwable $unused) {
                }
            }

            if ($word) {
                try {
                    $word->Quit(false);
                } catch (\Throwable $unused) {
                }
            }
        }

        return null;
    }

    private function generatePdfFromDocxTemplate($id, $checklist, array $valueMap, ?array $icValueMap = null, ?array $summaryState = null, ?string $filename = null, bool $inline = false)
    {
        $tempDocx = null;
        $convertedPdfPath = null;
        $checklistType = $this->resolvePmTemplateChecklistType($checklist, $valueMap);

        $cachePayload = [
            'source' => 'docx_template',
            'version' => 14,
            'id' => $id,
            'checklist_id' => $checklist->psm_id ?? null,
            'checklist_type' => $checklistType,
            'value_map' => $valueMap,
            'ic_value_map' => $icValueMap,
            'summary_state' => $summaryState,
        ];
        $cacheKey = 'pm_pdf:' . md5(serialize($cachePayload));
        $useCache = ! $inline;
        $cachedContent = $useCache ? Cache::get($cacheKey) : null;
        if ($useCache && is_string($cachedContent) && $cachedContent !== '') {
            $downloadName = $filename ?: $this->getExportFilename('pdf', $checklistType);
            return response($cachedContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($cachedContent))
                ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
        }

        try {
            $tempDocx = $this->createFilledPmTemplateDocx($checklist, $valueMap, $icValueMap, $summaryState);
            if (! $tempDocx) {
                return null;
            }

            $downloadName = $filename ?: $this->getExportFilename('pdf', $checklistType);

            $convertedPdfPath = $this->convertDocxToPdfUsingWordCom($tempDocx);
            if (! $convertedPdfPath) {
                $convertedPdfPath = $this->convertDocxToPdfUsingLibreOffice($tempDocx);
            }

            if ($convertedPdfPath && file_exists($convertedPdfPath) && filesize($convertedPdfPath) > 0) {
                $content = file_get_contents($convertedPdfPath);
                if ($content !== false && strlen($content) > 0) {
                    if ($useCache) {
                        Cache::put($cacheKey, $content, now()->addSeconds(self::VIEW_PDF_CACHE_SECONDS));
                    }
                    return response($content, 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                        ->header('Content-Length', strlen($content))
                        ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
                }
            }

            // Fallback to PDF-template overlay only when native DOCX conversion
            // is unavailable in the current environment.
            $templatePdf = $this->generatePdfFromPdfTemplate($id, $checklist, $valueMap, $icValueMap, $summaryState, $filename, $inline);
            if ($templatePdf) {
                return $templatePdf;
            }

            $html = $this->createFilledPmTemplateHtml($checklist, $valueMap, $icValueMap, $summaryState);
            if (! $html) {
                return null;
            }

            if (class_exists(DomPdfFacade::class)) {
                $pdf = DomPdfFacade::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                $content = $pdf->output();
                if (! is_string($content) || $content === '') {
                    return null;
                }

                if ($useCache) {
                    Cache::put($cacheKey, $content, now()->addSeconds(self::VIEW_PDF_CACHE_SECONDS));
                }

                return response($content, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                    ->header('Content-Length', strlen($content))
                    ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
            }

            if (class_exists(Dompdf::class)) {
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $content = $dompdf->output();
                if ($useCache) {
                    Cache::put($cacheKey, $content, now()->addSeconds(self::VIEW_PDF_CACHE_SECONDS));
                }

                return response($content, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"')
                    ->header('Content-Length', strlen($content))
                    ->header('Cache-Control', $this->pdfCacheControlHeader($inline));
            }
        } catch (\Throwable $e) {
            return null;
        } finally {
            if ($tempDocx && file_exists($tempDocx)) {
                @unlink($tempDocx);
            }

            if ($convertedPdfPath && file_exists($convertedPdfPath)) {
                @unlink($convertedPdfPath);
            }
        }

        return null;
    }

    /**
     * Generate Word document programmatically with database data filled in.
     * Builds the document layout matching the CMU template.
     */
    private function generateWordFromTemplate($checklist, $valueMap, $icValueMap = null, $summaryState = null)
    {
        try {
            // Clear any output buffers to prevent corruption
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Always generate Word from the official DOCX template.
            $tempDocx = $this->createFilledPmTemplateDocx(
                $checklist,
                $valueMap,
                is_array($icValueMap) ? $icValueMap : null,
                is_array($summaryState) ? $summaryState : null
            );
            if ($tempDocx) {
                $content = file_get_contents($tempDocx);
                @unlink($tempDocx);

                if ($content !== false && strlen($content) > 0) {
                    $filename = $this->getExportFilename('docx', $this->resolvePmTemplateChecklistType($checklist, $valueMap));

                    return response($content, 200)
                        ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->header('Content-Length', strlen($content))
                        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                }
            }

            return response()->json([
                'error' => 'Word export template rendering failed.'
            ], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate Word document: ' . $e->getMessage()], 500);
        }
    }
}
