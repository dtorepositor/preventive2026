<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollegeOfficeController;
use App\Http\Controllers\PreventiveMaintenancePlanController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'me'])
        ->middleware('role:superadmin,admin,encoder');
    Route::get('/user', [AuthController::class, 'me'])
        ->middleware('role:superadmin,admin,encoder');

    Route::middleware('role:superadmin,admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
        Route::patch('/users/{user}/enable', [UserController::class, 'enable']);
        Route::patch('/users/{user}/disable', [UserController::class, 'disable']);

        Route::get('/reports/preventive-maintenance', [ApiController::class, 'preventiveMaintenanceReport']);

        Route::get('/preventive-maintenance-plans/load-data', [PreventiveMaintenancePlanController::class, 'loadData']);
        Route::get('/preventive-maintenance-plans/{id}', [PreventiveMaintenancePlanController::class, 'show']);
        Route::get('/preventive-maintenance-plans/{id}/print', [PreventiveMaintenancePlanController::class, 'print']);
        Route::post('/preventive-maintenance-plans', [PreventiveMaintenancePlanController::class, 'store']);
        Route::put('/preventive-maintenance-plans/{id}', [PreventiveMaintenancePlanController::class, 'update']);
        Route::put('/preventive-maintenance-plans/{id}/schedule', [PreventiveMaintenancePlanController::class, 'updateSchedule']);
        Route::delete('/preventive-maintenance-plans/{id}', [PreventiveMaintenancePlanController::class, 'destroy']);
        Route::post('/preventive-maintenance-plans/{id}/restore', [PreventiveMaintenancePlanController::class, 'restore']);

        Route::post('/college-offices', [CollegeOfficeController::class, 'store']);
        Route::put('/college-offices/{collegeOffice}', [CollegeOfficeController::class, 'update']);
        Route::delete('/college-offices/{collegeOffice}', [CollegeOfficeController::class, 'destroy']);
        Route::post('/college-offices/{collegeOffice}/departments', [CollegeOfficeController::class, 'storeDepartment']);
        Route::put('/college-offices/{collegeOffice}/departments/{department}', [CollegeOfficeController::class, 'updateDepartment']);
        Route::delete('/college-offices/{collegeOffice}/departments/{department}', [CollegeOfficeController::class, 'destroyDepartment']);

        Route::get('/checklist-items', [ApiController::class, 'listChecklistItems']);
        Route::patch('/item-checklist-items/task/{itemNo}/disable', [ApiController::class, 'disableItemChecklistTask']);
        Route::patch('/item-checklist-items/task/{itemNo}/enable', [ApiController::class, 'enableItemChecklistTask']);
        Route::patch('/item-checklist-items/{id}/disable', [ApiController::class, 'disableItemChecklistItem']);
        Route::patch('/item-checklist-items/{id}/enable', [ApiController::class, 'enableItemChecklistItem']);
    });

    Route::middleware('role:superadmin,admin,encoder')->group(function () {
        Route::get('/reference-data', [ApiController::class, 'referenceData']);

        Route::get('/college-offices', [CollegeOfficeController::class, 'index']);
        Route::get('/college-offices/{collegeOffice}/departments', [CollegeOfficeController::class, 'departments']);

        Route::get('/preventive-maintenance-plans', [PreventiveMaintenancePlanController::class, 'index']);

        Route::get('/preventive-maintenance', [ApiController::class, 'listPreventiveMaintenance']);
        Route::get('/preventive-maintenance/next-identifier', [ApiController::class, 'previewPreventiveMaintenanceIdentifier']);
        Route::get('/preventive-maintenance/{preventiveMaintenanceId}/item-checklists', [ApiController::class, 'listItemChecklistsForPm']);
        Route::get('/preventive-maintenance/{id}/revisions', [ApiController::class, 'listPreventiveMaintenanceRevisions']);
        Route::get('/preventive-maintenance/{id}/revisions/{revisionId}', [ApiController::class, 'getPreventiveMaintenanceRevision']);
        Route::get('/preventive-maintenance/{id}/photos/{photoIndex}', [ApiController::class, 'viewPreventiveMaintenancePhoto']);
        Route::get('/preventive-maintenance/{id}/photo', [ApiController::class, 'viewPreventiveMaintenancePhoto']);
        Route::get('/preventive-maintenance/{id}', [ApiController::class, 'getPreventiveMaintenance']);
        Route::post('/preventive-maintenance', [ApiController::class, 'storePreventiveMaintenance']);
        Route::put('/preventive-maintenance/{id}', [ApiController::class, 'updatePreventiveMaintenance']);
        Route::get('/preventive-maintenance/{id}/print', [ApiController::class, 'printPreventiveMaintenance']);

        Route::get('/item-checklist-entries/{preventiveMaintenanceId}', [ApiController::class, 'itemChecklistEntries']);
        Route::get('/item-checklist/{id}', [ApiController::class, 'getItemChecklist']);
        Route::post('/item-checklist', [ApiController::class, 'storeItemChecklist']);
        Route::put('/item-checklist/{id}', [ApiController::class, 'updateItemChecklist']);
        Route::get('/item-checklist/{id}/print', [ApiController::class, 'printItemChecklist']);
        Route::get('/item-checklist/{id}/print-with-pm', [ApiController::class, 'printItemChecklistWithPM']);
        Route::post('/item-checklist/{id}/print-qr-code', [ApiController::class, 'printItemChecklistQrCode']);
        Route::post('/item-checklist/{id}/print-barcode', [ApiController::class, 'printItemChecklistBarcode']);
        Route::get('/item-checklist/{id}/view-pdf-link', [ApiController::class, 'getItemChecklistViewPdfLink']);
        Route::get('/item-checklist/{id}/CMU-F-4-DTO-002-Preventive-Maintenance-Checklist.pdf', [ApiController::class, 'viewItemChecklistPdfSigned'])
            ->name('api.item-checklist.view-pdf.signed')
            ->middleware('signed');
    });

    Route::delete('/preventive-maintenance/{id}', [ApiController::class, 'deletePreventiveMaintenance'])
        ->middleware('role:superadmin');
    Route::patch('/preventive-maintenance/{id}/lock', [ApiController::class, 'lockPreventiveMaintenance'])
        ->middleware('role:superadmin');
    Route::patch('/preventive-maintenance/{id}/unlock', [ApiController::class, 'unlockPreventiveMaintenance'])
        ->middleware('role:superadmin');
    Route::delete('/item-checklist/{id}', [ApiController::class, 'deleteItemChecklist'])
        ->middleware('role:superadmin,admin');
    Route::patch('/item-checklist/{id}/lock', [ApiController::class, 'lockItemChecklist'])
        ->middleware('role:superadmin,admin');
    Route::patch('/item-checklist/{id}/unlock', [ApiController::class, 'unlockItemChecklist'])
        ->middleware('role:superadmin,admin');
});
