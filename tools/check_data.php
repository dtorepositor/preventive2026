<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Database Status ===\n\n";

// Check PSM templates
$templates = App\Models\Psm::where('type', 'template')->get();
echo "PSM Templates:\n";
foreach ($templates as $t) {
    echo "  - ID: {$t->psm_id}, Name: {$t->name}\n";
}
echo "\n";

// Check for Preventive Maintenance template
$pmTemplate = App\Models\Psm::where('type', 'template')
    ->where('name', 'Preventive Maintenance Checklist')
    ->first();

if ($pmTemplate) {
    echo "PM Template Found:\n";
    echo "  - Template ID: {$pmTemplate->psm_id}\n";
    echo "  - Template Name: {$pmTemplate->name}\n\n";
    
    // Count submissions
    $submissionCount = App\Models\Psm::where('template_psm_id', $pmTemplate->psm_id)->count();
    echo "Submissions linked to PM template: {$submissionCount}\n\n";
    
    // Get a few submissions
    $submissions = App\Models\Psm::where('template_psm_id', $pmTemplate->psm_id)
        ->with('values.variable')
        ->take(5)
        ->get();
    
    echo "First 5 submissions:\n";
    foreach ($submissions as $sub) {
        $pcName = $sub->name;
        echo "  - PSM ID: {$sub->psm_id}, Name: {$pcName}\n";
    }
} else {
    echo "WARNING: Preventive Maintenance Checklist template NOT FOUND!\n";
    echo "This is why no checklists are showing.\n";
    echo "Run: php artisan db:seed --class=PsmTemplateSeeder\n";
}

echo "\n=== Other Tables ===\n";
echo "PreventiveMaintenanceChecklist records: " . App\Models\PreventiveMaintenanceChecklist::count() . "\n";
echo "ItemChecklist records: " . App\Models\ItemChecklist::count() . "\n";
