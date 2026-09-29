<?php
use App\Http\Controllers\{SessionController, CompanyController};
use Illuminate\Support\Facades\Route;
Route::redirect('/', '/companies');
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::post('/supply-categories', [App\Http\Controllers\SupplyCategoryController::class, 'store'])->name('supply-categories.store');
    Route::get('/companies/{company}/history', [CompanyController::class, 'history'])->name('companies.history');
    Route::resource('companies', CompanyController::class);
});
