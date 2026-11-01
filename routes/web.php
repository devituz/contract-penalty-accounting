<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\PenaltyDecisionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/contracts');

    Route::resource('contracts', ContractController::class)->except('destroy');
    Route::post('/contracts/{contract}/activate', [ContractController::class, 'activate'])->name('contracts.activate');
    Route::post('/contracts/{contract}/deliveries', [DeliveryController::class, 'store'])->name('contracts.deliveries.store');
    Route::post('/contracts/{contract}/penalty/approve', [PenaltyDecisionController::class, 'approve'])->name('contracts.penalty.approve');
    Route::post('/contracts/{contract}/penalty/reject', [PenaltyDecisionController::class, 'reject'])->name('contracts.penalty.reject');

    Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting/payments', [AccountingController::class, 'register'])->name('accounting.register');
    Route::get('/accounting/contracts/{contract}', [AccountingController::class, 'show'])->name('accounting.show');
    Route::post('/accounting/contracts/{contract}/payments', [AccountingController::class, 'store'])->name('accounting.payments.store');
    Route::post('/accounting/payments/{payment}/void', [AccountingController::class, 'void'])->name('accounting.payments.void');

    Route::get('/budget', [BudgetController::class, 'index'])->name('budget.index');
    Route::post('/budget', [BudgetController::class, 'store'])->name('budget.store');
    Route::put('/budget/{budgetLine}', [BudgetController::class, 'update'])->name('budget.update');
});
