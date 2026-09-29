<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupplyCategoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/companies');
Route::middleware('guest')->group(function () {
    Route::get('/activate', [ActivationController::class, 'create'])->name('activation.create');
    Route::post('/activate', [ActivationController::class, 'store'])->middleware('throttle:activation')->name('activation.store');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::post('/supply-categories', [SupplyCategoryController::class, 'store'])->name('supply-categories.store');
    Route::get('/companies/{company}/history', [CompanyController::class, 'history'])->name('companies.history');
    Route::resource('companies', CompanyController::class);
});
