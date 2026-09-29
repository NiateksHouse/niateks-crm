<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ActivityController;
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
    Route::get('/projects', [ProjectController::class,'index'])->name('projects.index');
    Route::get('/companies/{company}/projects/create', [ProjectController::class,'create'])->name('projects.create');
    Route::post('/companies/{company}/projects', [ProjectController::class,'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class,'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class,'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class,'update'])->name('projects.update');
    Route::get('/projects/{project}/history', [ProjectController::class,'history'])->name('projects.history');
    Route::get('/companies/{company}/activities/create', [ActivityController::class,'create'])->name('activities.create');
    Route::post('/companies/{company}/activities', [ActivityController::class,'store'])->name('activities.store');
    Route::get('/activities/{activity}/edit', [ActivityController::class,'edit'])->name('activities.edit');
    Route::put('/activities/{activity}', [ActivityController::class,'update'])->name('activities.update');
    Route::get('/activities/{activity}/history', [ActivityController::class,'history'])->name('activities.history');
});
