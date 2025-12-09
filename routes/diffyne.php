<?php

use Diffyne\Http\Controllers\DiffyneController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('/update', [DiffyneController::class, 'update'])->withoutMiddleware(VerifyCsrfToken::class)->name('diffyne.update');
Route::post('/update/lazy', [DiffyneController::class, 'loadLazy'])->withoutMiddleware(VerifyCsrfToken::class)->name('diffyne.loadLazy');
Route::post('/upload', [DiffyneController::class, 'upload'])->withoutMiddleware(VerifyCsrfToken::class)->name('diffyne.upload');
Route::get('/preview', [DiffyneController::class, 'preview'])->withoutMiddleware(VerifyCsrfToken::class)->name('diffyne.preview');
Route::get('/health', [DiffyneController::class, 'health'])->withoutMiddleware(VerifyCsrfToken::class)->name('diffyne.health');
