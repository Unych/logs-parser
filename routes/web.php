<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/api/stats/requests', [DashboardController::class, 'requestsChart'])->name('stats.requests');
Route::get('/api/stats/browsers', [DashboardController::class, 'browsersChart'])->name('stats.browsers');
Route::get('/api/stats/table',    [DashboardController::class, 'table'])->name('stats.table');

Route::middleware('throttle:upload-logs')
    ->post('/imports', [LogImportController::class, 'store'])
    ->name('imports.store');

Route::get('/imports/{job}/progress', [LogImportController::class, 'progress'])
    ->whereNumber('job')
    ->name('imports.progress');
