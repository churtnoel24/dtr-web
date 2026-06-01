<?php

use App\Http\Controllers\DtrDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DtrDashboardController::class, 'index'])->name('dtr.dashboard');
// Route::post('/upload', [DtrDashboardController::class, 'upload'])->name('dtr.upload');
Route::get('/report', [DtrDashboardController::class, 'report'])->name('dtr.report');
