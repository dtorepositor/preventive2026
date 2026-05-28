<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'layouts.app')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/scan/checklist/{code}', [ApiController::class, 'redirectScannedChecklist'])
    ->name('scan.checklist');
Route::get('/c/{id}', [ApiController::class, 'redirectChecklistByItemId'])
    ->whereNumber('id')
    ->name('scan.checklist.short');

Route::middleware(['auth', 'role:superadmin,admin,encoder'])->group(function () {
    Route::view('/dashboard', 'layouts.app')->name('dashboard');
    Route::view('/about', 'layouts.app')->name('about');

    Route::view('/records', 'layouts.app')->name('records');
    Route::get('/maintenance-records', fn () => redirect()->route('records'));
    Route::view('/preventive-maintenance', 'layouts.app');
    Route::view('/preventive-maintenance/create', 'layouts.app');
    Route::view('/preventive-maintenance/{id}', 'layouts.app')->whereNumber('id');
    Route::view('/preventive-maintenance/{id}/edit', 'layouts.app')->whereNumber('id');
    Route::view('/preventive-maintenance/{id}/revisions/{revisionId}', 'layouts.app')
        ->whereNumber('id')
        ->whereNumber('revisionId');
    Route::view('/preventive-maintenance/{id}/item-checklist/create', 'layouts.app')->whereNumber('id');
    Route::view('/preventive-maintenance/{pmId}/item-checklist/{id}', 'layouts.app')
        ->whereNumber('pmId')
        ->whereNumber('id');
    Route::view('/preventive-maintenance/{pmId}/item-checklist/{id}/edit', 'layouts.app')
        ->whereNumber('pmId')
        ->whereNumber('id');
});

Route::middleware(['auth', 'role:superadmin,admin'])->group(function () {
    Route::view('/users', 'layouts.app');
    Route::view('/colleges', 'layouts.app')->name('colleges');
    Route::get('/departments', fn () => redirect()->route('colleges'));
    Route::view('/checklist-items', 'layouts.app');
    Route::view('/reports', 'layouts.app');
    Route::view('/preventive-maintenance/plan', 'layouts.app')
        ->name('preventive-maintenance.plan');
    Route::view('/preventive-maintenance/reports', 'layouts.app')
        ->name('preventive-maintenance.reports');
});

Route::middleware(['auth', 'role:superadmin'])->group(function () {
    Route::view('/settings', 'layouts.app');
    Route::get('/print-test', [ApiController::class, 'printTest'])
        ->name('print.test');
});

Route::fallback(function () {
    return view('layouts.app');
})->middleware(['auth', 'role:superadmin,admin,encoder']);
