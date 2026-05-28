<?php

namespace App\Http\Controllers;

use App\Models\CollegeOffice;
use App\Models\PreventiveMaintenancePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;

class PreventiveMaintenancePlanController extends Controller
{
    private const NO_TAGGED_OFFICES_TEXT = 'No tagged colleges or offices';

    public function loadData(): JsonResponse
    {
        return response()->json([
            'plans' => $this->plansPayload(),
            'office_suggestions' => $this->officeSuggestionsPayload(),
            'current_year' => $this->currentAppYear(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 5), 100));
        $plans = PreventiveMaintenancePlan::withTrashed()
            ->with('schedules')
            ->orderByDesc('year')
            ->orderBy('name')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'current_page' => $plans->currentPage(),
            'data' => collect($plans->items())
                ->map(fn (PreventiveMaintenancePlan $plan) => $this->transformPlan($plan))
                ->values()
                ->all(),
            'first_page_url' => $plans->url(1),
            'from' => $plans->firstItem(),
            'last_page' => $plans->lastPage(),
            'last_page_url' => $plans->url($plans->lastPage()),
            'next_page_url' => $plans->nextPageUrl(),
            'path' => $request->url(),
            'per_page' => $plans->perPage(),
            'prev_page_url' => $plans->previousPageUrl(),
            'to' => $plans->lastItem(),
            'total' => $plans->total(),
            'office_suggestions' => $this->officeSuggestionsPayload(),
            'current_year' => $this->currentAppYear(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $plan = PreventiveMaintenancePlan::with('schedules')->findOrFail($id);

        return response()->json($this->transformPlan($plan));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'schedule_category' => ['required', 'string', 'in:' . implode(',', PreventiveMaintenancePlan::SCHEDULE_CATEGORIES)],
        ]);

        $validated['year'] = $this->currentAppYear();

        $plan = PreventiveMaintenancePlan::create($validated)->load('schedules');

        return response()->json($this->transformPlan($plan), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = PreventiveMaintenancePlan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'schedule_category' => ['required', 'string', 'in:' . implode(',', PreventiveMaintenancePlan::SCHEDULE_CATEGORIES)],
        ]);

        $plan->update($validated);
        $plan->load('schedules');

        return response()->json($this->transformPlan($plan));
    }

    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $plan = PreventiveMaintenancePlan::findOrFail($id);

        $validated = $request->validate([
            'schedule' => ['nullable', 'array'],
            'schedule.*.month' => ['required', 'integer', 'between:1,12'],
            'schedule.*.offices' => ['nullable', 'array'],
            'schedule.*.offices.*' => ['nullable', 'string', 'max:255'],
        ]);

        $scheduleEntries = collect($validated['schedule'] ?? [])
            ->flatMap(function (array $entry) {
                return collect($entry['offices'] ?? [])
                    ->map(fn ($office) => trim((string) $office))
                    ->filter()
                    ->unique()
                    ->map(fn (string $office) => [
                        'month' => (int) $entry['month'],
                        'office_name' => $office,
                    ]);
            })
            ->unique(fn (array $entry) => $entry['month'] . '|' . mb_strtolower($entry['office_name']))
            ->sortBy(fn (array $entry) => sprintf('%02d-%s', $entry['month'], mb_strtolower($entry['office_name'])))
            ->values();

        DB::transaction(function () use ($plan, $scheduleEntries) {
            $plan->schedules()->delete();

            if ($scheduleEntries->isNotEmpty()) {
                $plan->schedules()->createMany(
                    $scheduleEntries->map(fn (array $entry) => [
                        'month' => $entry['month'],
                        'office_name' => $entry['office_name'],
                    ])->all()
                );
            }
        });

        $plan->load('schedules');

        return response()->json($this->transformPlan($plan));
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = PreventiveMaintenancePlan::findOrFail($id);
        $plan->delete();
        $plan->load('schedules');

        return response()->json($this->transformPlan($plan));
    }

    public function restore(int $id): JsonResponse
    {
        $plan = PreventiveMaintenancePlan::withTrashed()->findOrFail($id);
        $plan->restore();
        $plan->load('schedules');

        return response()->json($this->transformPlan($plan));
    }

    public function print(Request $request, int $id)
    {
        $plan = PreventiveMaintenancePlan::with('schedules')
            ->findOrFail($id);

        $format = strtolower((string) $request->query('format', 'pdf'));
        $format = in_array($format, ['pdf', 'word'], true) ? $format : 'pdf';

        if ($format === 'word') {
            $filename = $this->exportFilename($plan, 'docx');
            $docxContent = $this->buildTemplateDocx($plan);

            return response($docxContent, 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($docxContent));
        }

        $filename = $this->exportFilename($plan, 'pdf');
        $pdfContent = $this->buildTemplatePdf($plan);

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($pdfContent));
    }

    private function transformPlan(PreventiveMaintenancePlan $plan): array
    {
        $scheduleMap = collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => []])
            ->all();

        foreach ($plan->schedules as $schedule) {
            $scheduleMap[(int) $schedule->month][] = $schedule->office_name;
        }

        $scheduleMap = collect($scheduleMap)
            ->map(fn (array $offices) => collect($offices)->filter()->unique()->sort()->values()->all())
            ->all();

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'year' => (int) $plan->year,
            'schedule_category' => in_array($plan->schedule_category, PreventiveMaintenancePlan::SCHEDULE_CATEGORIES, true)
                ? $plan->schedule_category
                : PreventiveMaintenancePlan::SCHEDULE_CATEGORY_MONTHLY,
            'schedule_map' => $scheduleMap,
            'tag_count' => collect($scheduleMap)->flatten()->count(),
            'months_used' => collect($scheduleMap)->filter(fn (array $offices) => !empty($offices))->count(),
            'is_deleted' => $plan->trashed(),
            'deleted_at' => $plan->deleted_at?->toISOString(),
            'created_at' => $plan->created_at?->toISOString(),
            'updated_at' => $plan->updated_at?->toISOString(),
        ];
    }

    private function currentAppYear(): int
    {
        return (int) now(config('app.timezone'))->format('Y');
    }

    private function plansPayload()
    {
        return PreventiveMaintenancePlan::withTrashed()
            ->with('schedules')
            ->orderByDesc('year')
            ->orderBy('name')
            ->get()
            ->map(fn (PreventiveMaintenancePlan $plan) => $this->transformPlan($plan))
            ->values();
    }

    private function officeSuggestionsPayload()
    {
        return CollegeOffice::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn (string $value) => mb_strtolower($value))
            ->sort(fn (string $left, string $right) => strcasecmp($left, $right))
            ->values()
            ->all();
    }

    private function buildTemplatePdf(PreventiveMaintenancePlan $plan): string
    {
        $templatePath = $this->planTemplatePath($plan->schedule_category);
        if (! $templatePath) {
            abort(500, 'Preventive maintenance plan PDF template not found.');
        }

        $pdf = new Fpdi('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $pageCount = $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($templateId);
        $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

        $this->overlayPlanTemplateData($pdf, $plan);

        for ($page = 2; $page <= $pageCount; $page++) {
            $templateId = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);
        }

        return $pdf->Output('', 'S');
    }

    private function buildTemplateDocx(PreventiveMaintenancePlan $plan): string
    {
        $templatePath = $this->planDocxTemplatePath($plan->schedule_category);
        if (! $templatePath) {
            abort(500, 'Preventive maintenance plan Word template not found.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'pm_plan_') . '.docx';
        copy($templatePath, $tempPath);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            abort(500, 'Unable to open preventive maintenance plan Word template.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (! is_string($documentXml) || $documentXml === '') {
            $zip->close();
            @unlink($tempPath);
            abort(500, 'Preventive maintenance plan Word template is missing document XML.');
        }

        $filledDocumentXml = $this->filledPlanDocumentXml($documentXml, $plan);
        $this->addPlanPrintFooterToDocx($zip, $filledDocumentXml);

        $zip->close();

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        if (! is_string($content) || $content === '') {
            abort(500, 'Unable to generate preventive maintenance plan Word export.');
        }

        return $content;
    }

    private function planTemplatePath(?string $category): ?string
    {
        $templateName = match ($category) {
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_QUARTERLY => 'preventive-maintenance-plan(quarterly).pdf',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_HALF_QUARTER => 'preventive-maintenance-plan(half-quarter).pdf',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_YEARLY => 'preventive-maintenance-plan(yearly).pdf',
            default => 'preventive-maintenance-plan(monthly).pdf',
        };

        $path = resource_path('views/template/' . $templateName);

        return file_exists($path) ? $path : null;
    }

    private function planDocxTemplatePath(?string $category): ?string
    {
        $templateName = match ($category) {
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_QUARTERLY => 'preventive-maintenance-plan(quarterly).docx',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_HALF_QUARTER => 'preventive-maintenance-plan(half-quarter).docx',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_YEARLY => 'preventive-maintenance-plan(yearly).docx',
            default => 'preventive-maintenance-plan(monthly).docx',
        };

        $path = storage_path('template/' . $templateName);

        return file_exists($path) ? $path : null;
    }

    private function filledPlanDocumentXml(string $xml, PreventiveMaintenancePlan $plan): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tables = $xpath->query('//w:tbl');
        if (! $tables || $tables->length < 2) {
            return $xml;
        }

        $metaRows = $xpath->query('./w:tr', $tables->item(0));
        $metaValues = [
            $plan->name,
            (string) $plan->year,
            $this->categoryLabel($plan->schedule_category),
            $plan->trashed() ? 'Deleted' : 'Active',
        ];

        foreach ($metaValues as $rowIndex => $value) {
            $row = $metaRows?->item($rowIndex);
            if (! $row) {
                continue;
            }

            $cells = $xpath->query('./w:tc', $row);
            $cell = $cells?->item(1);
            if ($cell instanceof \DOMElement) {
                $this->setWordCellText($dom, $cell, $value);
            }
        }

        $scheduleRows = $xpath->query('./w:tr', $tables->item(1));
        foreach ($this->planScheduleRows($plan) as $index => $rowData) {
            $row = $scheduleRows?->item($index + 1);
            if (! $row) {
                continue;
            }

            $cells = $xpath->query('./w:tc', $row);
            $officesCell = $cells?->item(($cells?->length ?? 0) > 2 ? 2 : 1);
            if ($officesCell instanceof \DOMElement) {
                $this->setWordCellText(
                    $dom,
                    $officesCell,
                    empty($rowData['offices']) ? self::NO_TAGGED_OFFICES_TEXT : implode(', ', $rowData['offices'])
                );
            }
        }

        return $dom->saveXML();
    }

    private function addPlanPrintFooterToDocx(\ZipArchive $zip, string $documentXml): void
    {
        $footerPart = 'word/footer1.xml';
        $footerRelId = $this->ensureDocxRelationship(
            $zip,
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer',
            'footer1.xml'
        );

        $this->ensureDocxContentType(
            $zip,
            '/word/footer1.xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml'
        );

        $this->replaceDocxPart($zip, $footerPart, $this->planFooterXml());

        $this->replaceDocxPart($zip, 'word/document.xml', $this->attachFooterReferenceToDocumentXml($documentXml, $footerRelId));
    }

    private function replaceDocxPart(\ZipArchive $zip, string $partName, string $content): void
    {
        if ($zip->locateName($partName) !== false) {
            $zip->deleteName($partName);
        }

        $zip->addFromString($partName, $content);
    }

    private function planFooterXml(): string
    {
        $printedAt = 'Printed: ' . now(config('app.timezone'))->format('F j, Y g:i A');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:p><w:pPr><w:jc w:val="right"/></w:pPr>'
            . '<w:r><w:rPr><w:sz w:val="16"/></w:rPr><w:t xml:space="preserve">'
            . htmlspecialchars($printedAt, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            . '</w:t></w:r></w:p></w:ftr>';
    }

    private function ensureDocxRelationship(\ZipArchive $zip, string $type, string $target): string
    {
        $relsPath = 'word/_rels/document.xml.rels';
        $xml = $zip->getFromName($relsPath);
        if (! is_string($xml) || $xml === '') {
            return 'rIdFooter1';
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($xpath->query('/rel:Relationships/rel:Relationship') as $relationship) {
            if (
                $relationship instanceof \DOMElement
                && $relationship->getAttribute('Type') === $type
                && $relationship->getAttribute('Target') === $target
            ) {
                return $relationship->getAttribute('Id');
            }
        }

        $ids = [];
        foreach ($xpath->query('/rel:Relationships/rel:Relationship') as $relationship) {
            if ($relationship instanceof \DOMElement && preg_match('/^rId(\d+)$/', $relationship->getAttribute('Id'), $matches)) {
                $ids[] = (int) $matches[1];
            }
        }

        $id = 'rId' . ((empty($ids) ? 0 : max($ids)) + 1);
        $relationship = $dom->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship');
        $relationship->setAttribute('Id', $id);
        $relationship->setAttribute('Type', $type);
        $relationship->setAttribute('Target', $target);
        $dom->documentElement->appendChild($relationship);

        $this->replaceDocxPart($zip, $relsPath, $dom->saveXML());

        return $id;
    }

    private function ensureDocxContentType(\ZipArchive $zip, string $partName, string $contentType): void
    {
        $path = '[Content_Types].xml';
        $xml = $zip->getFromName($path);
        if (! is_string($xml) || $xml === '') {
            return;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');

        foreach ($xpath->query('/ct:Types/ct:Override') as $override) {
            if ($override instanceof \DOMElement && $override->getAttribute('PartName') === $partName) {
                return;
            }
        }

        $override = $dom->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override');
        $override->setAttribute('PartName', $partName);
        $override->setAttribute('ContentType', $contentType);
        $dom->documentElement->appendChild($override);

        $this->replaceDocxPart($zip, $path, $dom->saveXML());
    }

    private function attachFooterReferenceToDocumentXml(string $xml, string $footerRelId): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sectionProperties = $xpath->query('//w:sectPr')->item(0);
        if (! $sectionProperties instanceof \DOMElement) {
            return $xml;
        }

        foreach ($xpath->query('./w:footerReference', $sectionProperties) as $footerReference) {
            if ($footerReference instanceof \DOMElement && $footerReference->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'type') === 'default') {
                $footerReference->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $footerRelId);
                return $dom->saveXML();
            }
        }

        $footerReference = $dom->createElement('w:footerReference');
        $footerReference->setAttribute('w:type', 'default');
        $footerReference->setAttribute('r:id', $footerRelId);

        $firstChild = $sectionProperties->firstChild;
        if ($firstChild) {
            $sectionProperties->insertBefore($footerReference, $firstChild);
        } else {
            $sectionProperties->appendChild($footerReference);
        }

        return $dom->saveXML();
    }

    private function setWordCellText(\DOMDocument $dom, \DOMElement $cell, string $text): void
    {
        $tcPr = null;
        foreach (iterator_to_array($cell->childNodes) as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tcPr') {
                $tcPr = $child->cloneNode(true);
            }
        }

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        if ($tcPr) {
            $cell->appendChild($tcPr);
        }

        $cell->appendChild($this->makeWordParagraph($dom, $text, 'left', 18));
    }

    private function makeWordParagraph(\DOMDocument $dom, string $text, string $align = 'left', int $fontHalfPoints = 18): \DOMElement
    {
        $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paragraph = $dom->createElementNS($w, 'w:p');
        $paragraphProperties = $dom->createElementNS($w, 'w:pPr');
        $justification = $dom->createElementNS($w, 'w:jc');
        $justification->setAttributeNS($w, 'w:val', $align);
        $paragraphProperties->appendChild($justification);
        $paragraph->appendChild($paragraphProperties);

        $run = $dom->createElementNS($w, 'w:r');
        $runProperties = $dom->createElementNS($w, 'w:rPr');
        $size = $dom->createElementNS($w, 'w:sz');
        $size->setAttributeNS($w, 'w:val', (string) $fontHalfPoints);
        $runProperties->appendChild($size);
        $run->appendChild($runProperties);

        $textNode = $dom->createElementNS($w, 'w:t');
        $textNode->setAttribute('xml:space', 'preserve');
        $textNode->appendChild($dom->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);

        return $paragraph;
    }


    private function overlayPlanTemplateData(Fpdi $pdf, PreventiveMaintenancePlan $plan): void
    {
        $printedAt = now(config('app.timezone'))->format('F j, Y g:i A');
        $rows = $this->planScheduleRows($plan);

        $this->drawTemplateText($pdf, 153.0, 264.0, 'Printed: ' . $printedAt, 45.0, 'R', 8.0);
        $this->drawTemplateText($pdf, 70.5, 54.5, $plan->name, 118.0, 'L', 10.0);
        $this->drawTemplateText($pdf, 70.5, 67.5, (string) $plan->year, 118.0, 'L', 10.0);
        $this->drawTemplateText($pdf, 70.5, 80.5, $this->categoryLabel($plan->schedule_category), 118.0, 'L', 9.0);
        $this->drawTemplateText($pdf, 70.5, 93.5, $plan->trashed() ? 'Deleted' : 'Active', 118.0, 'L', 9.0);

        $layout = $this->planTemplateTableLayout($plan->schedule_category);

        foreach ($rows as $index => $row) {
            $y = $layout['first_y'] + ($index * $layout['row_height']);
            $hasOffices = ! empty($row['offices']);
            $offices = $hasOffices ? implode(', ', $row['offices']) : self::NO_TAGGED_OFFICES_TEXT;

            $this->drawTemplateText(
                $pdf,
                $layout['offices_x'],
                $y,
                $offices,
                $layout['offices_w'],
                'L',
                $layout['font_size'],
                $hasOffices ? '' : 'I',
                $hasOffices ? [0, 0, 0] : [71, 85, 105],
                $layout['line_height'] ?? 4.0,
                $layout['max_height'] ?? 8.0
            );
        }
    }

    private function planTemplateTableLayout(?string $category): array
    {
        return match ($category) {
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_YEARLY => [
                'first_y' => 127.0,
                'row_height' => 18.25,
                'offices_x' => 107.5,
                'offices_w' => 82.5,
                'font_size' => 8.5,
            ],
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_HALF_QUARTER => [
                'first_y' => 127.0,
                'row_height' => 12.35,
                'offices_x' => 107.5,
                'offices_w' => 82.5,
                'font_size' => 8.0,
            ],
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_QUARTERLY => [
                'first_y' => 127.0,
                'row_height' => 8.68,
                'offices_x' => 107.5,
                'offices_w' => 82.5,
                'font_size' => 8.0,
            ],
            default => [
                'first_y' => 125.0,
                'row_height' => 8.68,
                'offices_x' => 61.0,
                'offices_w' => 130.5,
                'font_size' => 6.8,
                'line_height' => 3.3,
                'max_height' => 7.8,
            ],
        };
    }

    private function drawTemplateText(
        Fpdi $pdf,
        float $x,
        float $y,
        string $text,
        float $w,
        string $align = 'L',
        float $fontSize = 9.0,
        string $fontStyle = '',
        array $textColor = [0, 0, 0],
        float $lineHeight = 4.0,
        float $maxHeight = 8.0
    ): void
    {
        $text = trim((string) $text);
        if ($text === '') {
            return;
        }

        $pdf->SetFont('helvetica', $fontStyle, $fontSize);
        $pdf->SetTextColor($textColor[0] ?? 0, $textColor[1] ?? 0, $textColor[2] ?? 0);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, $lineHeight, $text, 0, $align, false, 1, '', '', true, 0, false, true, $maxHeight, 'T', true);
    }

    private function planScheduleRows(PreventiveMaintenancePlan $plan): array
    {
        $scheduleMap = collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => []])
            ->all();

        foreach ($plan->schedules as $schedule) {
            $scheduleMap[(int) $schedule->month][] = trim((string) $schedule->office_name);
        }

        foreach ($scheduleMap as $month => $offices) {
            $scheduleMap[$month] = collect($offices)
                ->filter()
                ->unique(fn (string $office) => mb_strtolower($office))
                ->sort(fn (string $left, string $right) => strcasecmp($left, $right))
                ->values()
                ->all();
        }

        return collect($this->schedulePeriodsForCategory($plan->schedule_category))
            ->map(function (array $period) use ($scheduleMap) {
                $offices = collect($period['months'])
                    ->flatMap(fn (int $month) => $scheduleMap[$month] ?? [])
                    ->filter()
                    ->unique(fn (string $office) => mb_strtolower($office))
                    ->sort(fn (string $left, string $right) => strcasecmp($left, $right))
                    ->values()
                    ->all();

                return [
                    'period' => $period['label'],
                    'months' => $this->monthListLabel($period['months']),
                    'offices' => $offices,
                ];
            })
            ->all();
    }

    private function schedulePeriodsForCategory(?string $category): array
    {
        return match ($category) {
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_QUARTERLY => [
                ['label' => '1st Quarter', 'months' => [1, 2, 3]],
                ['label' => '2nd Quarter', 'months' => [4, 5, 6]],
                ['label' => '3rd Quarter', 'months' => [7, 8, 9]],
                ['label' => '4th Quarter', 'months' => [10, 11, 12]],
            ],
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_HALF_QUARTER => [
                ['label' => '1st Half', 'months' => [1, 2, 3, 4, 5, 6]],
                ['label' => '2nd Half', 'months' => [7, 8, 9, 10, 11, 12]],
            ],
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_YEARLY => [
                ['label' => 'Yearly', 'months' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
            ],
            default => collect(range(1, 12))
                ->map(fn (int $month) => ['label' => $this->monthName($month), 'months' => [$month]])
                ->all(),
        };
    }

    private function categoryLabel(?string $category): string
    {
        return match ($category) {
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_QUARTERLY => 'Quarterly',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_HALF_QUARTER => 'Half Quarter',
            PreventiveMaintenancePlan::SCHEDULE_CATEGORY_YEARLY => 'Yearly',
            default => 'Monthly',
        };
    }

    private function monthListLabel(array $months): string
    {
        return collect($months)
            ->map(fn (int $month) => $this->monthName($month))
            ->implode(', ');
    }

    private function monthName(int $month): string
    {
        return now()->startOfYear()->month($month)->format('F');
    }

    private function exportFilename(PreventiveMaintenancePlan $plan, string $extension): string
    {
        $slug = Str::slug($plan->name ?: 'preventive-maintenance-plan');

        return ($slug !== '' ? $slug : 'preventive-maintenance-plan')
            . '-' . $plan->year
            . '.' . ltrim($extension, '.');
    }
}
